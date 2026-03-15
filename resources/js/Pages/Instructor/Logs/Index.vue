<script setup>
import InstructorLayout from "@/Layouts/InstructorLayout.vue";
import RecentActivityCard from "@/Components/RecentActivityCard.vue";
import InputLabel from "@/Components/InputLabel.vue";
import { Head, Link, router } from "@inertiajs/vue3";
import { computed, ref, watch } from "vue";

const props = defineProps({
    logs: Object,
    filters: Object,
});

const searchQuery = ref(props.filters?.search || "");
let searchTimeout = null;

watch(searchQuery, (newValue) => {
    if (searchTimeout) clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        router.get(route("instructor.logs.index"), { search: newValue }, {
            preserveState: true,
            preserveScroll: false,
            replace: true,
        });
    }, 500);
});

const logItems = computed(() => props.logs?.data ?? []);
</script>

<template>
    <InstructorLayout>
        <Head title="Activity" />

        <div class="flex items-center justify-between mb-4">
            <h1 class="text-xl font-semibold text-text-primary dark:text-text-inverted">
                Activity
            </h1>
        </div>

        <div class="card p-6 space-y-4">
            <div>
                <InputLabel for="search" value="Search" />
                <input
                    id="search"
                    v-model="searchQuery"
                    type="text"
                    placeholder="Search by description..."
                    class="input mt-1"
                />
            </div>

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
