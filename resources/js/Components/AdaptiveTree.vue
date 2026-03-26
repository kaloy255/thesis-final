<script setup>
import { ref } from "vue";
import { Link } from "@inertiajs/vue3";

const props = defineProps({
    adaptives: {
        type: Array,
        required: true,
    },
    depth: {
        type: Number,
        default: 0,
    },
    formatDate: {
        type: Function,
        required: true,
    },
    showActions: {
        type: Boolean,
        default: true,
    },
    role: {
        type: String,
        default: "student", // 'student' or 'instructor'
    },
    studentId: {
        type: Number,
        default: null,
    },
    assessmentId: {
        type: Number,
        default: null,
    },
    /** When true, hide "View History" (hub page lists everything here). */
    hideSeparateHistoryLink: {
        type: Boolean,
        default: false,
    },
});

/** One adaptive node + everything spawned under its attempts (recursive). */
const countAdaptiveSubtreeNodes = (node) => {
    if (!node) return 0;
    let n = 1;
    for (const a of node.attempts || []) {
        for (const child of a.adaptive_assessments || []) {
            n += countAdaptiveSubtreeNodes(child);
        }
    }
    return n;
};

/** Sum subtree sizes for top-level adaptive rows (matches History.vue). */
const countTotalAdaptivesInList = (list) => {
    if (!list || !Array.isArray(list)) return 0;
    return list.reduce((t, node) => t + countAdaptiveSubtreeNodes(node), 0);
};

const followUpAccordionKey = (adaptiveId, attemptId) => `${adaptiveId}-${attemptId}`;

/** Presence in set = accordion expanded (same pattern as History.vue). */
const expandedFollowUpKeys = ref(new Set());

const toggleFollowUpsAccordion = (key) => {
    const next = new Set(expandedFollowUpKeys.value);
    if (next.has(key)) {
        next.delete(key);
    } else {
        next.add(key);
    }
    expandedFollowUpKeys.value = next;
};

const isFollowUpsAccordionOpen = (key) => expandedFollowUpKeys.value.has(key);

const hasSpawnedFollowUps = (att) =>
    Array.isArray(att.adaptive_assessments) && att.adaptive_assessments.length > 0;

const scorePillClass = (score) => {
    if (score >= 75) {
        return "bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400";
    }
    if (score >= 50) {
        return "bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400";
    }
    return "bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400";
};

const getScoreColor = (score) => {
    if (score >= 75) return "text-emerald-600 dark:text-emerald-400";
    if (score >= 50) return "text-amber-600 dark:text-amber-400";
    return "text-rose-600 dark:text-rose-400";
};

const followUpAttempts = (adaptive) =>
    Array.isArray(adaptive.attempts) ? adaptive.attempts : [];

const bestAttemptNo = (adaptive) => {
    const list = followUpAttempts(adaptive);
    if (!list.length) return null;
    let best = list[0];
    for (const a of list) {
        if (a.score > best.score) best = a;
    }
    return best.attempt_no;
};
</script>

<template>
    <div class="space-y-4">
        <div
            v-for="adaptive in adaptives"
            :key="adaptive.id"
            class="py-3"
            :class="{ 'border-t border-border-light dark:border-border-dark': depth === 0 }"
        >
            <div
                class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between sm:gap-4"
            >
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                        <span
                            class="text-sm font-medium text-text-primary dark:text-text-inverted break-words min-w-0"
                        >
                            {{ adaptive.title }}
                        </span>
                        <span
                            v-if="adaptive.score !== null"
                            class="shrink-0 px-2 py-0.5 text-[10px] font-bold rounded-full"
                            :class="scorePillClass(adaptive.score)"
                        >
                            {{ adaptive.score }}%
                        </span>
                    </div>
                    <div
                        class="flex items-center gap-2 text-xs text-text-secondary mt-1 tabular-nums"
                    >
                        <time :datetime="adaptive.created_at">{{
                            formatDate(adaptive.created_at)
                        }}</time>
                    </div>
                </div>

                <div
                    v-if="showActions && role === 'student'"
                    class="flex flex-row flex-nowrap items-center gap-2 shrink-0"
                >
                    <Link
                        :href="route('student.assessments.show', adaptive.id)"
                        class="inline-flex items-center justify-center px-3 py-1.5 text-xs font-medium rounded-lg bg-accent-primary text-white hover:bg-accent-muted transition-colors whitespace-nowrap"
                    >
                        Take Assessment
                    </Link>
                    <Link
                        v-if="!hideSeparateHistoryLink"
                        :href="route('student.assessments.history', adaptive.id)"
                        class="inline-flex items-center justify-center px-3 py-1.5 text-xs font-medium bg-surface dark:bg-surface-dark border border-border-light dark:border-border-dark text-text-primary dark:text-text-inverted hover:bg-surface-muted dark:hover:bg-surface-dark-muted rounded-lg transition-colors whitespace-nowrap"
                    >
                        View History
                    </Link>
                </div>
            </div>

            <div
                class="mt-4 pt-3 border-t border-border-light/70 dark:border-border-dark/70"
            >
                <p
                    class="text-[11px] font-semibold uppercase tracking-wider text-text-secondary"
                >
                    Attempts
                </p>
                <ul
                    v-if="followUpAttempts(adaptive).length > 0"
                    class="mt-3 space-y-2.5 list-none p-0 m-0"
                >
                    <li
                        v-for="(att, attIndex) in followUpAttempts(adaptive)"
                        :key="att.id"
                        class="rounded-xl border border-border-light dark:border-border-dark bg-surface-muted/30 dark:bg-surface-dark/30 px-3 py-2.5 sm:px-4"
                    >
                        <div
                            class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between sm:gap-3"
                        >
                            <div class="min-w-0 flex flex-wrap items-center gap-2">
                                <span
                                    class="inline-flex items-center justify-center min-w-[2rem] h-7 px-2 rounded-md text-[11px] font-semibold tabular-nums bg-surface dark:bg-surface-dark text-text-primary dark:text-text-inverted"
                                >
                                    #{{ att.attempt_no }}
                                </span>
                                <span
                                    v-if="att.attempt_no === bestAttemptNo(adaptive)"
                                    class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-medium ring-1 ring-inset ring-amber-500/35 text-amber-800 dark:text-amber-200 bg-amber-500/10"
                                >
                                    Best
                                </span>
                                <span
                                    v-if="attIndex === 0"
                                    class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-medium ring-1 ring-inset ring-sky-500/35 text-sky-800 dark:text-sky-200 bg-sky-500/10"
                                >
                                    Latest
                                </span>
                                <time
                                    class="text-xs text-text-secondary tabular-nums"
                                    :datetime="att.created_at"
                                >
                                    {{ formatDate(att.created_at) }}
                                </time>
                            </div>
                            <div
                                class="flex flex-row flex-nowrap items-center gap-3 shrink-0"
                            >
                                <span
                                    class="text-lg font-semibold tabular-nums"
                                    :class="getScoreColor(att.score)"
                                >
                                    {{ att.score }}%
                                </span>
                                <Link
                                    v-if="role === 'student'"
                                    :href="
                                        route('student.assessments.results', {
                                            assessment: adaptive.id,
                                            attempt: att.id,
                                        })
                                    "
                                    class="inline-flex items-center justify-center px-3 py-1.5 text-xs font-medium rounded-lg border border-accent-primary text-accent-primary hover:bg-accent-primary hover:text-white transition-colors whitespace-nowrap"
                                >
                                    View results
                                </Link>
                                <Link
                                    v-else-if="role === 'instructor' && studentId"
                                    :href="
                                        route(
                                            'instructor.assessments.history.student.results',
                                            {
                                                assessment: adaptive.id,
                                                student: studentId,
                                                attempt: att.id,
                                            }
                                        )
                                    "
                                    class="inline-flex items-center justify-center px-3 py-1.5 text-xs font-medium rounded-lg border border-border-light dark:border-border-dark text-text-primary dark:text-text-inverted hover:bg-surface dark:hover:bg-surface-dark-muted transition-colors whitespace-nowrap"
                                >
                                    View results
                                </Link>
                            </div>
                        </div>
                        <dl
                            class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-[11px] text-text-secondary"
                        >
                            <div>
                                <span class="tabular-nums">{{ att.correct_answers }}</span>
                                correct
                            </div>
                            <div>
                                <span class="tabular-nums">{{ att.wrong_answers }}</span>
                                incorrect
                            </div>
                            <div v-if="att.no_answer > 0">
                                <span class="tabular-nums">{{ att.no_answer }}</span>
                                unanswered
                            </div>
                        </dl>

                        <div v-if="hasSpawnedFollowUps(att)" class="mt-3 w-full">
                            <button
                                type="button"
                                class="flex w-full items-center justify-between gap-3 min-h-[44px] px-3 -mx-1 text-left text-sm font-medium text-text-primary dark:text-text-inverted rounded-lg hover:bg-surface-muted/80 dark:hover:bg-surface-dark-muted/50 transition-colors"
                                :aria-expanded="
                                    isFollowUpsAccordionOpen(
                                        followUpAccordionKey(adaptive.id, att.id)
                                    )
                                "
                                :aria-controls="`followups-${adaptive.id}-${att.id}`"
                                @click="
                                    toggleFollowUpsAccordion(
                                        followUpAccordionKey(adaptive.id, att.id)
                                    )
                                "
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
                                            ({{
                                                countTotalAdaptivesInList(
                                                    att.adaptive_assessments
                                                )
                                            }})
                                        </span>
                                    </span>
                                </span>
                                <svg
                                    class="h-5 w-5 shrink-0 text-text-secondary transition-transform duration-200"
                                    :class="{
                                        'rotate-180': isFollowUpsAccordionOpen(
                                            followUpAccordionKey(adaptive.id, att.id)
                                        ),
                                    }"
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
                                :id="`followups-${adaptive.id}-${att.id}`"
                                v-show="
                                    isFollowUpsAccordionOpen(
                                        followUpAccordionKey(adaptive.id, att.id)
                                    )
                                "
                                class="mt-3 pl-1 border-l border-border-light dark:border-border-dark ml-1.5"
                            >
                                <div class="pl-4 py-1">
                                    <AdaptiveTree
                                        :adaptives="att.adaptive_assessments"
                                        :depth="depth + 1"
                                        :formatDate="formatDate"
                                        :showActions="showActions"
                                        :role="role"
                                        :studentId="studentId"
                                        :assessmentId="assessmentId"
                                        :hideSeparateHistoryLink="
                                            hideSeparateHistoryLink
                                        "
                                    />
                                </div>
                            </div>
                        </div>
                    </li>
                </ul>
                <p v-else class="mt-2 text-xs text-text-secondary leading-relaxed">
                    <template v-if="role === 'student'">
                        No attempts yet — use Take assessment when ready.
                    </template>
                    <template v-else> No attempts yet. </template>
                </p>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: "AdaptiveTree",
};
</script>
