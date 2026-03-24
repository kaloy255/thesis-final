<script setup>
import InstructorLayout from "@/Layouts/InstructorLayout.vue";
import RecentActivityCard from "@/Components/RecentActivityCard.vue";
import { Head, Link, router } from "@inertiajs/vue3";
import { computed, ref, watch, onMounted, onBeforeUnmount } from "vue";

const props = defineProps({
    logs: Object,
    filters: Object,
});

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

watch(searchQuery, (newValue) => {
    if (searchTimeout) clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        router.get(route("instructor.logs.index"), { search: newValue }, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    }, 300);
});

const logItems = computed(() => props.logs?.data ?? []);

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
        <Head title="Activity" />

        <div class="p-2.5 sm:p-4 mb-2">
            <h1 class="text-lg sm:text-2xl font-semibold text-text-primary dark:text-text-inverted">
                Activity
            </h1>
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
                        v-model="searchQuery"
                        type="text"
                        placeholder="Search by description..."
                        class="w-full pl-10 pr-4 py-2 text-sm border border-border-light dark:border-border-dark rounded-lg bg-surface dark:bg-surface-dark-muted text-text-primary dark:text-text-inverted placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/40 focus:border-indigo-500 transition-all"
                    />
                </div>
            </div>
        </div>

        <div class="card p-6 space-y-4">

            <!-- Empty State -->
            <div
                v-if="!logItems.length"
                class="text-center py-12 text-text-secondary"
            >
                <svg
                    class="w-16 h-16 mx-auto mb-4 opacity-50"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                    />
                </svg>
                <p class="text-sm font-medium text-text-primary dark:text-text-inverted mb-1">
                    No activity found
                </p>
                <p class="text-xs">
                    {{ searchQuery ? 'Try a different search.' : 'Your activities will appear here once you start creating assessments or managing students.' }}
                </p>
            </div>

            <!-- Timeline -->
            <div v-else class="relative">
                <div
                    class="absolute left-4 top-4 bottom-4 w-0.5 bg-border-light dark:bg-border-dark rounded-full"
                    aria-hidden="true"
                />
                <div class="space-y-0">
                    <RecentActivityCard
                        v-for="(log, index) in logItems"
                        :key="log.id"
                        :log="log"
                        :timeline="true"
                        :is-last="index === logItems.length - 1"
                    />
                </div>
            </div>

            <!-- Pagination -->
            <div
                v-if="logs?.links?.length"
                class="mt-6 flex gap-2 flex-wrap"
            >
                <Link
                    v-for="link in logs.links"
                    :key="link.url || link.label"
                    :href="link.url || '#'"
                    v-html="link.label"
                    class="px-3 py-1 rounded border text-sm transition"
                    :class="[
                        link.active
                            ? 'bg-accent-primary text-white border-transparent'
                            : 'bg-surface text-text-secondary border-border-light dark:border-border-dark dark:bg-surface-dark dark:text-text-inverted hover:bg-surface-muted dark:hover:bg-surface-dark-muted',
                        !link.url || link.url === '#' || link.url === null
                            ? 'opacity-50 cursor-not-allowed pointer-events-none'
                            : 'cursor-pointer',
                    ]"
                />
            </div>
        </div>
    </InstructorLayout>
</template>
