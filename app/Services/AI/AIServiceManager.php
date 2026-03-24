<?php

namespace App\Services\AI;

use App\Models\AiTokenUsage;
use App\Services\ContentProcessing\ContentChunker;
use App\Services\ContentProcessing\ContextSummarizer;
use App\Services\ContentProcessing\TokenCalculator;
use Illuminate\Support\Facades\Log;

class AIServiceManager
{
    protected array $providers = [];
    protected ContentChunker $chunker;
    protected ContextSummarizer $summarizer;
    protected TokenCalculator $tokenCalculator;
    protected BloomsValidator $bloomsValidator;

    protected function recordTokenUsage(
        string $provider,
        string $feature,
        string $inputText,
        mixed $outputPayload,
        ?array $providerUsage = null,
        ?string $model = null,
        array $meta = []
    ): void {
        try {
            $inputTokens = 0;
            $outputTokens = 0;
            $totalTokens = 0;
            $isEstimated = true;

            // OpenAI/Groq style usage
            if (is_array($providerUsage)) {
                $inputTokens = (int) ($providerUsage['prompt_tokens'] ?? $providerUsage['input_tokens'] ?? 0);
                $outputTokens = (int) ($providerUsage['completion_tokens'] ?? $providerUsage['output_tokens'] ?? 0);
                $totalTokens = (int) ($providerUsage['total_tokens'] ?? ($inputTokens + $outputTokens));
                $isEstimated = $totalTokens <= 0;
            }

            if ($isEstimated) {
                $inputTokens = $this->tokenCalculator->estimateTokens($inputText);

                $outputText = is_string($outputPayload)
                    ? $outputPayload
                    : json_encode($outputPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

                $outputTokens = $this->tokenCalculator->estimateTokens($outputText ?: '');
                $totalTokens = $inputTokens + $outputTokens;
                $isEstimated = true;
            }

            AiTokenUsage::create([
                'user_id' => auth()->id(),
                'provider' => $provider,
                'model' => $model,
                'feature' => $feature,
                'input_tokens' => max(0, $inputTokens),
                'output_tokens' => max(0, $outputTokens),
                'total_tokens' => max(0, $totalTokens),
                'is_estimated' => $isEstimated,
                'meta' => [
                    ...$meta,
                    'raw_usage' => $providerUsage,
                ],
            ]);
        } catch (\Throwable $e) {
            // Never fail the user flow due to telemetry/logging
            Log::warning('AIServiceManager: Failed to record token usage', [
                'provider' => $provider,
                'feature' => $feature,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function extractProviderTelemetry(AIServiceInterface $provider): array
    {
        $usage = method_exists($provider, 'getLastUsage') ? $provider->getLastUsage() : null;
        $promptText = method_exists($provider, 'getLastPromptText') ? $provider->getLastPromptText() : null;
        $model = method_exists($provider, 'getModel') ? $provider->getModel() : null;

        return [
            'usage' => is_array($usage) ? $usage : null,
            'promptText' => is_string($promptText) ? $promptText : null,
            'model' => is_string($model) ? $model : null,
        ];
    }

    public function __construct(
        ContentChunker $chunker,
        ContextSummarizer $summarizer,
        TokenCalculator $tokenCalculator,
        BloomsValidator $bloomsValidator
    ) {
        $this->chunker = $chunker;
        $this->summarizer = $summarizer;
        $this->tokenCalculator = $tokenCalculator;
        $this->bloomsValidator = $bloomsValidator;

        $this->initializeProviders();
    }

    /**
     * Initialize providers in fallback order.
     */
    protected function initializeProviders(): void
    {
        $fallbackOrder = config('ai_models.fallback_order');

        foreach ($fallbackOrder as $providerName) {
            $this->providers[$providerName] = $this->createProvider($providerName);
        }
    }

    /**
     * Create a provider instance.
     */
    protected function createProvider(string $providerName): AIServiceInterface
    {
        $config = $this->getProviderConfig($providerName);

        return match ($providerName) {
            'openai' => new OpenAIProvider($config),
            'groq' => new GroqProvider($config),
            'gemini' => new GeminiProvider($config),
            default => throw new \Exception("Unknown provider: {$providerName}"),
        };
    }

    /**
     * Get normalized configuration for a provider.
     */
    public function getProviderConfig(string $providerName): array
    {
        $providerConfig = config("ai_models.providers.{$providerName}");

        if (!$providerConfig) {
            throw new \Exception("Provider config not found: {$providerName}");
        }

        return [
            'name' => $providerName,
            'api_key' => $providerConfig['api_key'] ?? '',
            'model' => $providerConfig['model'] ?? '',
            'max_input_tokens' => $providerConfig['limits']['max_input_tokens'] ?? 8000,
            'max_output_tokens' => $providerConfig['limits']['max_output_tokens'] ?? 4096,
            'safe_limit' => $providerConfig['limits']['safe_limit'] ?? 6000,
            'timeout' => config('ai_models.timeout', 120),
        ];
    }

    /**
     * Get the primary provider's safe limit for chunking decisions.
     */
    protected function getPrimarySafeLimit(): int
    {
        $primaryProvider = config('ai_models.primary_provider');
        $config = $this->getProviderConfig($primaryProvider);

        return $config['safe_limit'];
    }

    /**
     * Main entry point for generating assessments.
     */
    public function generateAssessment(string $content, array $config): array
    {
        $safeLimit = $this->getPrimarySafeLimit();
        $contentTokens = $this->tokenCalculator->estimateTokens($content);

        // Calculate total questions requested
        $totalQuestions = 0;
        if (isset($config['question_distribution'])) {
            foreach ($config['question_distribution'] as $levelCounts) {
                $totalQuestions += ($levelCounts['mcq'] ?? 0)
                    + ($levelCounts['identification'] ?? 0)
                    + ($levelCounts['tf'] ?? 0);
            }
        }

        $maxQuestionsPerCall = 35; // Force chunking if > 35 questions to preserve quality
        $numChunksByQuestions = $totalQuestions > 0 ? (int) ceil($totalQuestions / $maxQuestionsPerCall) : 1;

        if ($numChunksByQuestions > 1 || !$this->tokenCalculator->fitsInModel($contentTokens, $safeLimit)) {
            if ($numChunksByQuestions > 1) {
                $bufferTokens = config('ai_models.chunking.buffer_tokens', 10000);
                // Lower the effective safe limit to naturally induce chunking
                // e.g., if 30,000 tokens and 3 chunks, effective limit = 10,000 + buffer
                $calculatedLimit = (int) ceil($contentTokens / $numChunksByQuestions) + $bufferTokens;
                $safeLimit = min($safeLimit, $calculatedLimit);
            }
            $result = $this->processChunks($content, $config, $safeLimit);
        } else {
            $result = $this->processSingleRequest($content, $config);
        }

        // Apply strict slicer to trim overgeneration
        if (isset($result['data']) && isset($config['question_distribution'])) {
            $result['data'] = $this->sliceToExactCounts($result['data'], $config['question_distribution']);
        }

        return $result;
    }

    /**
     * Post-processes generated AI output to trim down excess elements.
     */
    protected function sliceToExactCounts(array $data, array $distribution): array
    {
        if (empty($distribution)) {
            return $data;
        }

        $grouped = [
            'multiple_choice' => [],
            'identification' => [],
            'true_or_false' => [],
        ];

        foreach (['multiple_choice', 'identification', 'true_or_false'] as $type) {
            if (isset($data[$type])) {
                foreach ($data[$type] as $item) {
                    $level = $item['bloom_level'] ?? 'untagged';
                    if (!isset($grouped[$type][$level])) {
                        $grouped[$type][$level] = [];
                    }
                    $grouped[$type][$level][] = $item;
                }
            }
        }

        $finalData = [
            'multiple_choice' => [],
            'identification' => [],
            'true_or_false' => [],
        ];

        foreach ($distribution as $level => $counts) {
            $mcqTarget = $counts['mcq'] ?? 0;
            $idTarget = $counts['identification'] ?? 0;
            $tfTarget = $counts['tf'] ?? 0;

            if ($mcqTarget > 0 && isset($grouped['multiple_choice'][$level])) {
                $finalData['multiple_choice'] = array_merge(
                    $finalData['multiple_choice'], 
                    array_slice($grouped['multiple_choice'][$level], 0, $mcqTarget)
                );
            }

            if ($idTarget > 0 && isset($grouped['identification'][$level])) {
                $finalData['identification'] = array_merge(
                    $finalData['identification'], 
                    array_slice($grouped['identification'][$level], 0, $idTarget)
                );
            }

            if ($tfTarget > 0 && isset($grouped['true_or_false'][$level])) {
                $finalData['true_or_false'] = array_merge(
                    $finalData['true_or_false'], 
                    array_slice($grouped['true_or_false'][$level], 0, $tfTarget)
                );
            }
        }

        return $finalData;
    }

    /**
     * Process content in a single request (no chunking).
     */
    protected function processSingleRequest(string $content, array $config): array
    {
        $lastException = null;
        $fallbackOrder = config('ai_models.fallback_order');
        $usedProvider = null;

        foreach ($fallbackOrder as $providerName) {
            $provider = $this->providers[$providerName];

            try {
                $result = $provider->generateAssessment($content, $config);
                $usedProvider = $providerName;
                $telemetry = $this->extractProviderTelemetry($provider);

                Log::info('AIServiceManager: Raw AI result before validation', [
                    'provider' => $providerName,
                    'result_keys' => array_keys($result),
                    'mc_count' => count($result['multiple_choice'] ?? []),
                    'id_count' => count($result['identification'] ?? []),
                    'tf_count' => count($result['true_or_false'] ?? []),
                ]);

                // Run Bloom's validation if bloom_levels are specified and it's NOT an adaptive generation
                $bloomLevels = $config['bloom_levels'] ?? null;
                $isAdaptive = $config['is_adaptive'] ?? false;
                
                if ($bloomLevels && !$isAdaptive) {
                    $result = $this->bloomsValidator->validate($result, $bloomLevels);

                    Log::info('AIServiceManager: Result after Bloom\'s validation', [
                        'mc_count' => count($result['multiple_choice'] ?? []),
                        'id_count' => count($result['identification'] ?? []),
                        'tf_count' => count($result['true_or_false'] ?? []),
                    ]);
                }

                $this->recordTokenUsage(
                    provider: $providerName,
                    feature: ($config['is_adaptive'] ?? false) ? 'assessment_generate_adaptive' : 'assessment_generate',
                    inputText: $telemetry['promptText'] ?? $content,
                    outputPayload: $result,
                    providerUsage: $telemetry['usage'],
                    model: $telemetry['model'],
                    meta: [
                        'chunks_processed' => 1,
                    ]
                );

                return [
                    'success' => true,
                    'data' => $result,
                    'provider_used' => $providerName,
                    'chunks_processed' => 1,
                ];

            } catch (\Exception $e) {
                // Retry once before moving to next provider
                try {
                    $result = $provider->generateAssessment($content, $config);
                    $usedProvider = $providerName;
                    $telemetry = $this->extractProviderTelemetry($provider);

                    // Run Bloom's validation on retry result too (if not adaptive)
                    $bloomLevels = $config['bloom_levels'] ?? null;
                    $isAdaptive = $config['is_adaptive'] ?? false;
                    
                    if ($bloomLevels && !$isAdaptive) {
                        $result = $this->bloomsValidator->validate($result, $bloomLevels);
                    }

                    $this->recordTokenUsage(
                        provider: $providerName,
                        feature: ($config['is_adaptive'] ?? false) ? 'assessment_generate_adaptive' : 'assessment_generate',
                        inputText: $telemetry['promptText'] ?? $content,
                        outputPayload: $result,
                        providerUsage: $telemetry['usage'],
                        model: $telemetry['model'],
                        meta: [
                            'chunks_processed' => 1,
                            'retry_used' => true,
                        ]
                    );

                    return [
                        'success' => true,
                        'data' => $result,
                        'provider_used' => $providerName,
                        'chunks_processed' => 1,
                        'retry_used' => true,
                    ];

                } catch (\Exception $retryException) {
                    $lastException = $retryException;
                    continue;
                }
            }
        }

        throw new \Exception('All AI providers failed: ' . ($lastException?->getMessage() ?? 'Unknown error'));
    }

    /**
     * Process content with chunking.
     */
    protected function processChunks(string $content, array $config, int $safeLimit): array
    {
        $bufferTokens = config('ai_models.chunking.buffer_tokens');
        $overlapPercentage = config('ai_models.chunking.overlap_percentage');

        $chunkingResult = $this->chunker->chunk($content, $safeLimit, $bufferTokens, $overlapPercentage);
        $chunks = $chunkingResult['chunks'];
        $totalChunks = $chunkingResult['total_chunks'];

        // Calculate total questions from distribution matrix
        $totalQuestions = 0;
        if (isset($config['question_distribution'])) {
            foreach ($config['question_distribution'] as $levelCounts) {
                $totalQuestions += ($levelCounts['mcq'] ?? 0)
                    + ($levelCounts['identification'] ?? 0)
                    + ($levelCounts['tf'] ?? 0);
            }
        }

        $questionsPerChunk = $this->chunker->distributeQuestions($totalQuestions, $totalChunks);

        $lastException = null;
        $fallbackOrder = config('ai_models.fallback_order');

        foreach ($fallbackOrder as $providerName) {
            $provider = $this->providers[$providerName];

            try {
                $allResults = $this->processAllChunksWithProvider(
                    provider: $provider,
                    providerName: $providerName,
                    chunks: $chunks,
                    config: $config,
                    questionsPerChunk: $questionsPerChunk
                );

                $combinedResult = $this->combineChunkResults($allResults);

                // Run Bloom's validation on combined results (if not adaptive)
                $bloomLevels = $config['bloom_levels'] ?? null;
                $isAdaptive = $config['is_adaptive'] ?? false;
                
                if ($bloomLevels && !$isAdaptive) {
                    $combinedResult = $this->bloomsValidator->validate($combinedResult, $bloomLevels);
                }

                return [
                    'success' => true,
                    'data' => $combinedResult,
                    'provider_used' => $providerName,
                    'chunks_processed' => $totalChunks,
                ];

            } catch (\Exception $e) {
                $lastException = $e;
                continue;
            }
        }

        throw new \Exception('All AI providers failed to process chunks: ' . ($lastException?->getMessage() ?? 'Unknown error'));
    }

    /**
     * Process all chunks with a single provider.
     */
    protected function processAllChunksWithProvider(
        AIServiceInterface $provider,
        string $providerName,
        array $chunks,
        array $config,
        array $questionsPerChunk
    ): array {
        $allResults = [];
        $previousSummaries = [];
        $resolvedProviderName = $providerName;

        foreach ($chunks as $index => $chunk) {
            $chunkNumber = $index + 1;
            $chunkContent = $chunk['content'];

            $previousContext = $this->summarizer->combineSummaries($previousSummaries);

            $chunkConfig = $this->distributeQuestionsForChunk(
                $config,
                $questionsPerChunk[$index]
            );

            try {
                $result = $provider->generateChunk($chunkContent, $previousContext, $chunkConfig);
                $telemetry = $this->extractProviderTelemetry($provider);

                $allResults[] = $result;
                $previousSummaries[] = $this->summarizer->summarizeChunk($chunkContent);

                $this->recordTokenUsage(
                    provider: $resolvedProviderName,
                    feature: ($config['is_adaptive'] ?? false) ? 'chunk_generate_adaptive' : 'chunk_generate',
                    inputText: $telemetry['promptText'] ?? ($previousContext . "\n\n" . $chunkContent),
                    outputPayload: $result,
                    providerUsage: $telemetry['usage'],
                    model: $telemetry['model'],
                    meta: [
                        'chunk_number' => $chunkNumber,
                        'total_chunks' => count($chunks),
                    ]
                );

            } catch (\Exception $e) {
                // Retry once
                try {
                    $result = $provider->generateChunk($chunkContent, $previousContext, $chunkConfig);
                    $telemetry = $this->extractProviderTelemetry($provider);

                    $allResults[] = $result;
                    $previousSummaries[] = $this->summarizer->summarizeChunk($chunkContent);

                    $this->recordTokenUsage(
                        provider: $resolvedProviderName,
                        feature: ($config['is_adaptive'] ?? false) ? 'chunk_generate_adaptive' : 'chunk_generate',
                        inputText: $telemetry['promptText'] ?? ($previousContext . "\n\n" . $chunkContent),
                        outputPayload: $result,
                        providerUsage: $telemetry['usage'],
                        model: $telemetry['model'],
                        meta: [
                            'chunk_number' => $chunkNumber,
                            'total_chunks' => count($chunks),
                            'retry_used' => true,
                        ]
                    );

                } catch (\Exception $retryException) {
                    throw new \Exception("Chunk {$chunkNumber} failed after retry: " . $retryException->getMessage());
                }
            }
        }

        return $allResults;
    }

    /**
     * Distribute questions for a single chunk proportionally.
     */
    protected function distributeQuestionsForChunk(array $config, int $totalQuestionsForChunk): array
    {
        $distribution = $config['question_distribution'] ?? [];
        
        // Calculate total requested across all levels and types
        $totalRequested = 0;
        foreach ($distribution as $counts) {
            $totalRequested += ($counts['mcq'] ?? 0) + ($counts['identification'] ?? 0) + ($counts['tf'] ?? 0);
        }

        if ($totalRequested === 0) {
            return $config;
        }
        
        $chunkDistribution = [];
        $remainingQuestions = $totalQuestionsForChunk;

        // Pro-rata distribution per level and per type
        foreach ($distribution as $level => $counts) {
            $chunkDistribution[$level] = [
                'mcq' => (int) round((($counts['mcq'] ?? 0) / $totalRequested) * $totalQuestionsForChunk),
                'identification' => (int) round((($counts['identification'] ?? 0) / $totalRequested) * $totalQuestionsForChunk),
                'tf' => (int) round((($counts['tf'] ?? 0) / $totalRequested) * $totalQuestionsForChunk),
            ];
            $remainingQuestions -= ($chunkDistribution[$level]['mcq'] + $chunkDistribution[$level]['identification'] + $chunkDistribution[$level]['tf']);
        }
        
        // Adjust any rounding errors to match exact $totalQuestionsForChunk if possible
        // This is a naive adjustment on the first available non-zero count to ensure total matches exactly
        if ($remainingQuestions !== 0) {
            foreach ($chunkDistribution as $level => &$counts) {
                foreach (['mcq', 'identification', 'tf'] as $type) {
                    if ($counts[$type] > 0 || $remainingQuestions > 0) {
                        $adjustment = min(abs($remainingQuestions), $counts[$type]);
                        if ($remainingQuestions > 0) {
                            $counts[$type]++;
                            $remainingQuestions--;
                        } elseif ($remainingQuestions < 0 && $counts[$type] > 0) {
                            $counts[$type]--;
                            $remainingQuestions++;
                        }
                    }
                    if ($remainingQuestions === 0) break 2;
                }
            }
        }

        return [
            'question_distribution' => $chunkDistribution,
            'bloom_levels' => $config['bloom_levels'] ?? ['remember', 'understand'],
        ];
    }

    /**
     * Combine results from all chunks into a single result.
     */
    protected function combineChunkResults(array $allResults): array
    {
        $combined = [
            'multiple_choice' => [],
            'identification' => [],
            'true_or_false' => [],
        ];

        foreach ($allResults as $result) {
            if (isset($result['multiple_choice'])) {
                $combined['multiple_choice'] = array_merge(
                    $combined['multiple_choice'],
                    $result['multiple_choice']
                );
            }

            if (isset($result['identification'])) {
                $combined['identification'] = array_merge(
                    $combined['identification'],
                    $result['identification']
                );
            }

            if (isset($result['true_or_false'])) {
                $combined['true_or_false'] = array_merge(
                    $combined['true_or_false'],
                    $result['true_or_false']
                );
            }
        }

        return $combined;
    }
}
