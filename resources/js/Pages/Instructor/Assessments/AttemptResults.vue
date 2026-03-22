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
            <div class="mb-6 flex items-center justify-between">
                <Link
                    :href="route('instructor.assessments.history.student', [assessment.id, student.id])"
                    class="inline-flex items-center text-sm font-medium text-text-secondary hover:text-text-primary transition-colors"
                >
                    <svg
                        class="w-5 h-5 mr-1 text-text-secondary"
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
            <div class="card p-8 mb-6 relative overflow-hidden">
                <div
                    class="absolute top-0 right-0 w-32 h-32 transform translate-x-16 -translate-y-16"
                >
                    <div
                        class="w-full h-full rounded-full opacity-10"
                        :class="getScoreBgColor(results.score)"
                    ></div>
                </div>

                <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-6">
                    <div>
                        <h1 class="text-3xl font-bold text-text-primary dark:text-text-inverted mb-2">
                            Attempt Results
                        </h1>
                        <p class="text-text-secondary text-lg">
                            Assessment: <span class="font-semibold">{{ assessment.title }}</span>
                        </p>
                        <p class="text-text-secondary">
                            Student: <span class="font-semibold">{{ student.name }}</span>
                        </p>
                        <p class="text-text-secondary text-sm mt-1">
                            Attempt #{{ attempt.attempt_no }} • {{ formatDate(attempt.created_at) }}
                        </p>
                    </div>

                    <div class="flex flex-col items-center">
                        <div
                            class="text-5xl font-extrabold"
                            :class="getScoreColor(results.score)"
                        >
                            {{ results.score }}%
                        </div>
                        <div class="text-text-secondary font-medium mt-1">
                            Final Score
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-8 pt-8 border-t border-border-light dark:border-border-dark">
                    <div class="text-center">
                        <div class="text-2xl font-bold text-text-primary dark:text-text-inverted">
                            {{ results.total_questions }}
                        </div>
                        <div class="text-sm text-text-secondary">Questions</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-green-600 dark:text-green-400">
                            {{ results.correct_answers }}
                        </div>
                        <div class="text-sm text-text-secondary">Correct</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-red-600 dark:text-red-400">
                            {{ results.wrong_answers }}
                        </div>
                        <div class="text-sm text-text-secondary">Incorrect</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-gray-400 dark:text-gray-500">
                            {{ results.no_answer }}
                        </div>
                        <div class="text-sm text-text-secondary">Skipped</div>
                    </div>
                </div>
            </div>

            <!-- Detailed Answers -->
            <div class="space-y-8">
                <template v-for="(items, type) in groupedItems" :key="type">
                    <div v-if="items.length > 0">
                        <h2 class="text-xl font-bold text-text-primary dark:text-text-inverted mb-4 flex items-center gap-2">
                            <span class="w-8 h-8 rounded-lg bg-surface flex items-center justify-center text-sm border border-border-light dark:border-border-dark">
                                {{ items.length }}
                            </span>
                            {{ getTypeLabel(type) }}
                        </h2>

                        <div class="space-y-4">
                            <div
                                v-for="(item, index) in items"
                                :key="item.id"
                                class="card p-6 border-l-4"
                                :class="
                                    item.is_correct
                                        ? 'border-l-green-500'
                                        : item.student_answer
                                        ? 'border-l-red-500'
                                        : 'border-l-gray-400'
                                "
                            >
                                <div class="flex items-start gap-4">
                                    <div class="flex-shrink-0 mt-1">
                                        <div
                                            v-if="item.is_correct"
                                            class="w-6 h-6 rounded-full bg-green-100 dark:bg-green-900/30 text-green-600 dark:text-green-400 flex items-center justify-center"
                                        >
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                                            </svg>
                                        </div>
                                        <div
                                            v-else-if="item.student_answer"
                                            class="w-6 h-6 rounded-full bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400 flex items-center justify-center"
                                        >
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                        </div>
                                        <div
                                            v-else
                                            class="w-6 h-6 rounded-full bg-gray-100 dark:bg-gray-800 text-gray-500 flex items-center justify-center"
                                        >
                                            <span class="text-xs font-bold">-</span>
                                        </div>
                                    </div>
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2 mb-2">
                                            <span class="text-sm font-semibold text-text-secondary">Question {{ index + 1 }}</span>
                                        </div>
                                        <h3 class="text-lg font-medium text-text-primary dark:text-text-inverted mb-4">
                                            {{ item.question }}
                                        </h3>

                                        <div class="grid gap-3 sm:grid-cols-2">
                                            <div class="p-3 rounded-lg bg-red-50 dark:bg-red-900/10 border border-red-100 dark:border-red-900/30" v-if="!item.is_correct">
                                                <div class="text-xs font-semibold text-red-600 dark:text-red-400 mb-1 uppercase tracking-wider">
                                                    {{ item.student_answer ? 'Student Answer' : 'Skipped' }}
                                                </div>
                                                <div class="text-red-700 dark:text-red-300 font-medium break-words">
                                                    {{ item.student_answer || 'No answer provided' }}
                                                </div>
                                            </div>
                                            
                                            <div class="p-3 rounded-lg bg-green-50 dark:bg-green-900/10 border border-green-100 dark:border-green-900/30">
                                                <div class="text-xs font-semibold text-green-600 dark:text-green-400 mb-1 uppercase tracking-wider">
                                                    Correct Answer
                                                </div>
                                                <div class="text-green-700 dark:text-green-300 font-medium break-words">
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
