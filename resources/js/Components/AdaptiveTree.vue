<script setup>
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
        default: 'student', // 'student' or 'instructor'
    },
    studentId: {
        type: Number,
        default: null,
    },
    assessmentId: {
        type: Number,
        default: null,
    },
});
</script>

<template>
    <div class="space-y-4">
        <div
            v-for="adaptive in adaptives"
            :key="adaptive.id"
            class="py-3"
            :class="{ 'border-t border-border-light dark:border-border-dark': depth === 0 }"
        >
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <!-- Info Section -->
                <div>
                    <div class="flex items-center gap-2">
                        <div class="text-sm font-medium text-text-primary dark:text-text-inverted">
                            {{ adaptive.title }}
                        </div>
                        <span
                            v-if="adaptive.score !== null"
                            class="px-2 py-0.5 text-[10px] font-bold rounded-full"
                            :class="
                                adaptive.score >= 75 ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' :
                                adaptive.score >= 50 ? 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400' :
                                'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'
                            "
                        >
                            {{ adaptive.score }}%
                        </span>
                    </div>
                    <div class="flex items-center gap-2 text-xs text-text-secondary mt-1">
                        {{ formatDate(adaptive.created_at) }}
                    </div>
                </div>

                <!-- Actions Section -->
                <div v-if="showActions && role === 'student'" class="flex flex-wrap items-center gap-2">
                    <Link
                        :href="route('student.assessments.show', adaptive.id)"
                        class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-lg bg-accent-primary text-white hover:bg-accent-muted transition-colors"
                    >
                        Take Assessment
                    </Link>
                    <Link
                        :href="route('student.assessments.history', adaptive.id)"
                        class="inline-flex items-center px-3 py-1.5 text-xs font-medium bg-surface dark:bg-surface-dark border border-border-light dark:border-border-dark text-text-primary dark:text-text-inverted hover:bg-surface-muted dark:hover:bg-surface-dark-muted rounded-lg transition-colors"
                    >
                        View History
                    </Link>
                </div>

                <div v-if="role === 'instructor' && adaptive.latest_attempt_id" class="flex flex-wrap items-center gap-2">
                    <Link
                        :href="route('instructor.assessments.history.student.results', {
                            assessment: adaptive.id,
                            student: studentId,
                            attempt: adaptive.latest_attempt_id
                        })"
                        class="inline-flex items-center px-3 py-1.5 text-xs font-medium bg-surface dark:bg-surface-dark border border-border-light dark:border-border-dark text-text-primary dark:text-text-inverted hover:bg-surface-muted dark:hover:bg-surface-dark-muted rounded-lg transition-colors"
                    >
                        View Results
                    </Link>
                </div>
            </div>

            <!-- Recursive Children -->
            <div
                v-if="adaptive.children && adaptive.children.length > 0"
                class="mt-3 pl-4 border-l-2 border-accent-primary/20"
            >
                <div class="text-xs font-semibold text-text-secondary uppercase tracking-wider mb-2">
                    Nested Adaptives
                </div>
                <AdaptiveTree
                    :adaptives="adaptive.children"
                    :depth="depth + 1"
                    :formatDate="formatDate"
                    :showActions="showActions"
                    :role="role"
                    :studentId="studentId"
                    :assessmentId="assessmentId"
                />
            </div>
        </div>
    </div>
</template>

<script>
// Required for self-referencing recursive components in Vue <script setup> (prior to Vue 3.3 in some cases, though standard now, it's safer to explicitly name it if needed, but <script setup> handles it automatically if file is named AdaptiveTree.vue). We'll leave it simple.
export default {
    name: 'AdaptiveTree',
};
</script>
