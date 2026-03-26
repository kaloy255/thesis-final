<script setup>
import InstructorLayout from "@/Layouts/InstructorLayout.vue";
import Modal from "@/Components/Modal.vue";
import { Head, Link } from "@inertiajs/vue3";
import { ref } from "vue";
import AdaptiveTree from "@/Components/AdaptiveTree.vue";

const showCheatingDialog = ref(false);
const openCheatingDialog = () => { showCheatingDialog.value = true; };
const closeCheatingDialog = () => { showCheatingDialog.value = false; };

const props = defineProps({
    assessment: Object,
    student: Object,
    summary: Object,
    attempts: Array,
    cheating_logs: {
        type: Array,
        default: () => [],
    },
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

// Also calculate total inner adaptives recursively for the counter badge
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
    if (score >= 75) return "text-green-600 dark:text-green-400";
    if (score >= 50) return "text-yellow-600 dark:text-yellow-400";
    return "text-red-600 dark:text-red-400";
};

const getScoreBgColor = (score) => {
    if (score >= 75)
        return "bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-800";
    if (score >= 50)
        return "bg-yellow-50 dark:bg-yellow-900/20 border-yellow-200 dark:border-yellow-800";
    return "bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-800";
};

const isBestAttempt = (attemptId) => {
    return attemptId === props.summary.best_attempt_id;
};

const isLatestAttempt = (index) => {
    return index === props.attempts.length - 1;
};

// Cheating log helpers
const getEventBadgeClass = (eventType) => {
    switch (eventType) {
        case 'tab_switch':
            return 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-300';
        case 'page_leave':
            return 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300';
        case 'window_blur':
            return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300';
        default:
            return 'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-300';
    }
};

const getEventLabel = (eventType) => {
    switch (eventType) {
        case 'tab_switch': return 'Tab Switch';
        case 'page_leave': return 'Page Leave';
        case 'window_blur': return 'Window Blur';
        default: return 'Unknown';
    }
};
</script>

<template>
    <InstructorLayout>
        <Head :title="`History - ${student.name} - ${assessment.title}`" />

        <div class="max-w-4xl mx-auto">
            <div class="mb-4 sm:mb-5">
                <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
                    <div class="min-w-0">
                        <h1 class="text-lg sm:text-2xl font-semibold text-text-primary dark:text-text-inverted truncate">
                            {{ student.name }} - Attempts
                        </h1>
                        <p class="text-sm sm:text-base text-text-primary dark:text-text-inverted truncate mt-0.5">
                            {{ assessment.title }}
                        </p>
                        <div class="mt-1.5 flex flex-wrap items-center gap-2 text-xs sm:text-sm text-text-secondary">
                            <span class="inline-flex items-center px-2 py-1 rounded-md bg-surface-muted dark:bg-surface-dark-muted">
                                {{ assessment.subject.code }}
                            </span>
                            <span class="truncate">{{ assessment.subject.name }}</span>
                            <span class="hidden sm:inline">-</span>
                            <span class="truncate">Lesson: {{ assessment.lesson.title }}</span>
                        </div>
                    </div>
                    <button
                        v-if="cheating_logs && cheating_logs.length > 0"
                        type="button"
                        @click="openCheatingDialog"
                        class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-red-200 dark:border-red-800 bg-red-50/70 dark:bg-red-900/20 text-red-700 dark:text-red-300 hover:bg-red-100/80 dark:hover:bg-red-900/30 transition-colors text-xs sm:text-sm font-medium shrink-0"
                        aria-label="View suspicious activity log"
                    >
                        <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>Suspicious Activity</span>
                        <span class="px-1.5 py-0.5 text-[11px] font-semibold bg-red-500 text-white rounded-full">
                            {{ cheating_logs.length }}
                        </span>
                    </button>
                </div>
            </div>

            <div class="mb-5 rounded-lg border border-border-light dark:border-border-dark bg-surface dark:bg-surface-dark p-3 sm:p-4">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 sm:gap-4">
                    <div class="sm:pr-3 sm:border-r sm:border-border-light dark:sm:border-border-dark">
                        <p class="text-xs sm:text-sm text-text-secondary">Total Attempts</p>
                        <p class="text-lg sm:text-xl font-semibold text-text-primary dark:text-text-inverted">
                            {{ summary.total_attempts }}
                        </p>
                    </div>
                    <div class="sm:px-3 sm:border-r sm:border-border-light dark:sm:border-border-dark">
                        <p class="text-xs sm:text-sm text-text-secondary">Best Score</p>
                        <p class="text-lg sm:text-xl font-semibold" :class="getScoreColor(summary.best_score)">
                            {{ summary.best_score }}%
                        </p>
                        <p v-if="summary.best_attempt_no" class="text-xs text-text-secondary mt-0.5">
                            Attempt #{{ summary.best_attempt_no }}
                        </p>
                    </div>
                    <div class="sm:pl-3">
                        <p class="text-xs sm:text-sm text-text-secondary">Latest Attempt</p>
                        <p class="text-xs sm:text-sm font-medium text-text-primary dark:text-text-inverted">
                            {{ formatDate(summary.latest_attempt_date) || "N/A" }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Attempts List -->
            <div class="space-y-3 sm:space-y-4">
                <h2
                    class="text-base sm:text-xl font-semibold text-text-primary dark:text-text-inverted mb-2 sm:mb-3"
                >
                    All Attempts
                </h2>

                <!-- Empty State -->
                <div
                    v-if="attempts.length === 0"
                    class="card p-12 text-center text-text-secondary"
                >
                    <h3
                        class="text-lg font-medium text-text-primary dark:text-text-inverted mb-2"
                    >
                        No attempts
                    </h3>
                </div>

                <!-- Attempt Cards -->
                <div
                    v-for="(attempt, index) in attempts"
                    :key="attempt.id"
                    class="card p-4 sm:p-5 hover:shadow-md transition-all duration-200 border border-border-light dark:border-border-dark"
                >
                    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3 mb-3">
                        <div class="flex items-center gap-3 min-w-0">
                            <div
                                class="flex-shrink-0 w-9 h-9 rounded-full bg-accent-primary text-white flex items-center justify-center font-bold text-xs"
                            >
                                #{{ attempt.attempt_no }}
                            </div>
                            <div class="min-w-0">
                                <div class="flex items-center gap-2 mb-1">
                                    <h3
                                        class="text-sm sm:text-base font-semibold text-text-primary dark:text-text-inverted"
                                    >
                                        Attempt {{ attempt.attempt_no }}
                                    </h3>
                                    <span
                                        v-if="isBestAttempt(attempt.id)"
                                        class="px-2 py-0.5 text-[11px] font-medium bg-yellow-500 text-white rounded"
                                    >
                                        Best
                                    </span>
                                    <span
                                        v-if="isLatestAttempt(index)"
                                        class="px-2 py-0.5 text-[11px] font-medium bg-blue-500 text-white rounded"
                                    >
                                        Latest
                                    </span>
                                </div>
                                <p class="text-xs sm:text-sm text-text-secondary truncate">
                                    {{ formatDate(attempt.created_at) }}
                                </p>
                            </div>
                        </div>
                        <div
                            class="text-2xl sm:text-3xl font-semibold sm:font-bold"
                            :class="getScoreColor(attempt.score)"
                        >
                            {{ attempt.score }}%
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-2 mb-3">
                        <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs bg-surface-muted dark:bg-surface-dark-muted text-text-secondary">
                            Total: <span class="ml-1 font-semibold text-text-primary dark:text-text-inverted">{{ attempt.total_questions }}</span>
                        </span>
                        <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs bg-green-50 dark:bg-green-900/20 text-green-700 dark:text-green-300">
                            Correct: <span class="ml-1 font-semibold">{{ attempt.correct_answers }}</span>
                        </span>
                        <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-300">
                            Incorrect: <span class="ml-1 font-semibold">{{ attempt.wrong_answers }}</span>
                        </span>
                        <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300">
                            No Answer: <span class="ml-1 font-semibold">{{ attempt.no_answer }}</span>
                        </span>
                    </div>

                    <div class="mt-3 flex">
                        <Link
                            :href="
                                route('instructor.assessments.history.student.results', {
                                    assessment: assessment.id,
                                    student: student.id,
                                    attempt: attempt.id,
                                })
                            "
                            class="inline-flex items-center justify-center px-3 sm:px-4 py-2 text-xs sm:text-sm font-medium text-white bg-accent-primary rounded-lg hover:bg-accent-muted transition-colors duration-150 min-w-[110px]"
                        >
                            View Results
                        </Link>
                    </div>

                    <!-- Accordion: follow-up assessments -->
                    <div
                        v-if="hasAdaptives(attempt)"
                        class="pt-3 mt-3 border-t border-border-light dark:border-border-dark"
                    >
                        <button
                            type="button"
                            @click="toggleAccordion(attempt.id)"
                            class="flex items-center justify-between w-full px-3 py-2 rounded-lg bg-surface-muted dark:bg-surface-dark-muted border border-border-light dark:border-border-dark text-left text-xs sm:text-sm font-medium text-text-primary dark:text-text-inverted hover:bg-surface dark:hover:bg-surface-dark transition-colors"
                        >
                            <span class="flex items-center gap-2">
                                <svg
                                    class="w-4 h-4 text-text-secondary"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"
                                    />
                                </svg>
                                Follow-ups ({{
                                    countTotalAdaptives(attempt.adaptive_assessments)
                                }})
                            </span>
                            <svg
                                class="w-5 h-5 text-text-secondary transition-transform"
                                :class="{
                                    'rotate-180': isAccordionOpen(attempt.id),
                                }"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
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
                            v-show="isAccordionOpen(attempt.id)"
                            class="mt-2 px-3 py-2 bg-surface dark:bg-surface-dark rounded-lg border border-border-light dark:border-border-dark"
                        >
                            <AdaptiveTree
                                :adaptives="attempt.adaptive_assessments"
                                :formatDate="formatDate"
                                :showActions="false"
                                role="instructor"
                                :studentId="student.id"
                                :assessmentId="assessment.id"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Suspicious Activity Dialog -->
            <Modal :show="showCheatingDialog" @close="closeCheatingDialog" max-width="lg">
                <div class="p-6">
                    <div class="flex items-center gap-2 mb-4">
                        <svg class="w-6 h-6 text-red-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <h2 class="text-xl font-semibold text-text-primary dark:text-text-inverted">
                            Suspicious activity – {{ student.name }}
                        </h2>
                        <span class="px-2 py-0.5 text-xs font-semibold bg-red-500 text-white rounded-full">
                            {{ cheating_logs.length }}
                        </span>
                    </div>
                    <p class="text-sm text-text-secondary mb-4">
                        This student triggered cheating detection alerts during this assessment.
                    </p>
                    <div class="max-h-[60vh] overflow-y-auto rounded-lg border border-border-light dark:border-border-dark bg-surface dark:bg-surface-dark">
                        <div class="divide-y divide-border-light dark:divide-border-dark">
                            <div
                                v-for="log in cheating_logs"
                                :key="log.id"
                                class="flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-4 p-3 sm:p-4 hover:bg-surface-muted dark:hover:bg-surface-dark-muted transition-colors"
                            >
                                <span
                                    :class="[
                                        'px-2 py-0.5 text-xs font-semibold rounded-full whitespace-nowrap shrink-0',
                                        getEventBadgeClass(log.event_type),
                                    ]"
                                >
                                    {{ getEventLabel(log.event_type) }}
                                </span>
                                <p class="flex-1 text-sm text-text-primary dark:text-text-inverted min-w-0 break-words">
                                    {{ log.description }}
                                </p>
                                <span class="text-xs text-text-secondary whitespace-nowrap shrink-0 self-start sm:self-auto">
                                    {{ formatDate(log.created_at) }}
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="mt-6 flex justify-end">
                        <button
                            type="button"
                            @click="closeCheatingDialog"
                            class="px-4 py-2 text-sm font-medium rounded-lg bg-accent-primary text-white hover:opacity-90 transition-opacity"
                        >
                            Close
                        </button>
                    </div>
                </div>
            </Modal>
        </div>
    </InstructorLayout>
</template>
