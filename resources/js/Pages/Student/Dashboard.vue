<script setup>
import StudentLayout from "@/Layouts/StudentLayout.vue";
import { Head, Link } from "@inertiajs/vue3";

defineProps({
    stats: {
        type: Object,
        required: true,
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
            <div class="text-center py-12 text-text-secondary">
                <svg class="w-16 h-16 mx-auto mb-4 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <p>No recent activity</p>
            </div>
        </div>
    </StudentLayout>
</template>
