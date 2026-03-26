<script setup>
import StudentLayout from "@/Layouts/StudentLayout.vue";
import { Head, Link } from "@inertiajs/vue3";
import { ref, computed } from "vue";
import AdaptiveTree from "@/Components/AdaptiveTree.vue";

const props = defineProps({
    assessment: Object,
    summary: Object,
    attempts: Array,
});

const expandedAttemptIds = ref(new Set());

const toggleAccordion = (attemptId) => {
    const next = new Set(expandedAttemptIds.value);
    if (next.has(attemptId)) {
        next.delete(attemptId);
    } else {
        next.add(attemptId);
    }
    expandedAttemptIds.value = next;
};

const isAccordionOpen = (attemptId) => expandedAttemptIds.value.has(attemptId);

const hasAdaptives = (attempt) => {
    const list = attempt.adaptive_assessments || [];
    return Array.isArray(list) && list.length > 0;
};

/** Adaptive node + all deeper nodes under its attempts (matches AdaptiveTree). */
const countAdaptiveSubtreeNodes = (adaptive) => {
    let n = 1;
    for (const att of adaptive.attempts || []) {
        for (const child of att.adaptive_assessments || []) {
            n += countAdaptiveSubtreeNodes(child);
        }
    }
    return n;
};

const countTotalAdaptives = (adaptives) => {
    if (!adaptives || !Array.isArray(adaptives)) return 0;
    return adaptives.reduce((total, adaptive) => total + countAdaptiveSubtreeNodes(adaptive), 0);
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

const getScoreColor = (score) => {
    if (score >= 75) return "text-emerald-600 dark:text-emerald-400";
    if (score >= 50) return "text-amber-600 dark:text-amber-400";
    return "text-rose-600 dark:text-rose-400";
};

const scoreAccentBorder = (score) => {
    if (score >= 75) return "border-l-emerald-500 dark:border-l-emerald-400";
    if (score >= 50) return "border-l-amber-500 dark:border-l-amber-400";
    return "border-l-rose-500 dark:border-l-rose-400";
};

const isBestAttempt = (attemptNo) => attemptNo === props.summary.best_attempt_no;

const isLatestAttempt = (index) => index === 0;

const hasAttempts = computed(() => (props.attempts?.length || 0) > 0);
</script>

<template>
    <StudentLayout>
        <Head :title="`History — ${assessment.title}`" />

        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 pb-16 sm:pb-12 pt-4 sm:pt-6">
            <!-- Page header -->
            <header class="mb-10 sm:mb-12">
                <p
                    class="text-[11px] sm:text-xs font-medium uppercase tracking-[0.14em] text-text-secondary mb-3"
                >
                    Assessment history
                </p>
                <h1
                    class="text-[clamp(1.375rem,4vw,1.875rem)] font-semibold tracking-tight text-text-primary dark:text-text-inverted leading-tight"
                >
                    {{ assessment.title }}
                </h1>
                <p
                    class="mt-3 text-sm text-text-secondary leading-relaxed max-w-prose"
                >
                    <span>{{ assessment.subject.name }} ({{ assessment.subject.code }})</span>
                    <span class="mx-2 text-border-light dark:text-border-dark" aria-hidden="true">·</span>
                    <span>{{ assessment.lesson.title }}</span>
                </p>

                <div
                    class="mt-6 flex flex-col sm:flex-row sm:items-center gap-3 sm:gap-4"
                >
                    <Link
                        :href="route('student.assessments.show', assessment.id)"
                        class="inline-flex items-center justify-center min-h-[44px] px-6 rounded-full text-sm font-medium bg-accent-primary text-white hover:bg-accent-muted transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-accent-primary focus-visible:ring-offset-2 dark:focus-visible:ring-offset-surface-dark"
                    >
                        Take assessment
                    </Link>
                    <Link
                        :href="route('student.assessments.index')"
                        class="inline-flex items-center justify-center min-h-[44px] px-2 text-sm font-medium text-text-secondary hover:text-text-primary dark:hover:text-text-inverted transition-colors"
                    >
                        ← All assessments
                    </Link>
                </div>
            </header>

            <!-- Summary metrics: one band, no card grid -->
            <section
                class="mb-12 sm:mb-14 py-6 sm:py-7 border-y border-border-light dark:border-border-dark"
                aria-label="Summary"
            >
                <div
                    class="grid grid-cols-1 sm:grid-cols-3 gap-8 sm:gap-6 sm:divide-x sm:divide-border-light sm:dark:divide-border-dark"
                >
                    <div class="sm:pr-6 sm:first:pl-0">
                        <p class="text-[11px] font-medium uppercase tracking-[0.12em] text-text-secondary">
                            Total attempts
                        </p>
                        <p
                            class="mt-2 text-3xl sm:text-4xl font-semibold tabular-nums tracking-tight text-text-primary dark:text-text-inverted"
                        >
                            {{ summary.total_attempts }}
                        </p>
                    </div>
                    <div class="sm:px-6">
                        <p class="text-[11px] font-medium uppercase tracking-[0.12em] text-text-secondary">
                            Best score
                        </p>
                        <p
                            class="mt-2 text-3xl sm:text-4xl font-semibold tabular-nums tracking-tight"
                            :class="getScoreColor(summary.best_score ?? 0)"
                        >
                            {{ summary.best_score }}%
                        </p>
                        <p
                            v-if="summary.best_attempt_no"
                            class="mt-1.5 text-xs text-text-secondary"
                        >
                            Attempt {{ summary.best_attempt_no }}
                        </p>
                    </div>
                    <div class="sm:pl-6">
                        <p class="text-[11px] font-medium uppercase tracking-[0.12em] text-text-secondary">
                            Last submitted
                        </p>
                        <p
                            class="mt-2 text-base sm:text-lg font-medium text-text-primary dark:text-text-inverted leading-snug"
                        >
                            {{ formatDate(summary.latest_attempt_date) || "—" }}
                        </p>
                    </div>
                </div>
            </section>

            <!-- Attempts -->
            <section aria-labelledby="attempts-heading">
                <div class="flex items-end justify-between gap-4 mb-6 sm:mb-8">
                    <h2
                        id="attempts-heading"
                        class="text-lg sm:text-xl font-semibold text-text-primary dark:text-text-inverted tracking-tight"
                    >
                        Attempts
                    </h2>
                    <span
                        v-if="hasAttempts"
                        class="text-xs font-medium tabular-nums text-text-secondary"
                    >
                        {{ attempts.length }} record{{ attempts.length !== 1 ? "s" : "" }}
                    </span>
                </div>

                <!-- Empty -->
                <div
                    v-if="!hasAttempts"
                    class="py-16 sm:py-20 px-4 text-center border border-dashed border-border-light dark:border-border-dark rounded-2xl bg-surface-muted/30 dark:bg-surface-dark-muted/20"
                >
                    <div
                        class="mx-auto mb-5 flex h-14 w-14 items-center justify-center rounded-2xl bg-surface-muted dark:bg-surface-dark-muted text-text-secondary"
                    >
                        <svg class="h-7 w-7 opacity-70" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="1.5"
                                d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"
                            />
                        </svg>
                    </div>
                    <h3 class="text-base font-semibold text-text-primary dark:text-text-inverted">
                        No attempts yet
                    </h3>
                    <p class="mt-2 text-sm text-text-secondary max-w-sm mx-auto leading-relaxed">
                        When you complete this assessment, each attempt will appear here with your score and breakdown.
                    </p>
                    <Link
                        :href="route('student.assessments.show', assessment.id)"
                        class="mt-8 inline-flex items-center justify-center min-h-[44px] px-6 rounded-full text-sm font-medium bg-accent-primary text-white hover:bg-accent-muted transition-colors"
                    >
                        Start assessment
                    </Link>
                </div>

                <!-- List -->
                <ul v-else class="space-y-4 sm:space-y-5 list-none p-0 m-0">
                    <li v-for="(attempt, index) in attempts" :key="attempt.id">
                        <article
                            class="rounded-2xl border border-border-light dark:border-border-dark bg-surface dark:bg-surface-dark-muted overflow-hidden transition-shadow hover:shadow-[0_8px_30px_rgba(0,0,0,0.06)] dark:hover:shadow-none"
                            :class="['border-l-[3px]', scoreAccentBorder(attempt.score)]"
                        >
                            <!-- Top row: meta + score -->
                            <div class="p-5 sm:p-6 sm:pb-5">
                                <div
                                    class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4"
                                >
                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-center gap-2 mb-2">
                                            <span
                                                class="inline-flex items-center justify-center min-w-[2rem] h-8 px-2 rounded-lg text-xs font-semibold tabular-nums bg-surface-muted dark:bg-surface-dark text-text-primary dark:text-text-inverted"
                                            >
                                                #{{ attempt.attempt_no }}
                                            </span>
                                            <span
                                                v-if="isBestAttempt(attempt.attempt_no)"
                                                class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-medium ring-1 ring-inset ring-amber-500/35 text-amber-800 dark:text-amber-200 bg-amber-500/10"
                                            >
                                                Best
                                            </span>
                                            <span
                                                v-if="isLatestAttempt(index)"
                                                class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-medium ring-1 ring-inset ring-sky-500/35 text-sky-800 dark:text-sky-200 bg-sky-500/10"
                                            >
                                                Latest
                                            </span>
                                        </div>
                                        <time
                                            class="text-sm text-text-secondary tabular-nums"
                                            :datetime="attempt.created_at"
                                        >
                                            {{ formatDate(attempt.created_at) }}
                                        </time>
                                    </div>
                                    <div class="flex sm:flex-col sm:items-end gap-1 sm:text-right shrink-0">
                                        <span
                                            class="text-4xl sm:text-[2.75rem] font-semibold tabular-nums leading-none tracking-tight"
                                            :class="getScoreColor(attempt.score)"
                                        >
                                            {{ attempt.score }}%
                                        </span>
                                        <span class="text-[11px] uppercase tracking-wider text-text-secondary sm:mt-1">
                                            Score
                                        </span>
                                    </div>
                                </div>

                                <!-- Metrics: inline, scannable -->
                                <dl
                                    class="mt-6 flex flex-wrap gap-x-6 gap-y-3 text-sm border-t border-border-light/80 dark:border-border-dark/80 pt-5"
                                >
                                    <div class="flex items-baseline gap-2">
                                        <dt class="text-text-secondary">Total</dt>
                                        <dd class="font-semibold tabular-nums text-text-primary dark:text-text-inverted">
                                            {{ attempt.total_questions }}
                                        </dd>
                                    </div>
                                    <div class="flex items-baseline gap-2">
                                        <dt class="text-emerald-600 dark:text-emerald-400">Correct</dt>
                                        <dd class="font-semibold tabular-nums text-emerald-600 dark:text-emerald-400">
                                            {{ attempt.correct_answers }}
                                        </dd>
                                    </div>
                                    <div class="flex items-baseline gap-2">
                                        <dt class="text-rose-600 dark:text-rose-400">Incorrect</dt>
                                        <dd class="font-semibold tabular-nums text-rose-600 dark:text-rose-400">
                                            {{ attempt.wrong_answers }}
                                        </dd>
                                    </div>
                                    <div class="flex items-baseline gap-2">
                                        <dt class="text-text-secondary">Unanswered</dt>
                                        <dd class="font-semibold tabular-nums text-text-primary dark:text-text-inverted">
                                            {{ attempt.no_answer }}
                                        </dd>
                                    </div>
                                </dl>
                            </div>

                            <!-- Actions -->
                            <div
                                class="px-5 sm:px-6 py-4 bg-surface-muted/40 dark:bg-surface-dark/40 border-t border-border-light/70 dark:border-border-dark/70"
                            >
                                <div class="flex flex-col gap-3">
                                    <Link
                                        :href="
                                            route('student.assessments.results', {
                                                assessment: assessment.id,
                                                attempt: attempt.id,
                                            })
                                        "
                                        class="inline-flex w-full sm:w-auto sm:min-w-[10rem] items-center justify-center min-h-[44px] px-5 rounded-full text-sm font-medium border border-accent-primary text-accent-primary hover:bg-accent-primary hover:text-white transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-accent-primary focus-visible:ring-offset-2 dark:focus-visible:ring-offset-surface-dark"
                                    >
                                        View results
                                    </Link>

                                    <div v-if="hasAdaptives(attempt)" class="w-full">
                                        <button
                                            type="button"
                                            class="flex w-full items-center justify-between gap-3 min-h-[44px] px-3 -mx-1 text-left text-sm font-medium text-text-primary dark:text-text-inverted rounded-lg hover:bg-surface-muted/80 dark:hover:bg-surface-dark-muted/50 transition-colors"
                                            :aria-expanded="isAccordionOpen(attempt.id)"
                                            :aria-controls="`adaptive-${attempt.id}`"
                                            @click="toggleAccordion(attempt.id)"
                                        >
                                            <span class="flex items-center gap-2 min-w-0">
                                                <svg
                                                    class="h-4 w-4 shrink-0 text-accent-primary opacity-80"
                                                    fill="none"
                                                    stroke="currentColor"
                                                    viewBox="0 0 24 24"
                                                    aria-hidden="true"
                                                >
                                                    <path
                                                        stroke-linecap="round"
                                                        stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"
                                                    />
                                                </svg>
                                                <span class="truncate">
                                                    Follow-ups
                                                    <span class="text-text-secondary font-normal">
                                                        ({{ countTotalAdaptives(attempt.adaptive_assessments) }})
                                                    </span>
                                                </span>
                                            </span>
                                            <svg
                                                class="h-5 w-5 shrink-0 text-text-secondary transition-transform duration-200"
                                                :class="{ 'rotate-180': isAccordionOpen(attempt.id) }"
                                                fill="none"
                                                stroke="currentColor"
                                                viewBox="0 0 24 24"
                                                aria-hidden="true"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    stroke-width="2"
                                                    d="M19 9l-7 7-7-7"
                                                />
                                            </svg>
                                        </button>
                                        <div
                                            :id="`adaptive-${attempt.id}`"
                                            v-show="isAccordionOpen(attempt.id)"
                                            class="mt-3 pl-1 border-l border-border-light dark:border-border-dark ml-1.5"
                                        >
                                            <div class="pl-4 py-1">
                                                <AdaptiveTree
                                                    :adaptives="attempt.adaptive_assessments"
                                                    :formatDate="formatDate"
                                                    :hide-separate-history-link="assessment.type !== 'adaptive'"
                                                />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </article>
                    </li>
                </ul>
            </section>
        </div>
    </StudentLayout>
</template>
