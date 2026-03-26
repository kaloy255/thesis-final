<script setup>
import StudentLayout from "@/Layouts/StudentLayout.vue";
import { Head, Link } from "@inertiajs/vue3";

defineProps({
    stats: {
        type: Object,
        required: true,
    },
    recentActivities: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>
    <StudentLayout>
        <Head title="Student Dashboard" />

        <div class="mb-6">
            <h1 class="text-2xl font-bold text-text-primary dark:text-text-inverted">Dashboard</h1>
            <p class="text-text-secondary">Welcome back! Here's your overview.</p>
        </div>

        <!-- Quick Stats -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <Link
                :href="route('student.assessments.index')"
                class="card p-6 hover:shadow-xl hover:border-l-4 hover:border-blue-500 transition-all duration-300 cursor-pointer focus:outline-none focus:ring-2 focus:ring-blue-500/40"
            >
                <div class="text-sm text-text-secondary mb-2">Assessments</div>
                <div class="text-3xl font-bold text-text-primary dark:text-text-inverted">
                    {{ stats.assessments_count }}
                </div>
                <p class="text-xs text-text-secondary mt-2">Total assessments</p>
            </Link>

            <Link
                :href="route('student.subjects.index')"
                class="card p-6 hover:shadow-xl hover:border-l-4 hover:border-blue-500 transition-all duration-300 cursor-pointer focus:outline-none focus:ring-2 focus:ring-blue-500/40"
            >
                <div class="text-sm text-text-secondary mb-2">Joined Subjects</div>
                <div class="text-3xl font-bold text-text-primary dark:text-text-inverted">
                    {{ stats.joined_subjects_count }}
                </div>
                <p class="text-xs text-text-secondary mt-2">Total joined subjects</p>
            </Link>
        </div>

        <!-- Recent Activity -->
        <div class="card p-6 hover:shadow-xl hover:border-l-4 hover:border-indigo-500 transition-all duration-300">
            <h2 class="text-lg font-semibold text-text-primary dark:text-text-inverted mb-4">
                Recent Activity
            </h2>
            <div v-if="!recentActivities.length" class="text-center py-12 text-text-secondary">
                <svg class="w-16 h-16 mx-auto mb-4 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <p>No recent activity</p>
            </div>
            <div v-else class="space-y-2">
                <div
                    v-for="activity in recentActivities"
                    :key="activity.id"
                    class="flex items-start justify-between gap-4 rounded-lg border border-border-light dark:border-border-dark bg-surface dark:bg-surface-dark-muted px-4 py-3"
                >
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <p class="text-sm font-medium text-text-primary dark:text-text-inverted truncate">
                                {{ activity.title }}
                            </p>
                            <span
                                v-if="activity.type === 'notification' && !activity.read_at"
                                class="inline-flex h-2 w-2 rounded-full bg-indigo-500"
                                aria-hidden="true"
                            />
                        </div>
                        <p class="text-xs text-text-secondary mt-0.5 truncate">
                            {{ activity.description }}
                        </p>
                    </div>

                    <div class="shrink-0 flex items-center gap-3">
                        <span class="text-[11px] text-text-secondary whitespace-nowrap">
                            {{ new Date(activity.occurred_at).toLocaleString() }}
                        </span>
                        <Link
                            v-if="activity.url"
                            :href="activity.url"
                            class="text-sm font-medium text-indigo-600 hover:text-indigo-700 dark:text-indigo-400 dark:hover:text-indigo-300 whitespace-nowrap"
                        >
                            View
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </StudentLayout>
</template>
