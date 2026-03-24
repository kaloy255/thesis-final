<script setup>
import InstructorLayout from "@/Layouts/InstructorLayout.vue";
import { Head, Link, router } from "@inertiajs/vue3";
import { computed, ref, watch, onMounted, onBeforeUnmount } from "vue";

const props = defineProps({
    subjects: Array,
    filters: Object,
});

const hasSubjects = computed(() => props.subjects && props.subjects.length > 0);
const searchQuery = ref(props.filters?.search || "");
const detailsSectionRef = ref(null);
const isDetailsStuck = ref(false);
const STICKY_RELEASE_OFFSET = 8;
let searchTimeout = null;

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

// Reactive search with debouncing
watch(searchQuery, (newValue) => {
    if (searchTimeout) {
        clearTimeout(searchTimeout);
    }

    searchTimeout = setTimeout(() => {
        router.get(
            route("instructor.subjects.index"),
            { search: newValue },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            }
        );
    }, 300);
});

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
        <Head title="My Subjects" />

        <div class="p-2.5 sm:p-4 mb-2">
            <div>
                <h1
                    class="text-lg sm:text-2xl font-semibold text-text-primary dark:text-text-inverted"
                >
                    My Subjects
                </h1>
                <p class="text-xs sm:text-sm text-text-secondary">
                    View and manage your assigned subjects and join requests.
                </p>
            </div>
        </div>

        <!-- z-20: below app headers so notification dropdown stacks on top -->
        <div ref="detailsSectionRef" class="sticky top-16 lg:top-[64px] z-20 mb-6">
            <div
                :class="[
                    'rounded-lg transition-all duration-200',
                    isDetailsStuck
                        ? 'px-3 py-2 sm:px-4 sm:py-3 bg-white/95 dark:bg-slate-900/95 border border-border-light dark:border-slate-700 shadow-md backdrop-blur-sm'
                        : '',
                ]"
            >
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
                        type="text"
                        placeholder="Search by subject name, code, or description..."
                        class="w-full pl-10 pr-4 py-2 text-sm border border-border-light dark:border-border-dark rounded-lg bg-surface dark:bg-surface-dark-muted text-text-primary dark:text-text-inverted placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/40 focus:border-indigo-500 transition-all"
                    />
                </div>
            </div>
        </div>

        <!-- Empty State -->
        <div v-if="!hasSubjects" class="p-6 text-center text-text-secondary">
            <svg
                class="mx-auto h-12 w-12 mb-4 opacity-50"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"
                />
            </svg>
            <h3
                class="text-sm font-medium text-text-primary dark:text-text-inverted"
            >
                <span v-if="searchQuery">
                    No subjects found matching "{{ searchQuery }}"
                </span>
                <span v-else> No subjects assigned </span>
            </h3>
            <p class="mt-1 text-sm">
                <span v-if="searchQuery">
                    Try adjusting your search terms.
                </span>
                <span v-else>
                    You don't have any subjects assigned yet. Contact your
                    administrator.
                </span>
            </p>
        </div>

        <!-- Subjects Grid -->
        <div
            v-else
            class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3"
        >
            <div
                v-for="subject in subjects"
                :key="subject.id"
                class="card p-6 hover:shadow-lg transition-shadow duration-200"
            >
                <!-- Subject Header -->
                <div class="flex items-start justify-between mb-4">
                    <div class="flex-1">
                        <h3
                            class="text-lg font-semibold text-text-primary dark:text-text-inverted"
                        >
                            {{ subject.name }}
                        </h3>
                        <p class="text-sm text-text-secondary mt-1">
                            {{ subject.code }}
                        </p>
                    </div>

                    <!-- Pending Requests Badge -->
                    <span
                        v-if="subject.pending_requests_count > 0"
                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200"
                    >
                        {{ subject.pending_requests_count }} pending
                    </span>
                </div>

                <!-- Statistics -->
                <div class="flex items-center gap-4 mb-4">
                    <div class="flex items-center gap-2">
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
                                d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"
                            />
                        </svg>
                        <span class="text-sm text-text-secondary">
                            <span
                                class="font-medium text-text-primary dark:text-text-inverted"
                            >
                                {{ subject.enrolled_students_count || 0 }}
                            </span>
                            <span class="ml-1">
                                {{
                                    subject.enrolled_students_count === 1
                                        ? "student"
                                        : "students"
                                }}
                                enrolled
                            </span>
                        </span>
                    </div>
                </div>

                <!-- Subject Description -->
                <p
                    v-if="subject.description"
                    class="text-sm text-text-secondary mb-4 line-clamp-2"
                >
                    {{ subject.description }}
                </p>

                <!-- Actions -->
                <div class="flex gap-2 mt-4">
                    <Link
                        :href="
                            route('instructor.subjects.requests', subject.id)
                        "
                        class="btn-primary w-full flex items-center justify-center gap-2"
                    >
                        <svg
                            class="w-4 h-4"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"
                            />
                        </svg>
                        View Requests
                    </Link>
                </div>
            </div>
        </div>
    </InstructorLayout>
</template>

<style scoped>
.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>
