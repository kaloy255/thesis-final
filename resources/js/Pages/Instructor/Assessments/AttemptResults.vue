<script setup>
import InstructorLayout from "@/Layouts/InstructorLayout.vue";
import { Head, Link } from "@inertiajs/vue3";
import { computed } from "vue";

const props = defineProps({
    assessment: Object,
    student: Object,
    attempt: Object,
    results: Object,
    items: Array,
});

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
    if (score >= 75) return "bg-green-500";
    if (score >= 50) return "bg-yellow-500";
    return "bg-red-500";
};

// Organize items by type
const groupedItems = computed(() => {
    const groups = {
        multiple_choice: [],
        identification: [],
        true_or_false: [],
    };

    props.items.forEach((item) => {
        if (groups[item.type]) {
            groups[item.type].push(item);
        }
    });

    return groups;
});

const getTypeLabel = (type) => {
    const labels = {
        multiple_choice: "Multiple Choice",
        identification: "Identification",
        true_or_false: "True or False",
    };
    return labels[type] || type;
};
</script>

<template>
    <InstructorLayout>
        <Head :title="`Results - ${assessment.title}`" />

        <div class="max-w-4xl mx-auto">
            <!-- Header section -->
            <div class="mb-4 flex items-center justify-between">
                <Link
                    :href="route('instructor.assessments.history.student', [assessment.id, student.id])"
                    class="inline-flex items-center text-xs sm:text-sm font-medium text-text-secondary hover:text-text-primary transition-colors"
                >
                    <svg
                        class="w-4 h-4 mr-1 text-text-secondary"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M10 19l-7-7m0 0l7-7m-7 7h18"
                        />
                    </svg>
                    Back to History
                </Link>
            </div>

            <!-- Score overview -->
            <div class="card p-4 sm:p-6 mb-5 border border-border-light dark:border-border-dark">
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 sm:gap-6">
                    <div>
                        <h1 class="text-lg sm:text-2xl font-semibold text-text-primary dark:text-text-inverted mb-1">
                            Attempt Results
                        </h1>
                        <p class="text-text-secondary text-sm sm:text-base">
                            Assessment: <span class="font-semibold">{{ assessment.title }}</span>
                        </p>
                        <p class="text-text-secondary text-sm sm:text-base">
                            Student: <span class="font-semibold">{{ student.name }}</span>
                        </p>
                        <p class="text-text-secondary text-xs sm:text-sm mt-1">
                            Attempt #{{ attempt.attempt_no }} • {{ formatDate(attempt.created_at) }}
                        </p>
                    </div>

                    <div class="flex flex-col items-center">
                        <div
                            class="text-3xl sm:text-5xl font-bold sm:font-extrabold"
                            :class="getScoreColor(results.score)"
                        >
                            {{ results.score }}%
                        </div>
                        <div class="text-text-secondary text-xs sm:text-sm font-medium mt-1">
                            Final Score
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-2 md:grid-cols-4 gap-2 sm:gap-3 mt-4 sm:mt-6 pt-4 sm:pt-5 border-t border-border-light dark:border-border-dark">
                    <div class="rounded-lg border border-border-light dark:border-border-dark bg-surface dark:bg-surface-dark p-2.5 sm:p-3 text-center">
                        <div class="text-lg sm:text-2xl font-semibold text-text-primary dark:text-text-inverted">
                            {{ results.total_questions }}
                        </div>
                        <div class="text-xs sm:text-sm text-text-secondary">Questions</div>
                    </div>
                    <div class="rounded-lg border border-green-100 dark:border-green-900/40 bg-green-50/60 dark:bg-green-900/10 p-2.5 sm:p-3 text-center">
                        <div class="text-lg sm:text-2xl font-semibold text-green-600 dark:text-green-400">
                            {{ results.correct_answers }}
                        </div>
                        <div class="text-xs sm:text-sm text-text-secondary">Correct</div>
                    </div>
                    <div class="rounded-lg border border-red-100 dark:border-red-900/40 bg-red-50/60 dark:bg-red-900/10 p-2.5 sm:p-3 text-center">
                        <div class="text-lg sm:text-2xl font-semibold text-red-600 dark:text-red-400">
                            {{ results.wrong_answers }}
                        </div>
                        <div class="text-xs sm:text-sm text-text-secondary">Incorrect</div>
                    </div>
                    <div class="rounded-lg border border-border-light dark:border-border-dark bg-surface-muted dark:bg-surface-dark-muted p-2.5 sm:p-3 text-center">
                        <div class="text-lg sm:text-2xl font-semibold text-text-secondary">
                            {{ results.no_answer }}
                        </div>
                        <div class="text-xs sm:text-sm text-text-secondary">Skipped</div>
                    </div>
                </div>
            </div>

            <!-- Detailed Answers -->
            <div class="space-y-6 sm:space-y-8">
                <template v-for="(items, type) in groupedItems" :key="type">
                    <div v-if="items.length > 0">
                        <h2 class="text-base sm:text-lg font-semibold text-text-primary dark:text-text-inverted mb-3 flex items-center gap-2">
                            <span class="w-7 h-7 rounded-md bg-surface dark:bg-surface-dark flex items-center justify-center text-xs sm:text-sm border border-border-light dark:border-border-dark">
                                {{ items.length }}
                            </span>
                            {{ getTypeLabel(type) }}
                        </h2>

                        <div class="space-y-4">
                            <div
                                v-for="(item, index) in items"
                                :key="item.id"
                                class="card p-4 sm:p-5 border border-border-light dark:border-border-dark"
                                :class="
                                    item.is_correct
                                        ? 'border-l-2 border-l-green-500'
                                        : item.student_answer
                                        ? 'border-l-2 border-l-red-500'
                                        : 'border-l-2 border-l-border-light dark:border-l-border-dark'
                                "
                            >
                                <div class="flex items-start gap-3 sm:gap-4">
                                    <div class="flex-shrink-0 mt-0.5">
                                        <div
                                            v-if="item.is_correct"
                                            class="w-5 h-5 rounded-full bg-green-100 dark:bg-green-900/30 text-green-600 dark:text-green-400 flex items-center justify-center"
                                        >
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                                            </svg>
                                        </div>
                                        <div
                                            v-else-if="item.student_answer"
                                            class="w-5 h-5 rounded-full bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400 flex items-center justify-center"
                                        >
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                        </div>
                                        <div
                                            v-else
                                            class="w-5 h-5 rounded-full bg-surface-muted dark:bg-surface-dark-muted text-text-secondary flex items-center justify-center"
                                        >
                                            <span class="text-xs font-bold">-</span>
                                        </div>
                                    </div>
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2 mb-2">
                                            <span class="text-xs sm:text-sm font-semibold text-text-secondary">Question {{ index + 1 }}</span>
                                        </div>
                                        <h3 class="text-sm sm:text-base font-medium text-text-primary dark:text-text-inverted mb-3 leading-relaxed break-words">
                                            {{ item.question }}
                                        </h3>

                                        <div class="grid gap-2 sm:gap-3 sm:grid-cols-2">
                                            <div class="p-3 rounded-lg bg-red-50/70 dark:bg-red-900/10 border border-red-100 dark:border-red-900/30" v-if="!item.is_correct">
                                                <div class="text-xs font-semibold text-red-600 dark:text-red-400 mb-1 uppercase tracking-wider">
                                                    {{ item.student_answer ? 'Student Answer' : 'Skipped' }}
                                                </div>
                                                <div class="text-sm text-red-700 dark:text-red-300 font-medium break-words leading-relaxed">
                                                    {{ item.student_answer || 'No answer provided' }}
                                                </div>
                                            </div>
                                            
                                            <div class="p-3 rounded-lg bg-green-50/70 dark:bg-green-900/10 border border-green-100 dark:border-green-900/30">
                                                <div class="text-xs font-semibold text-green-600 dark:text-green-400 mb-1 uppercase tracking-wider">
                                                    Correct Answer
                                                </div>
                                                <div class="text-sm text-green-700 dark:text-green-300 font-medium break-words leading-relaxed">
                                                    {{ item.correct_answer }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </InstructorLayout>
</template>
