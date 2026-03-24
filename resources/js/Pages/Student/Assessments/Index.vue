<script setup>
import StudentLayout from "@/Layouts/StudentLayout.vue";
import { Head, router, usePage } from "@inertiajs/vue3";
import { computed, ref, watch, onMounted, onBeforeUnmount } from "vue";
import CardAssessment from "@/Components/CardAssessment.vue";
import { useToast } from "@/Stores/useToast";

const page = usePage();
const { success, error } = useToast();

const props = defineProps({
    assessments: Array,
    filters: Object,
});

const search = ref(props.filters?.search || "");
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

const hasAssessments = computed(
    () => props.assessments && props.assessments.length > 0
);

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

watch(search, (newValue) => {
    if (searchTimeout) clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        router.get(
            route("student.assessments.index"),
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

        <Head title="Assessments" />

        <div class="p-2.5 sm:p-4 mb-2">
            <h1 class="text-lg sm:text-2xl font-semibold text-text-primary dark:text-text-inverted">
                Assessments
            </h1>
            <p class="text-xs sm:text-sm text-text-secondary">
                Take assessments assigned to your section or from enrolled
                subjects.
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
                        placeholder="Search assessments by title, subject, or lesson..."
                        class="w-full pl-10 pr-4 py-2 text-sm border border-border-light dark:border-border-dark rounded-lg bg-surface dark:bg-surface-dark-muted text-text-primary dark:text-text-inverted placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/40 focus:border-indigo-500 transition-all"
                    />
                </div>
            </div>
        </div>

        <!-- Empty State -->
        <div v-if="!hasAssessments && !search" class="card p-12 text-center text-text-secondary">
            <svg class="mx-auto h-16 w-16 mb-4 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <h3 class="text-lg font-medium text-text-primary dark:text-text-inverted mb-2">
                No assessments available
            </h3>
            <p class="text-sm">
                There are no assessments available at the moment. Please check
                back later.
            </p>
        </div>

        <!-- No Results State -->
        <div v-else-if="!hasAssessments && search" class="card p-8 sm:p-12 text-center min-h-[20rem] flex flex-col items-center justify-center text-text-secondary">
            <svg class="mx-auto h-16 w-16 mb-4 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
            <h3 class="text-lg font-medium text-text-primary dark:text-text-inverted mb-2">
                No results found
            </h3>
            <p class="text-sm">
                Try adjusting your search terms to find what you're looking for.
            </p>
        </div>

        <!-- Assessments Grid -->
        <div v-else class="grid grid-cols-1 gap-6 sm:grid-cols-2 xl:grid-cols-3">
            <CardAssessment
                v-for="assessment in props.assessments"
                :key="assessment.id"
                :assessment="assessment"
            />
        </div>
    </StudentLayout>
</template>
