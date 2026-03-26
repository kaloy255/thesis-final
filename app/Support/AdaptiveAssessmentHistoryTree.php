<?php

namespace App\Support;

use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use Illuminate\Support\Collection;

/**
 * Builds nested adaptive-assessment payloads for history views (student + instructor).
 * Child adaptives are grouped by source_attempt_id under each parent attempt (not a flat children list).
 */
class AdaptiveAssessmentHistoryTree
{
    /**
     * Map siblings with display titles "Follow-up #1", "Follow-up #2", … (order = created_at from query).
     *
     * @param  Collection<int, Assessment>  $assessments
     * @return list<array<string, mixed>>
     */
    public static function mapBranchesWithFollowUpTitles(Collection $assessments, int $studentId, array $options = []): array
    {
        return $assessments->values()->map(function (Assessment $adaptive, int $idx) use ($studentId, $options) {
            $node = self::mapBranch($adaptive, $studentId, $options);
            $node['title'] = 'Follow-up #'.($idx + 1);

            return $node;
        })->all();
    }

    /**
     * Map one adaptive assessment node: full student attempt list, each with spawned follow-ups.
     *
     * @param  array<string, mixed>  $options  'include_latest_attempt_id' => bool (for instructor UI)
     * @return array<string, mixed>
     */
    public static function mapBranch(Assessment $adaptive, int $studentId, array $options = []): array
    {
        $includeLatestAttemptId = $options['include_latest_attempt_id'] ?? false;

        $totalQuestions = $adaptive->items()->count();

        $attemptRows = AssessmentAttempt::query()
            ->where('student_id', $studentId)
            ->where('assessment_id', $adaptive->id)
            ->with('answers')
            ->latest('created_at')
            ->get();

        $adaptivesByAttemptId = Assessment::query()
            ->where('parent_assessment_id', $adaptive->id)
            ->whereNotNull('source_attempt_id')
            ->where('type', 'adaptive')
            ->orderBy('created_at')
            ->get()
            ->groupBy('source_attempt_id');

        $attemptsPayload = self::mapAttemptsPayload(
            $attemptRows,
            $totalQuestions,
            $adaptivesByAttemptId,
            $studentId,
            $options
        );

        $latestAttempt = $attemptRows->first();
        $score = null;
        if ($latestAttempt) {
            $correct = $latestAttempt->answers->where('correct_answer', true)->count();
            $score = $totalQuestions > 0 ? round(($correct / $totalQuestions) * 100, 2) : 0;
        }

        $node = [
            'id' => $adaptive->id,
            'title' => AdaptiveAssessmentTitle::forHistoryListing($adaptive->title),
            'created_at' => $adaptive->created_at,
            'score' => $score,
            'attempts' => $attemptsPayload,
        ];

        if ($includeLatestAttemptId) {
            $node['latest_attempt_id'] = $latestAttempt?->id;
        }

        return $node;
    }

    /**
     * @param  Collection<int, AssessmentAttempt>  $attemptRows
     * @param  Collection<int, Collection<int, Assessment>>  $adaptivesByAttemptId
     * @return list<array<string, mixed>>
     */
    public static function mapAttemptsPayload(
        Collection $attemptRows,
        int $totalQuestions,
        Collection $adaptivesByAttemptId,
        int $studentId,
        array $options = []
    ): array {
        return $attemptRows->map(function (AssessmentAttempt $attempt) use ($totalQuestions, $adaptivesByAttemptId, $studentId, $options) {
            $correctAnswers = $attempt->answers->where('correct_answer', true)->count();
            $wrongAnswers = $attempt->answers->where('correct_answer', false)->count();
            $noAnswer = $attempt->answers->whereNull('choices')->count();
            $score = $totalQuestions > 0
                ? round(($correctAnswers / $totalQuestions) * 100, 2)
                : 0;

            $spawned = $adaptivesByAttemptId->get($attempt->id, collect());
            $adaptiveAssessments = self::mapBranchesWithFollowUpTitles($spawned, $studentId, $options);

            return [
                'id' => $attempt->id,
                'attempt_no' => $attempt->attempt_no,
                'created_at' => $attempt->created_at,
                'score' => $score,
                'correct_answers' => $correctAnswers,
                'wrong_answers' => $wrongAnswers,
                'no_answer' => $noAnswer,
                'total_questions' => $totalQuestions,
                'adaptive_assessments' => $adaptiveAssessments,
            ];
        })->values()->all();
    }
}
