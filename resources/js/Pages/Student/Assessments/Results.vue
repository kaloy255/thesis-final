<script setup>
import StudentLayout from "@/Layouts/StudentLayout.vue";
import { Head, Link, usePage, useForm } from "@inertiajs/vue3";
import { computed, ref, watch, nextTick, onMounted } from "vue";
import ProcessingModal from "@/Components/ProcessingModal.vue";

const props = defineProps({
    assessment: Object,
    attempt: Object,
    results: Object,
    items: Array,
    show_adaptive_button: Boolean,
    has_wrong_answers: Boolean,
});

const page = usePage();
const adaptiveLoading = ref(false);
const adaptiveError = ref(null);
const showAdaptiveModal = ref(false);

const showProcessingModal = ref(false);
const uploadProgress = ref(0);
const currentStage = ref("");

const adaptiveForm = useForm({
    multiple_choice_count: 0,
    identification_count: 0,
    true_or_false_count: 0,
});

onMounted(() => {
    if (page.props.errors?.error) {
        adaptiveError.value = page.props.errors.error;
    }
});

const showAdaptiveButton = computed(() => props.show_adaptive_button === true);

const openAdaptiveModal = () => {
    adaptiveForm.reset();
    showAdaptiveModal.value = true;
};

const closeAdaptiveModal = () => {
    showAdaptiveModal.value = false;
    adaptiveForm.reset();
};

const totalRequestedCounts = computed(() => {
    return (adaptiveForm.multiple_choice_count || 0) +
        (adaptiveForm.identification_count || 0) +
        (adaptiveForm.true_or_false_count || 0);
});

const isValidAdaptiveRequest = computed(() => {
    const total = totalRequestedCounts.value;
    const minRequired = props.results.wrong_answers || 0;
    const maxAllowed = props.results.total_questions || 0;
    return total >= minRequired && total <= maxAllowed;
});

const generateAdaptive = () => {
    if (!isValidAdaptiveRequest.value) return;

    adaptiveError.value = null;
    showAdaptiveModal.value = false;

    showProcessingModal.value = true;
    uploadProgress.value = 10;
    currentStage.value = "Analyzing mistakes and content...";

    adaptiveForm.post(
        route("student.assessments.adaptive", {
            assessment: props.assessment.id,
            attempt: props.attempt.id,
        }),
        {
            preserveScroll: true,
            onProgress: (progress) => {
                uploadProgress.value = Math.min(90, progress.percentage || 0);
                if (uploadProgress.value > 50) {
                    currentStage.value = "Generating adaptive questions...";
                }
            },
            onSuccess: () => {
                showProcessingModal.value = false;
                uploadProgress.value = 100;
            },
            onError: (errors) => {
                adaptiveError.value = errors.error || "Failed to generate adaptive assessment.";
                uploadProgress.value = 0;
            },
            onFinish: () => {
                adaptiveLoading.value = false;
            },
        }
    );
};

const handleProcessingClose = () => {
    showProcessingModal.value = false;
    if (!uploadProgress.value || adaptiveError.value) {
        adaptiveError.value = null;
    }
};

const cancelAdaptiveUpload = () => {
    adaptiveForm.cancel();
    showProcessingModal.value = false;
    adaptiveError.value = null;
    uploadProgress.value = 0;
    currentStage.value = "";
};

const retryAdaptiveUpload = () => {
    adaptiveError.value = null;
    uploadProgress.value = 0;
    currentStage.value = "";
    generateAdaptive();
};

const currentQuestionIndex = ref(0);

const totalQuestions = computed(() => props.items?.length || 0);

const currentQuestion = computed(() => {
    return props.items?.[currentQuestionIndex.value] || null;
});

const isFirstQuestion = computed(() => currentQuestionIndex.value === 0);

const isLastQuestion = computed(() => currentQuestionIndex.value === totalQuestions.value - 1);

const getChoices = (item) => {
    if (!item.choices) return [];
    if (Array.isArray(item.choices)) return item.choices;
    if (typeof item.choices === "string") {
        try {
            return JSON.parse(item.choices);
        } catch (e) {
            return [];
        }
    }
    return [];
};

const formatDate = (dateString) => {
    if (!dateString) return null;
    const date = new Date(dateString);
    return date.toLocaleDateString("en-US", {
        year: "numeric",
        month: "short",
        day: "numeric",
        hour: "2-digit",
        minute: "2-digit",
    });
};

const scoreColor = computed(() => {
    const score = props.results.score;
    if (score >= 75) return "text-emerald-600 dark:text-emerald-400";
    if (score >= 50) return "text-amber-600 dark:text-amber-400";
    return "text-rose-600 dark:text-rose-400";
});

const scoreAccentBorder = computed(() => {
    const score = props.results.score;
    if (score >= 75) return "border-l-[3px] border-l-emerald-500 dark:border-l-emerald-400";
    if (score >= 50) return "border-l-[3px] border-l-amber-500 dark:border-l-amber-400";
    return "border-l-[3px] border-l-rose-500 dark:border-l-rose-400";
});

const nextQuestion = () => {
    if (currentQuestionIndex.value < totalQuestions.value - 1) {
        currentQuestionIndex.value++;
    }
};

const previousQuestion = () => {
    if (currentQuestionIndex.value > 0) {
        currentQuestionIndex.value--;
    }
};

const goToQuestion = (index) => {
    if (index >= 0 && index < totalQuestions.value) {
        currentQuestionIndex.value = index;
    }
};

const paginationButtonRefs = ref([]);
const setPaginationButtonRef = (el, index) => {
    if (el) {
        paginationButtonRefs.value[index] = el;
    }
};

watch(currentQuestionIndex, async () => {
    await nextTick();
    const btn = paginationButtonRefs.value[currentQuestionIndex.value];
    if (btn) {
        btn.scrollIntoView({ behavior: "smooth", block: "nearest", inline: "center" });
    }
}, { immediate: true });

const questionStatusAccent = (item) => {
    if (item.is_correct) return "border-l-[3px] border-l-emerald-500 dark:border-l-emerald-400";
    if (item.student_answer !== null && item.student_answer !== "") {
        return "border-l-[3px] border-l-rose-500 dark:border-l-rose-400";
    }
    return "border-l-[3px] border-l-amber-500/80 dark:border-l-amber-400/80";
};
</script>

<template>
    <StudentLayout>
        <Head :title="`Results — ${assessment.title}`" />

        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 pb-36 sm:pb-12 pt-4 sm:pt-6">
            <!-- Header -->
            <header class="mb-10 sm:mb-12">
                <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 mb-6">
                    <div>
                        <p
                            class="text-[11px] sm:text-xs font-medium uppercase tracking-[0.14em] text-text-secondary mb-3"
                        >
                            Results
                        </p>
                        <h1
                            class="text-[clamp(1.375rem,4vw,1.875rem)] font-semibold tracking-tight text-text-primary dark:text-text-inverted leading-tight"
                        >
                            {{ assessment.title }}
                        </h1>
                        <p class="mt-3 text-sm text-text-secondary leading-relaxed max-w-prose">
                            <span>{{ assessment.subject.name }} ({{ assessment.subject.code }})</span>
                            <span class="mx-2 text-border-light dark:text-border-dark" aria-hidden="true">·</span>
                            <span>{{ assessment.lesson.title }}</span>
                        </p>
                        <p class="mt-2 text-sm text-text-secondary">
                            Attempt <span class="font-medium text-text-primary dark:text-text-inverted tabular-nums">#{{ attempt.attempt_no }}</span>
                            <span class="mx-1.5 text-border-light dark:text-border-dark" aria-hidden="true">·</span>
                            <time :datetime="attempt.created_at">{{ formatDate(attempt.created_at) }}</time>
                        </p>
                    </div>
                    <Link
                        :href="route('student.assessments.history', assessment.id)"
                        class="inline-flex shrink-0 items-center justify-center min-h-[44px] px-5 rounded-full text-sm font-medium border border-border-light dark:border-border-dark text-text-primary dark:text-text-inverted hover:bg-surface-muted dark:hover:bg-surface-dark-muted transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-accent-primary focus-visible:ring-offset-2 dark:focus-visible:ring-offset-surface-dark"
                    >
                        All attempts
                    </Link>
                </div>
            </header>

            <!-- Score summary -->
            <section
                class="mb-10 sm:mb-12 rounded-2xl border border-border-light dark:border-border-dark bg-surface dark:bg-surface-dark-muted overflow-hidden"
                :class="scoreAccentBorder"
                aria-label="Score summary"
            >
                <div class="px-5 sm:px-8 py-8 sm:py-10 text-center sm:text-left sm:flex sm:items-end sm:justify-between sm:gap-8">
                    <div>
                        <p class="text-[11px] font-medium uppercase tracking-[0.12em] text-text-secondary">
                            Your score
                        </p>
                        <p
                            class="mt-2 text-5xl sm:text-6xl font-semibold tabular-nums tracking-tight leading-none"
                            :class="scoreColor"
                        >
                            {{ results.score }}%
                        </p>
                        <p class="mt-3 text-sm text-text-secondary">
                            {{ results.correct_answers }} of {{ results.total_questions }} correct
                        </p>
                    </div>
                    <dl
                        class="mt-8 sm:mt-0 flex flex-wrap justify-center sm:justify-end gap-x-8 gap-y-4 text-sm border-t border-border-light/80 dark:border-border-dark/80 sm:border-0 sm:pt-0 pt-6"
                    >
                        <div>
                            <dt class="text-text-secondary">Total</dt>
                            <dd class="mt-0.5 font-semibold tabular-nums text-text-primary dark:text-text-inverted">
                                {{ results.total_questions }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-emerald-600 dark:text-emerald-400">Correct</dt>
                            <dd class="mt-0.5 font-semibold tabular-nums text-emerald-600 dark:text-emerald-400">
                                {{ results.correct_answers }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-rose-600 dark:text-rose-400">Incorrect</dt>
                            <dd class="mt-0.5 font-semibold tabular-nums text-rose-600 dark:text-rose-400">
                                {{ results.wrong_answers }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-text-secondary">Unanswered</dt>
                            <dd class="mt-0.5 font-semibold tabular-nums text-text-primary dark:text-text-inverted">
                                {{ results.no_answer }}
                            </dd>
                        </div>
                    </dl>
                </div>
            </section>

            <!-- Adaptive CTA -->
            <section
                v-if="showAdaptiveButton"
                class="mb-10 sm:mb-12 pl-5 sm:pl-6 border-l-2 border-accent-primary/70 py-1"
            >
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-5">
                    <div class="flex gap-4 min-w-0">
                        <div
                            class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-accent-primary/12 dark:bg-accent-primary/20 text-accent-primary"
                        >
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"
                                />
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <h2 class="text-base font-semibold text-text-primary dark:text-text-inverted">
                                Practice weak areas
                            </h2>
                            <p class="mt-1 text-sm text-text-secondary leading-relaxed">
                                Generate a short adaptive set based on what you missed.
                            </p>
                        </div>
                    </div>
                    <button
                        type="button"
                        class="inline-flex shrink-0 items-center justify-center min-h-[44px] px-6 rounded-full bg-accent-primary text-white text-sm font-medium hover:bg-accent-muted transition-colors disabled:opacity-60 disabled:cursor-not-allowed focus:outline-none focus-visible:ring-2 focus-visible:ring-accent-primary focus-visible:ring-offset-2 dark:focus-visible:ring-offset-surface-dark"
                        :disabled="adaptiveForm.processing || showProcessingModal"
                        @click="openAdaptiveModal"
                    >
                        Customize
                    </button>
                </div>
                <p
                    v-if="adaptiveError && !showProcessingModal"
                    class="mt-4 text-sm text-rose-600 dark:text-rose-400"
                >
                    {{ adaptiveError }}
                </p>
            </section>

            <ProcessingModal
                :show="showProcessingModal"
                type="adaptive"
                :progress="uploadProgress"
                :stage="currentStage"
                :error="adaptiveError"
                @close="handleProcessingClose"
                @cancel="cancelAdaptiveUpload"
                @retry="retryAdaptiveUpload"
            />

            <!-- Adaptive modal -->
            <Teleport to="body">
                <div
                    v-if="showAdaptiveModal"
                    class="fixed inset-0 z-50 flex items-end justify-center sm:items-center p-4 sm:p-6"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="adaptive-modal-title"
                >
                    <div
                        class="absolute inset-0 bg-black/50 dark:bg-black/70 backdrop-blur-[2px]"
                        aria-hidden="true"
                        @click="closeAdaptiveModal"
                    />
                    <div
                        class="relative w-full max-w-lg rounded-2xl border border-border-light dark:border-border-dark bg-surface dark:bg-surface-dark shadow-xl max-h-[90vh] overflow-y-auto"
                    >
                        <div class="p-5 sm:p-6">
                            <h3
                                id="adaptive-modal-title"
                                class="text-lg font-semibold text-text-primary dark:text-text-inverted"
                            >
                                Adaptive practice
                            </h3>
                            <p class="mt-2 text-sm text-text-secondary leading-relaxed">
                                Choose how many items per type. Total must be at least
                                <span class="font-semibold text-text-primary dark:text-text-inverted tabular-nums">{{ results.wrong_answers }}</span>
                                and at most
                                <span class="font-semibold text-text-primary dark:text-text-inverted tabular-nums">{{ results.total_questions }}</span>.
                            </p>

                            <div class="mt-6 space-y-4">
                                <div>
                                    <label for="mcq_count" class="block text-xs font-medium uppercase tracking-wide text-text-secondary">Multiple choice</label>
                                    <input
                                        id="mcq_count"
                                        v-model.number="adaptiveForm.multiple_choice_count"
                                        type="number"
                                        min="0"
                                        class="input mt-1.5"
                                    >
                                </div>
                                <div>
                                    <label for="identification_count" class="block text-xs font-medium uppercase tracking-wide text-text-secondary">Identification</label>
                                    <input
                                        id="identification_count"
                                        v-model.number="adaptiveForm.identification_count"
                                        type="number"
                                        min="0"
                                        class="input mt-1.5"
                                    >
                                </div>
                                <div>
                                    <label for="tf_count" class="block text-xs font-medium uppercase tracking-wide text-text-secondary">True / false</label>
                                    <input
                                        id="tf_count"
                                        v-model.number="adaptiveForm.true_or_false_count"
                                        type="number"
                                        min="0"
                                        class="input mt-1.5"
                                    >
                                </div>
                            </div>

                            <div
                                class="mt-5 rounded-xl px-3 py-2.5 text-sm"
                                :class="isValidAdaptiveRequest
                                    ? 'bg-emerald-500/10 text-emerald-800 dark:text-emerald-200'
                                    : 'bg-rose-500/10 text-rose-800 dark:text-rose-200'"
                            >
                                <span class="font-medium tabular-nums">Total: {{ totalRequestedCounts }}</span>
                                <span v-if="!isValidAdaptiveRequest && totalRequestedCounts < results.wrong_answers" class="block mt-1 text-xs opacity-90">
                                    Add {{ results.wrong_answers - totalRequestedCounts }} more to reach your mistake count.
                                </span>
                                <span v-if="!isValidAdaptiveRequest && totalRequestedCounts > results.total_questions" class="block mt-1 text-xs opacity-90">
                                    Reduce by {{ totalRequestedCounts - results.total_questions }} to stay within the limit.
                                </span>
                            </div>
                        </div>
                        <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2 px-5 sm:px-6 py-4 border-t border-border-light dark:border-border-dark bg-surface-muted/40 dark:bg-surface-dark-muted/30">
                            <button
                                type="button"
                                class="inline-flex justify-center min-h-[44px] px-5 rounded-full text-sm font-medium border border-border-light dark:border-border-dark text-text-primary dark:text-text-inverted hover:bg-surface-muted dark:hover:bg-surface-dark-muted transition-colors"
                                @click="closeAdaptiveModal"
                            >
                                Cancel
                            </button>
                            <button
                                type="button"
                                class="inline-flex justify-center min-h-[44px] px-6 rounded-full text-sm font-medium bg-accent-primary text-white hover:bg-accent-muted disabled:opacity-45 disabled:cursor-not-allowed transition-colors"
                                :disabled="!isValidAdaptiveRequest || adaptiveForm.processing"
                                @click="generateAdaptive"
                            >
                                Generate
                            </button>
                        </div>
                    </div>
                </div>
            </Teleport>

            <!-- Question review -->
            <section aria-labelledby="review-heading">
                <h2
                    id="review-heading"
                    class="text-lg font-semibold text-text-primary dark:text-text-inverted tracking-tight mb-6 sm:mb-8"
                >
                    Question review
                </h2>

                <div class="mb-8 sm:mb-10">
                    <div class="flex flex-wrap items-baseline justify-between gap-2 text-xs sm:text-sm text-text-secondary mb-3">
                        <span class="font-medium text-text-primary dark:text-text-inverted tabular-nums">
                            {{ currentQuestionIndex + 1 }} / {{ totalQuestions }}
                        </span>
                        <span>Review</span>
                    </div>
                    <div class="h-1 w-full rounded-full bg-border-light/90 dark:bg-border-dark/90 overflow-hidden">
                        <div
                            class="h-full bg-accent-primary transition-[width] duration-300 ease-out rounded-full"
                            :style="{ width: `${totalQuestions ? ((currentQuestionIndex + 1) / totalQuestions) * 100 : 0}%` }"
                        />
                    </div>
                </div>

                <div
                    v-if="currentQuestion"
                    class="mb-8 rounded-2xl border border-border-light dark:border-border-dark bg-surface dark:bg-surface-dark-muted overflow-hidden"
                    :class="questionStatusAccent(currentQuestion)"
                >
                    <div class="p-5 sm:p-7">
                        <p class="text-[11px] font-medium uppercase tracking-[0.12em] text-text-secondary mb-3">
                            Question {{ currentQuestionIndex + 1 }}
                        </p>
                        <h3 class="text-[clamp(1.0625rem,2.8vw,1.25rem)] font-medium text-text-primary dark:text-text-inverted leading-snug mb-8">
                            {{ currentQuestion.question }}
                        </h3>

                        <!-- Multiple choice -->
                        <div
                            v-if="currentQuestion.type === 'multiple_choice'"
                            class="divide-y divide-border-light dark:divide-border-dark -mx-1"
                        >
                            <div
                                v-for="(choice, choiceIndex) in getChoices(currentQuestion)"
                                :key="choiceIndex"
                                class="flex items-start gap-3 py-3.5 sm:py-4 px-1 first:pt-0"
                                :class="{
                                    'bg-emerald-500/[0.06] dark:bg-emerald-500/10': choice === currentQuestion.correct_answer,
                                    'bg-rose-500/[0.06] dark:bg-rose-500/10':
                                        choice === currentQuestion.student_answer && !currentQuestion.is_correct,
                                }"
                            >
                                <span
                                    class="mt-0.5 w-5 shrink-0 text-center text-sm font-semibold"
                                    :class="{
                                        'text-emerald-600 dark:text-emerald-400': choice === currentQuestion.correct_answer,
                                        'text-rose-600 dark:text-rose-400':
                                            choice === currentQuestion.student_answer && !currentQuestion.is_correct,
                                        'text-text-secondary': choice !== currentQuestion.correct_answer
                                            && !(choice === currentQuestion.student_answer && !currentQuestion.is_correct),
                                    }"
                                    aria-hidden="true"
                                >
                                    <template v-if="choice === currentQuestion.correct_answer">✓</template>
                                    <template v-else-if="choice === currentQuestion.student_answer && !currentQuestion.is_correct">✗</template>
                                    <template v-else>·</template>
                                </span>
                                <div class="flex-1 min-w-0 flex flex-wrap items-baseline justify-between gap-2">
                                    <span class="text-[15px] sm:text-base text-text-primary dark:text-text-inverted leading-relaxed">
                                        {{ choice }}
                                    </span>
                                    <span
                                        v-if="choice === currentQuestion.correct_answer"
                                        class="text-[11px] font-medium uppercase tracking-wide text-emerald-600 dark:text-emerald-400 shrink-0"
                                    >
                                        Correct
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Identification -->
                        <div v-else-if="currentQuestion.type === 'identification'" class="space-y-6">
                            <div>
                                <p class="text-xs font-medium uppercase tracking-wide text-text-secondary mb-2">Your answer</p>
                                <p
                                    class="text-base border-b border-border-light dark:border-border-dark pb-2"
                                    :class="currentQuestion.is_correct
                                        ? 'text-emerald-700 dark:text-emerald-300'
                                        : 'text-rose-700 dark:text-rose-300'"
                                >
                                    {{ currentQuestion.student_answer || "—" }}
                                </p>
                            </div>
                            <div>
                                <p class="text-xs font-medium uppercase tracking-wide text-text-secondary mb-2">Correct answer</p>
                                <p class="text-base text-emerald-700 dark:text-emerald-300 border-b border-emerald-500/40 pb-2">
                                    {{ currentQuestion.correct_answer }}
                                </p>
                            </div>
                        </div>

                        <!-- True / false -->
                        <div v-else-if="currentQuestion.type === 'true_or_false'" class="space-y-6">
                            <div>
                                <p class="text-xs font-medium uppercase tracking-wide text-text-secondary mb-2">Your answer</p>
                                <p
                                    class="text-base border-b border-border-light dark:border-border-dark pb-2"
                                    :class="currentQuestion.is_correct
                                        ? 'text-emerald-700 dark:text-emerald-300'
                                        : 'text-rose-700 dark:text-rose-300'"
                                >
                                    {{ currentQuestion.student_answer || "—" }}
                                </p>
                            </div>
                            <div>
                                <p class="text-xs font-medium uppercase tracking-wide text-text-secondary mb-2">Correct answer</p>
                                <p class="text-base text-emerald-700 dark:text-emerald-300 border-b border-emerald-500/40 pb-2">
                                    {{ currentQuestion.correct_answer }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Nav: sticky on mobile -->
                <div
                    class="max-sm:fixed max-sm:inset-x-0 max-sm:bottom-0 max-sm:z-30 max-sm:mt-0 max-sm:border-t max-sm:border-border-light/80 max-sm:dark:border-border-dark/80 max-sm:bg-surface/95 max-sm:dark:bg-surface-dark/95 max-sm:backdrop-blur-md max-sm:px-4 max-sm:pt-3 max-sm:pb-[max(0.75rem,env(safe-area-inset-bottom))] max-sm:shadow-[0_-4px_24px_rgba(0,0,0,0.06)] max-sm:dark:shadow-[0_-4px_24px_rgba(0,0,0,0.25)] sm:mt-10 sm:pt-8 sm:border-t sm:border-border-light sm:dark:border-border-dark"
                >
                    <div
                        class="flex gap-1.5 overflow-x-auto pb-3 sm:pb-2 -mx-1 px-1 scroll-smooth max-sm:[scrollbar-width:none] max-sm:[-ms-overflow-style:none] max-sm:[&::-webkit-scrollbar]:hidden"
                    >
                        <button
                            v-for="(item, index) in items"
                            :key="item.id"
                            type="button"
                            :ref="(el) => setPaginationButtonRef(el, index)"
                            class="flex-shrink-0 w-9 h-9 rounded-full text-xs font-medium transition-all focus:outline-none focus-visible:ring-2 focus-visible:ring-accent-primary focus-visible:ring-offset-2 dark:focus-visible:ring-offset-surface-dark"
                            :class="[
                                index === currentQuestionIndex
                                    ? 'bg-accent-primary text-white shadow-sm'
                                    : item.is_correct
                                        ? 'text-emerald-700 dark:text-emerald-300 bg-emerald-500/12 dark:bg-emerald-500/15'
                                        : 'text-rose-700 dark:text-rose-300 bg-rose-500/12 dark:bg-rose-500/15',
                            ]"
                            :title="`Question ${index + 1}`"
                            @click="goToQuestion(index)"
                        >
                            {{ index + 1 }}
                        </button>
                    </div>
                    <div class="flex items-center gap-2 sm:gap-4 sm:justify-between sm:pt-2">
                        <button
                            type="button"
                            class="flex-1 sm:flex-none inline-flex items-center justify-center min-h-[48px] sm:min-h-[44px] sm:px-5 rounded-full text-sm font-medium text-text-secondary border border-transparent max-sm:border-border-light max-sm:dark:border-border-dark hover:text-text-primary dark:hover:text-text-inverted active:bg-surface-muted dark:active:bg-surface-dark-muted transition-colors disabled:opacity-35 disabled:pointer-events-none"
                            :disabled="isFirstQuestion"
                            @click="previousQuestion"
                        >
                            Back
                        </button>
                        <button
                            type="button"
                            class="flex-1 sm:flex-none inline-flex items-center justify-center min-h-[48px] sm:min-h-[44px] sm:px-8 rounded-full text-sm font-medium bg-accent-primary text-white hover:bg-accent-muted transition-colors disabled:opacity-35 disabled:pointer-events-none"
                            :disabled="isLastQuestion"
                            @click="nextQuestion"
                        >
                            Next
                        </button>
                    </div>
                </div>
            </section>
        </div>
    </StudentLayout>
</template>
