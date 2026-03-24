<script setup>
import StudentLayout from "@/Layouts/StudentLayout.vue";
import { Head, router, usePage } from "@inertiajs/vue3";
import { computed, ref, watch, onMounted, onBeforeUnmount } from "vue";
import Badge from "@/Components/Badge.vue";
import { useToast } from "@/Stores/useToast";

const page = usePage();
const { success, error } = useToast();

const props = defineProps({
    subjects: Array,
    filters: Object,
});

const search = ref(props.filters?.search || "");
const processingJoin = ref(null);
const detailsSectionRef = ref(null);
const isDetailsStuck = ref(false);
const STICKY_RELEASE_OFFSET = 8;
let searchTimeout = null;

const flash = computed(() => page.props.flash || {});

// Watch for flash messages
watch(
    () => page.props.flash,
    (flash) => {
        if (flash?.message) {
            if (flash.type === "success") {
                success(flash.message);
            } else if (flash.type === "error") {
                error(flash.message);
            }
        }
    },
    { immediate: true }
);

const hasSubjects = computed(() => props.subjects && props.subjects.length > 0);

const getStatusBadge = (status) => {
    switch (status) {
        case "approved":
            return { variant: "success", text: "Enrolled" };
        case "pending":
            return { variant: "primary", text: "Pending Approval" };
        case "declined":
            return { variant: "danger", text: "Declined" };
        default:
            return { variant: "neutral", text: "Available" };
    }
};

const canJoin = (subject) => {
    return (
        subject.status === "not_joined" ||
        subject.status === "declined" ||
        (subject.status === "pending" && subject.instructors.length > 0)
    );
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

const joinSubject = (subject, professorId) => {
    if (processingJoin.value) return;

    if (subject.status === "pending") {
        error("You already have a pending request for this subject.");
        return;
    }

    if (subject.status === "approved") {
        error("You are already enrolled in this subject.");
        return;
    }

    if (!confirm(`Join ${subject.name} with this instructor?`)) {
        return;
    }

    processingJoin.value = `${subject.id}-${professorId}`;

    router.post(
        route("student.subjects.join"),
        {
            subject_id: subject.id,
            professor_id: professorId,
        },
        {
            preserveScroll: true,
            onFinish: () => {
                processingJoin.value = null;
            },
        }
    );
};

const isProcessing = (subjectId, professorId) => {
    return processingJoin.value === `${subjectId}-${professorId}`;
};

// Subtle accent for instructor row (border-left) – keeps text readable
const getInstructorDepartmentBorder = (departmentName) => {
    switch ((departmentName || "").trim()) {
        case "Hospitality Management":
            return "border-l-orange-500";
        case "Criminology":
            return "border-l-slate-500";
        case "Nursing":
            return "border-l-rose-500";
        case "Business Administration":
            return "border-l-emerald-500";
        case "Education":
            return "border-l-sky-500";
        case "Computer Science":
            return "border-l-indigo-500";
        default:
            return "border-l-border-light dark:border-l-border-dark";
    }
};

const getInitials = (name) => {
    if (!name || !name.trim()) return "?";
    const parts = name.trim().split(/\s+/);
    if (parts.length >= 2) {
        return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
    }
    return name.slice(0, 2).toUpperCase();
};

watch(search, (newValue) => {
    if (searchTimeout) clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        router.get(
            route("student.subjects.index"),
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
    <StudentLayout>
        <Head title="Join Subjects" />

        <div class="p-2.5 sm:p-4 mb-2">
            <h1
                class="text-lg sm:text-2xl font-semibold text-text-primary dark:text-text-inverted"
            >
                Join Subjects
            </h1>
            <p class="text-xs sm:text-sm text-text-secondary">
                Browse available subjects and request to join with your
                preferred instructor.
            </p>
        </div>

        <div ref="detailsSectionRef" class="sticky top-16 lg:top-[64px] z-40 mb-6">
            <div
                :class="[
                    'rounded-lg transition-all duration-200',
                    isDetailsStuck
                        ? 'px-3 py-2 sm:px-4 sm:py-3 bg-white/95 dark:bg-slate-900/95 border border-border-light dark:border-slate-700 shadow-md backdrop-blur-sm'
                        : '',
                ]"
            >
                <div class="relative w-full sm:max-w-md">
                    <svg
                        class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-text-secondary pointer-events-none"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35m1.85-5.15a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    <input
                        id="search"
                        v-model="search"
                        type="text"
                        placeholder="Search subjects by name, code, or description..."
                        class="w-full pl-10 pr-4 py-2 text-sm border border-border-light dark:border-border-dark rounded-lg bg-surface dark:bg-surface-dark-muted text-text-primary dark:text-text-inverted placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/40 focus:border-indigo-500 transition-all"
                    />
                </div>
            </div>
        </div>

        <!-- Empty State -->
        <div
            v-if="!hasSubjects && !search"
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
                    d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"
                />
            </svg>
            <h3
                class="text-lg font-medium text-text-primary dark:text-text-inverted mb-2"
            >
                No subjects available
            </h3>
            <p class="text-sm">
                There are no subjects available at the moment. Please check back
                later.
            </p>
        </div>

        <!-- No Results State -->
        <div
            v-else-if="!hasSubjects && search"
            class="card p-8 sm:p-12 text-center min-h-[20rem] flex flex-col items-center justify-center text-text-secondary"
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
                    d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
                />
            </svg>
            <h3
                class="text-lg font-medium text-text-primary dark:text-text-inverted mb-2"
            >
                No results found
            </h3>
            <p class="text-sm">
                Try adjusting your search terms to find what you're looking for.
            </p>
        </div>

        <!-- Subjects Grid -->
        <div
            v-else
            class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3"
        >
            <article
                v-for="subject in props.subjects"
                :key="subject.id"
                class="card overflow-hidden p-0 hover:shadow-lg transition-all duration-200 border border-border-light dark:border-border-dark flex flex-col"
                :class="{
                    'ring-1 ring-green-500/30 dark:ring-green-500/40': subject.status === 'approved',
                    'ring-1 ring-blue-500/30 dark:ring-blue-500/40': subject.status === 'pending',
                }"
            >
                <!-- Card header: code + title + status -->
                <div class="px-5 pt-5 pb-1">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex-1 min-w-0">
                            <span
                                class="inline-block text-xs font-medium text-text-secondary bg-surface-muted dark:bg-surface-dark-muted px-2 py-0.5 rounded mb-2"
                            >
                                {{ subject.code }}
                            </span>
                            <h3
                                class="text-lg font-semibold text-text-primary dark:text-text-inverted leading-tight"
                            >
                                {{ subject.name }}
                            </h3>
                        </div>
                        <Badge
                            :variant="getStatusBadge(subject.status).variant"
                            class="flex-shrink-0"
                        >
                            {{ getStatusBadge(subject.status).text }}
                        </Badge>
                    </div>
                    <p
                        v-if="subject.description"
                        class="text-sm text-text-secondary mt-2 line-clamp-2"
                    >
                        {{ subject.description }}
                    </p>
                </div>

                <!-- Instructors -->
                <div class="px-5 pb-5 pt-4 flex-1">
                    <p class="text-xs font-medium text-text-secondary uppercase tracking-wide mb-3">
                        Choose instructor
                    </p>
                    <div
                        v-if="subject.instructors.length === 0"
                        class="text-sm text-text-secondary italic py-2"
                    >
                        No instructors assigned
                    </div>
                    <ul v-else class="space-y-2">
                        <li
                            v-for="instructor in subject.instructors"
                            :key="instructor.id"
                            :class="[
                                'flex items-center gap-3 rounded-lg border border-border-light dark:border-border-dark pl-3 pr-3 py-2.5 border-l-4 transition-colors',
                                getInstructorDepartmentBorder(instructor.department_name),
                                instructor.is_selected && 'bg-accent-primary/5 dark:bg-accent-primary/10 border-accent-primary/40',
                            ]"
                        >
                            <div
                                class="flex-shrink-0 w-9 h-9 rounded-full bg-surface-muted dark:bg-surface-dark-muted flex items-center justify-center text-sm font-semibold text-text-primary dark:text-text-inverted"
                            >
                                {{ getInitials(instructor.name) }}
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-text-primary dark:text-text-inverted truncate">
                                    {{ instructor.name }}
                                </p>
                                <p
                                    v-if="instructor.department_name"
                                    class="text-xs text-text-secondary truncate"
                                >
                                    {{ instructor.department_name }}
                                </p>
                            </div>
                            <div class="flex-shrink-0">
                                <button
                                    v-if="canJoin(subject) && !instructor.is_selected"
                                    type="button"
                                    @click="joinSubject(subject, instructor.id)"
                                    :disabled="isProcessing(subject.id, instructor.id)"
                                    class="px-3 py-1.5 text-xs font-medium rounded-lg bg-accent-primary text-white hover:bg-accent-primary/90 focus:outline-none focus:ring-2 focus:ring-accent-primary/50 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                                >
                                    <span
                                        v-if="isProcessing(subject.id, instructor.id)"
                                        class="inline-flex items-center gap-1.5"
                                    >
                                        <svg
                                            class="animate-spin h-3.5 w-3.5"
                                            xmlns="http://www.w3.org/2000/svg"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                        >
                                            <circle
                                                class="opacity-25"
                                                cx="12"
                                                cy="12"
                                                r="10"
                                                stroke="currentColor"
                                                stroke-width="4"
                                            />
                                            <path
                                                class="opacity-75"
                                                fill="currentColor"
                                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                                            />
                                        </svg>
                                        Joining...
                                    </span>
                                    <span v-else>Join</span>
                                </button>
                                <span
                                    v-else-if="instructor.is_selected"
                                    class="inline-flex items-center gap-1 text-xs font-medium"
                                    :class="[
                                        subject.status === 'approved'
                                            ? 'text-green-600 dark:text-green-400'
                                            : subject.status === 'pending'
                                            ? 'text-blue-600 dark:text-blue-400'
                                            : 'text-accent-primary',
                                    ]"
                                >
                                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <span v-if="subject.status === 'approved'">Enrolled</span>
                                    <span v-else-if="subject.status === 'pending'">Pending</span>
                                </span>
                            </div>
                        </li>
                    </ul>
                </div>
            </article>
        </div>
    </StudentLayout>
</template>

<style scoped>
.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>
