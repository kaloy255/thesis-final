<script setup>
import { ref, onMounted, onBeforeUnmount } from "vue";
import InstructorLayout from "@/Layouts/InstructorLayout.vue";
import Modal from "@/Components/Modal.vue";
import { Head, Link, router } from "@inertiajs/vue3";

const props = defineProps({
    assessment: Object,
    summary: Object,
    students: Array,
    most_common_mistakes: {
        type: Array,
        default: () => [],
    },
    sections: {
        type: Array,
        default: () => [],
    },
    filters: {
        type: Object,
        default: () => ({ search: null, section: null }),
    },
});

const showMistakesModal = ref(false);
const searchQuery = ref(props.filters?.search || "");
const sectionFilter = ref(props.filters?.section || "all");
const detailsSectionRef = ref(null);
const isDetailsStuck = ref(false);
const STICKY_RELEASE_OFFSET = 8;
let searchTimeout = null;

const applyFilters = () => {
    router.get(
        route("instructor.assessments.history", props.assessment.id),
        {
            search: searchQuery.value || null,
            section: sectionFilter.value === "all" ? null : sectionFilter.value,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        }
    );
};

const handleSearch = () => {
    if (searchTimeout) clearTimeout(searchTimeout);
    searchTimeout = setTimeout(applyFilters, 300);
};

const handleSectionFilter = () => {
    applyFilters();
};

const activeSectionName = () => {
    if (!props.filters?.section) return null;
    const s = props.sections?.find(
        (sec) => String(sec.id) === String(props.filters.section)
    );
    return s?.name ?? null;
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

const getMasteryPercent = (student) => {
    const value = Number(student?.mastery_percent ?? 0);
    if (Number.isNaN(value)) return 0;
    return Math.max(0, Math.min(100, value));
};

const getMasteryColor = (student) => {
    const percent = getMasteryPercent(student);
    if (percent > 75) return "#05ff00"; // high
    if (percent > 50) return "#d79f00"; // middle
    return "#ff0000"; // low
};

const getMasteryRingStyle = (student) => {
    const percent = getMasteryPercent(student);
    const color = getMasteryColor(student);
    return {
        background: `conic-gradient(${color} ${percent}%, #e5e7eb ${percent}% 100%)`,
    };
};

const updateStickyState = () => {
    const el = detailsSectionRef.value;
    if (!el) return;
    const stickyTop = Number.parseFloat(window.getComputedStyle(el).top || "0") || 0;
    const rectTop = el.getBoundingClientRect().top;

    if (isDetailsStuck.value) {
        if (rectTop > stickyTop + STICKY_RELEASE_OFFSET) {
            isDetailsStuck.value = false;
        }
    } else if (rectTop <= stickyTop + 0.5) {
        isDetailsStuck.value = true;
    }
};

onMounted(() => {
    updateStickyState();
    window.addEventListener("scroll", updateStickyState, { passive: true });
});

onBeforeUnmount(() => {
    if (searchTimeout) clearTimeout(searchTimeout);
    window.removeEventListener("scroll", updateStickyState);
});
</script>

<template>
    <InstructorLayout>
        <Head :title="`History - ${assessment.title}`" />

        <div class="max-w-4xl mx-auto">
            <div class="mb-4 sm:mb-5">
                <div class="flex flex-col gap-2">
                    <h1 class="text-lg sm:text-2xl font-semibold text-text-primary dark:text-text-inverted">
                        Assessment History
                    </h1>
                    <p class="text-sm sm:text-base font-medium text-text-primary dark:text-text-inverted truncate">
                        {{ assessment.title }}
                    </p>
                    <div class="flex flex-wrap items-center gap-2 text-xs sm:text-sm text-text-secondary">
                        <span class="inline-flex items-center px-2 py-1 rounded-md bg-surface-muted dark:bg-surface-dark-muted">
                            {{ assessment.subject.code }}
                        </span>
                        <span class="truncate">{{ assessment.subject.name }}</span>
                        <span class="hidden sm:inline">-</span>
                        <span class="truncate">Lesson: {{ assessment.lesson.title }}</span>
                    </div>
                </div>
            </div>

            <div class="mb-4 sm:mb-5 rounded-lg border border-border-light dark:border-border-dark bg-surface dark:bg-surface-dark p-3 sm:p-4">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 sm:gap-4">
                    <div class="sm:pr-3 sm:border-r sm:border-border-light dark:sm:border-border-dark">
                        <p class="text-xs sm:text-sm text-text-secondary">Students Who Took</p>
                        <p class="text-lg sm:text-xl font-semibold text-text-primary dark:text-text-inverted">
                            {{ summary.total_students }}
                        </p>
                    </div>
                    <div class="sm:px-3 sm:border-r sm:border-border-light dark:sm:border-border-dark">
                        <p class="text-xs sm:text-sm text-text-secondary">Best Score</p>
                        <p class="text-lg sm:text-xl font-semibold" :class="getScoreColor(summary.best_score)">
                            {{ summary.best_score }}%
                        </p>
                        <p v-if="summary.best_student_name" class="text-xs text-text-secondary mt-0.5 truncate">
                            {{ summary.best_student_name }}
                        </p>
                    </div>
                    <button
                        type="button"
                        @click="showMistakesModal = true"
                        class="sm:pl-3 text-left rounded-lg px-2 py-1 hover:bg-surface-muted dark:hover:bg-surface-dark-muted transition-colors"
                    >
                        <p class="text-xs sm:text-sm text-text-secondary">Most Common Mistakes</p>
                        <p class="text-lg sm:text-xl font-semibold text-text-primary dark:text-text-inverted">
                            {{
                                most_common_mistakes.length > 0
                                    ? most_common_mistakes[0].mistake_count + " students"
                                    : "None"
                            }}
                        </p>
                        <p class="text-xs text-text-secondary mt-0.5">
                            First-take misses
                            <span v-if="activeSectionName()">({{ activeSectionName() }})</span>
                        </p>
                    </button>
                </div>
            </div>

            <div ref="detailsSectionRef" class="sticky top-16 lg:top-[64px] z-40 mb-5">
                <div
                    :class="[
                        'rounded-lg transition-all duration-200',
                        isDetailsStuck
                            ? 'px-3 py-2 sm:px-4 sm:py-3 bg-white/95 dark:bg-slate-900/95 border border-border-light dark:border-slate-700 shadow-md backdrop-blur-sm'
                            : '',
                    ]"
                >
                    <div class="flex flex-col sm:flex-row gap-2 sm:gap-3">
                        <div class="w-full sm:w-48">
                            <select
                                v-model="sectionFilter"
                                @change="handleSectionFilter"
                                class="block w-full px-3 py-2 border border-border-light dark:border-border-dark rounded-lg bg-surface dark:bg-surface-dark-muted text-text-primary dark:text-text-inverted focus:outline-none focus:ring-2 focus:ring-indigo-500/40 text-sm"
                            >
                                <option value="all">All Sections</option>
                                <option
                                    v-for="section in sections"
                                    :key="section.id"
                                    :value="section.id"
                                >
                                    {{ section.name }}
                                </option>
                            </select>
                        </div>
                        <div class="relative w-full sm:max-w-md">
                            <div
                                class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none"
                            >
                                <svg
                                    class="h-4 w-4 text-text-secondary"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
                                    />
                                </svg>
                            </div>
                            <input
                                v-model="searchQuery"
                                @input="handleSearch"
                                type="text"
                                placeholder="Search by student name..."
                                class="block w-full pl-10 pr-3 py-2 border border-border-light dark:border-border-dark rounded-lg bg-surface dark:bg-surface-dark-muted text-text-primary dark:text-text-inverted placeholder-gray-500 dark:placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/40 text-sm"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Students List -->
            <div class="space-y-3 sm:space-y-4">
                <h2
                    class="text-base sm:text-xl font-semibold text-text-primary dark:text-text-inverted mb-2 sm:mb-3"
                >
                    Students
                </h2>

                <!-- Empty State -->
                <div
                    v-if="students.length === 0"
                    class="card p-12 text-center text-text-secondary"
                >
                    <svg
                        class="mx-auto h-16 w-16 mb-4 opacity-50"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"
                        />
                    </svg>
                    <h3
                        class="text-lg font-medium text-text-primary dark:text-text-inverted mb-2"
                    >
                        No students yet
                    </h3>
                    <p class="text-sm mb-4">
                        No students have taken this assessment yet.
                    </p>
                </div>

                <!-- Student Cards -->
                <div
                    v-for="student in students"
                    :key="student.student_id"
                    class="card p-4 sm:p-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-4 hover:shadow-md transition-all duration-200"
                >
                    <div class="flex items-center gap-3 sm:gap-4 min-w-0">
                        <div
                            class="flex-shrink-0 w-10 h-10 sm:w-11 sm:h-11 rounded-full bg-accent-primary/20 dark:bg-accent-primary/30 text-accent-primary flex items-center justify-center font-bold text-sm sm:text-base"
                        >
                            {{ student.student_name?.charAt(0)?.toUpperCase() || "?" }}
                        </div>
                        <div class="min-w-0">
                            <h3
                                class="text-sm sm:text-base font-semibold text-text-primary dark:text-text-inverted truncate"
                            >
                                {{ student.student_name }}
                            </h3>
                            <p class="text-xs sm:text-sm text-text-secondary">
                                {{ student.attempt_count }}
                                {{ student.attempt_count === 1 ? "attempt" : "attempts" }}
                                · Best: {{ student.best_score }}%
                                <span v-if="student.section_name" class="ml-1">
                                    · {{ student.section_name }}
                                </span>
                            </p>
                            <p
                                v-if="student.latest_attempt_date"
                                class="text-xs text-text-secondary mt-0.5"
                            >
                                Latest: {{ formatDate(student.latest_attempt_date) }}
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center justify-between sm:justify-end gap-3 sm:gap-4 w-full sm:w-auto">
                        <div class="flex items-center gap-2">
                            <div
                                class="relative w-12 h-12 sm:w-14 sm:h-14 rounded-full p-[4px]"
                                :style="getMasteryRingStyle(student)"
                            >
                                <div
                                    class="w-full h-full rounded-full bg-white dark:bg-gray-900 flex items-center justify-center"
                                >
                                    <span
                                        class="text-[11px] font-bold text-text-primary dark:text-text-inverted"
                                    >
                                        {{ Math.round(getMasteryPercent(student)) }}%
                                    </span>
                                </div>
                            </div>
                            <div class="text-[11px] sm:text-xs leading-tight">
                                <div
                                    class="font-semibold text-text-primary dark:text-text-inverted"
                                >
                                    Mastery
                                </div>
                            </div>
                        </div>
                        <Link
                            :href="
                                route('instructor.assessments.history.student', [
                                    assessment.id,
                                    student.student_id,
                                ])
                            "
                            class="inline-flex items-center justify-center px-3 sm:px-4 py-2 text-xs sm:text-sm font-medium text-white bg-accent-primary rounded-lg hover:bg-accent-muted transition-colors duration-150 min-w-[84px]"
                        >
                            View
                        </Link>
                    </div>
                </div>
            </div>
        </div>

        <!-- Most Common Mistakes Modal -->
        <Modal :show="showMistakesModal" @close="showMistakesModal = false" max-width="lg">
            <div class="p-6">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h2 class="text-xl font-semibold text-text-primary dark:text-text-inverted">
                            Question Mistakes (First Attempt)
                        </h2>
                        <p class="text-sm text-text-secondary mt-1">
                            Ranked by how many students got it wrong
                            <span
                                v-if="activeSectionName()"
                                class="font-medium text-accent-primary"
                            >
                                · Filtered by: {{ activeSectionName() }}
                            </span>
                        </p>
                    </div>
                    <button
                        type="button"
                        @click="showMistakesModal = false"
                        class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300 transition-colors"
                    >
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <div
                    v-if="most_common_mistakes.length === 0"
                    class="py-12 text-center text-text-secondary"
                >
                    <p>
                        {{
                            activeSectionName()
                                ? `No mistakes recorded on first attempts for ${activeSectionName()}.`
                                : "No mistakes recorded on first attempts."
                        }}
                    </p>
                </div>
                <div
                    v-else
                    class="max-h-96 overflow-y-auto space-y-3"
                >
                    <div
                        v-for="item in most_common_mistakes"
                        :key="item.item_id"
                        class="p-3 rounded-lg border border-border-light dark:border-border-dark bg-surface dark:bg-surface-dark-muted"
                    >
                        <div class="flex items-start gap-4">
                            <div
                                class="flex-shrink-0 w-7 h-7 rounded-full bg-surface-muted dark:bg-surface-dark text-text-primary dark:text-text-inverted flex items-center justify-center font-semibold text-xs"
                            >
                                {{ item.rank }}
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm text-text-primary dark:text-text-inverted">
                                    {{ item.question }}
                                </p>
                                <p class="text-xs text-text-secondary font-medium mt-1">
                                    {{ item.mistake_count }} {{ item.mistake_count === 1 ? "student" : "students" }} got this wrong
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </Modal>
    </InstructorLayout>
</template>
