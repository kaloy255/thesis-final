<script setup>
import { Link, router } from "@inertiajs/vue3";
import { computed } from "vue";

const props = defineProps({
    /** Laravel paginator links array */
    links: {
        type: Array,
        default: () => [],
    },
    /** Current page number */
    currentPage: {
        type: Number,
        default: 1,
    },
    /** Last page number */
    lastPage: {
        type: Number,
        default: 1,
    },
    /** Items per page */
    perPage: {
        type: Number,
        default: 10,
    },
    /** Total items */
    total: {
        type: Number,
        default: 0,
    },
    /** From index (e.g. 1) */
    from: {
        type: Number,
        default: 0,
    },
    /** To index (e.g. 10) */
    to: {
        type: Number,
        default: 0,
    },
    /** Route name for building URLs when per_page changes (e.g. 'admin.students.index') */
    routeName: {
        type: String,
        required: true,
    },
    /** Current query/filter params to preserve (e.g. { search: '', department_id: '' }) */
    filters: {
        type: Object,
        default: () => ({}),
    },
    /** Per-page options [10, 25, 50, 100] */
    perPageOptions: {
        type: Array,
        default: () => [10, 25, 50, 100],
    },
});

const prevLink = computed(() => props.links[0] || {});
const nextLink = computed(() => props.links[props.links.length - 1] || {});

/** Compute which page numbers to display (1,2,3,4,5,...,last) */
const displayPages = computed(() => {
    const last = props.lastPage;
    const current = props.currentPage;

    if (last <= 7) {
        return Array.from({ length: last }, (_, i) => ({ type: "page", number: i + 1 }));
    }

    const result = [{ type: "page", number: 1 }];

    if (current <= 4) {
        for (let i = 2; i <= Math.min(5, last - 1); i++) {
            result.push({ type: "page", number: i });
        }
        result.push({ type: "ellipsis" });
    } else if (current >= last - 3) {
        result.push({ type: "ellipsis" });
        for (let i = Math.max(2, last - 4); i < last; i++) {
            result.push({ type: "page", number: i });
        }
    } else {
        result.push({ type: "ellipsis" });
        for (let i = current - 1; i <= current + 1; i++) {
            result.push({ type: "page", number: i });
        }
        result.push({ type: "ellipsis" });
    }

    if (last > 1) {
        result.push({ type: "page", number: last });
    }

    return result;
});

const pageLink = (pageNum) => {
    const link = props.links.find((l) => {
        const label = typeof l.label === "string" ? l.label.replace(/<[^>]*>/g, "").trim() : "";
        return label === String(pageNum);
    });
    return link?.url || route(props.routeName, { ...props.filters, per_page: props.perPage, page: pageNum });
};

const changePerPage = (value) => {
    const perPage = Number(value);
    if (perPage === props.perPage) return;
    router.get(route(props.routeName), {
        ...props.filters,
        per_page: perPage,
        page: 1,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

/** Show whenever the paginator provides link metadata (including empty result sets). */
const showPagination = computed(() => Array.isArray(props.links) && props.links.length >= 1);

const perPageOptionsSorted = computed(() => {
    const opts = new Set(props.perPageOptions);
    if (!opts.has(props.perPage)) {
        opts.add(props.perPage);
    }
    return Array.from(opts).sort((a, b) => a - b);
});

const pageBtnClass = (isActive) =>
    [
        "min-w-[2.25rem] shrink-0 px-2.5 py-2 text-sm font-medium rounded-lg text-center transition-colors",
        isActive
            ? "bg-indigo-600 text-white"
            : "text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700",
    ];

const navLinkClass = "shrink-0 px-2 py-2 text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:text-indigo-700 dark:hover:text-indigo-300 transition-colors disabled:opacity-50";
const navLinkDisabledClass = "shrink-0 px-2 py-2 text-sm text-gray-400 dark:text-gray-500";
</script>

<template>
    <nav
        v-if="showPagination"
        class="px-4 py-4 sm:px-5 border-t border-gray-200 dark:border-gray-700"
        aria-label="Pagination"
    >
        <!-- Phones & tablets: summary → scrollable page row → prev/next (per-page is desktop-only, see xl+ block) -->
        <div class="lg:hidden flex flex-col gap-4 w-full min-w-0">
            <p
                class="text-center text-sm text-gray-700 dark:text-gray-300 tabular-nums leading-snug px-1"
                aria-live="polite"
            >
                <span class="font-medium">{{ from || 0 }}</span>
                <span class="text-gray-500 dark:text-gray-400">–</span>
                <span class="font-medium">{{ to || 0 }}</span>
                <span class="text-gray-500 dark:text-gray-400"> of </span>
                <span class="font-medium">{{ total || 0 }}</span>
            </p>

            <!-- Page numbers: full-width scroll strip with padding so first/last digits are never clipped -->
            <div
                class="w-full min-w-0 -mx-1 px-3 overflow-x-auto overflow-y-hidden overscroll-x-contain scroll-smooth [scrollbar-width:thin]"
            >
                <div class="flex w-max min-h-[44px] items-center justify-center gap-1 mx-auto py-0.5">
                    <template v-for="(item, idx) in displayPages" :key="idx">
                        <Link
                            v-if="item.type === 'page'"
                            :href="pageLink(item.number)"
                            :class="pageBtnClass(item.number === currentPage)"
                            preserve-scroll
                        >
                            {{ item.number }}
                        </Link>
                        <span
                            v-else
                            class="px-1.5 py-2 text-sm text-gray-400 dark:text-gray-500 shrink-0 select-none"
                        >
                            …
                        </span>
                    </template>
                </div>
            </div>

            <!-- Prev / Next: equal columns, aligned with summary -->
            <div class="grid grid-cols-2 gap-3 w-full px-0.5">
                <Link
                    v-if="prevLink.url"
                    :href="prevLink.url"
                    class="inline-flex items-center justify-center min-h-[44px] px-3 rounded-lg border border-gray-300 dark:border-gray-600 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                    preserve-scroll
                >
                    Previous
                </Link>
                <span
                    v-else
                    class="inline-flex items-center justify-center min-h-[44px] px-3 rounded-lg border border-dashed border-gray-200 dark:border-gray-700 text-sm text-gray-400 dark:text-gray-500"
                >
                    Previous
                </span>
                <Link
                    v-if="nextLink.url"
                    :href="nextLink.url"
                    class="inline-flex items-center justify-center min-h-[44px] px-3 rounded-lg border border-gray-300 dark:border-gray-600 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                    preserve-scroll
                >
                    Next
                </Link>
                <span
                    v-else
                    class="inline-flex items-center justify-center min-h-[44px] px-3 rounded-lg border border-dashed border-gray-200 dark:border-gray-700 text-sm text-gray-400 dark:text-gray-500"
                >
                    Next
                </span>
            </div>
        </div>

        <!-- Desktop (lg+): single row; per-page control only on xl+ (wide desktop) -->
        <div class="hidden lg:flex flex-row flex-wrap xl:flex-nowrap items-center justify-between gap-x-4 gap-y-3 w-full min-w-0">
            <p class="text-sm text-gray-700 dark:text-gray-300 shrink-0">
                Showing
                <span class="font-medium tabular-nums">{{ from || 0 }}</span>
                to
                <span class="font-medium tabular-nums">{{ to || 0 }}</span>
                of
                <span class="font-medium tabular-nums">{{ total || 0 }}</span>
                results
            </p>

            <div class="flex flex-wrap items-center gap-2 xl:gap-3 min-w-0 justify-end flex-1">
                <Link
                    v-if="prevLink.url"
                    :href="prevLink.url"
                    :class="navLinkClass"
                    preserve-scroll
                >
                    Previous
                </Link>
                <span v-else :class="navLinkDisabledClass">Previous</span>

                <div class="flex items-center gap-1 flex-wrap justify-end">
                    <template v-for="(item, idx) in displayPages" :key="idx">
                        <Link
                            v-if="item.type === 'page'"
                            :href="pageLink(item.number)"
                            :class="pageBtnClass(item.number === currentPage)"
                            preserve-scroll
                        >
                            {{ item.number }}
                        </Link>
                        <span
                            v-else
                            class="px-2 py-2 text-sm text-gray-400 dark:text-gray-500"
                        >
                            ...
                        </span>
                    </template>
                </div>

                <Link
                    v-if="nextLink.url"
                    :href="nextLink.url"
                    :class="navLinkClass"
                    preserve-scroll
                >
                    Next
                </Link>
                <span v-else :class="navLinkDisabledClass">Next</span>

                <div
                    class="hidden xl:flex items-center gap-2 ml-2 pl-2 border-l border-gray-200 dark:border-gray-600 shrink-0"
                >
                    <label class="sr-only" for="pagination-per-page-desktop">Results per page</label>
                    <select
                        id="pagination-per-page-desktop"
                        :value="perPage"
                        class="min-h-[40px] px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-colors"
                        @change="changePerPage(($event.target).value)"
                    >
                        <option
                            v-for="opt in perPageOptionsSorted"
                            :key="opt"
                            :value="opt"
                        >
                            {{ opt }} / page
                        </option>
                    </select>
                </div>
            </div>
        </div>
    </nav>
</template>
