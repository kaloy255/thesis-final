<script setup>
import AdminLayout from "@/Layouts/AdminLayout.vue";
import DataTable from "@/Components/DataTable.vue";
import { Head, Link } from "@inertiajs/vue3";
import { formatTimeAgo } from "@/utils/formatTime";
import { Icon } from "@iconify/vue";
import Chart from "primevue/chart";
import DatePicker from "primevue/datepicker";
import { computed, ref, watch, onMounted } from "vue";

const props = defineProps({
    stats: Object,
    logs: Array,
    aiUsage: Object,
});

const aiUsageState = ref(props.aiUsage ?? { labels: [], series: {}, meta: {} });

const formatLocalDateToISODate = (date) => {
    if (!(date instanceof Date) || Number.isNaN(date.getTime())) return "";
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, "0");
    const d = String(date.getDate()).padStart(2, "0");
    return `${y}-${m}-${d}`;
};

// Default selection: today and +7 days (based on Asia/Manila)
const TIME_ZONE = "Asia/Manila";
const getISODateInTimeZone = (date, timeZone) => {
    // `en-CA` with `timeZone` returns `YYYY-MM-DD`
    return new Intl.DateTimeFormat("en-CA", {
        timeZone,
        year: "numeric",
        month: "2-digit",
        day: "2-digit",
    }).format(date);
};

const isoToLocalDate = (iso) => {
    const [y, m, d] = iso.split("-").map((v) => Number(v));
    if (!y || !m || !d) return null;
    return new Date(y, m - 1, d);
};

const todayLocal = isoToLocalDate(getISODateInTimeZone(new Date(), TIME_ZONE));
const in7DaysLocal = new Date(todayLocal);
in7DaysLocal.setDate(todayLocal.getDate() + 7);

// Backend initial default is "last 30 days": (today - 29 days) .. today
const backendDefaultFromLocal = new Date(todayLocal);
backendDefaultFromLocal.setDate(todayLocal.getDate() - 29);

const backendDefaultFromISO = formatLocalDateToISODate(backendDefaultFromLocal);
const backendDefaultToISO = formatLocalDateToISODate(todayLocal);

const defaultFromISO = backendDefaultToISO; // today
const defaultToISO = formatLocalDateToISODate(in7DaysLocal); // today + 7

const dateFrom = ref(
    (() => {
        const metaFrom = aiUsageState.value?.meta?.from;
        const metaTo = aiUsageState.value?.meta?.to;
        const metaFromISO = metaFrom && String(metaFrom).trim().length ? metaFrom : "";
        const metaToISO = metaTo && String(metaTo).trim().length ? metaTo : "";

        // If backend sent its own default range (last 30 days), override to "today..today+7"
        if (metaFromISO && metaToISO && metaFromISO === backendDefaultFromISO && metaToISO === backendDefaultToISO) {
            return defaultFromISO;
        }

        return metaFromISO || defaultFromISO;
    })()
);
const dateTo = ref(
    (() => {
        const metaFrom = aiUsageState.value?.meta?.from;
        const metaTo = aiUsageState.value?.meta?.to;
        const metaFromISO = metaFrom && String(metaFrom).trim().length ? metaFrom : "";
        const metaToISO = metaTo && String(metaTo).trim().length ? metaTo : "";

        if (metaFromISO && metaToISO && metaFromISO === backendDefaultFromISO && metaToISO === backendDefaultToISO) {
            return defaultToISO;
        }

        return metaToISO || defaultToISO;
    })()
);
const datePickerRef = ref(null);

const parseISODateToLocalDate = (isoDate) => {
    if (!isoDate) return null;

    // Avoid timezone issues: build a local Date at midnight.
    const [y, m, d] = isoDate.split("-").map((v) => Number(v));
    if (!y || !m || !d) return null;

    return new Date(y, m - 1, d);
};

const rangeValue = ref([parseISODateToLocalDate(dateFrom.value), parseISODateToLocalDate(dateTo.value)]);

const isRangeComplete = computed(() => {
    const start = rangeValue.value?.[0];
    const end = rangeValue.value?.[1];
    return start instanceof Date && end instanceof Date;
});

const syncRangeFromCommittedDates = () => {
    rangeValue.value = [parseISODateToLocalDate(dateFrom.value), parseISODateToLocalDate(dateTo.value)];
};

const initialMetaFromISO = (() => {
    const metaFrom = aiUsageState.value?.meta?.from;
    return metaFrom && String(metaFrom).trim().length ? metaFrom : "";
})();

const initialMetaToISO = (() => {
    const metaTo = aiUsageState.value?.meta?.to;
    return metaTo && String(metaTo).trim().length ? metaTo : "";
})();

const shouldOverrideInitialRangeTo7Days = computed(() => {
    return initialMetaFromISO && initialMetaToISO && initialMetaFromISO === backendDefaultFromISO && initialMetaToISO === backendDefaultToISO;
});

onMounted(() => {
    // If backend delivered its own initial default range (last 30 days),
    // immediately fetch the aligned range for "today..today+7".
    if (shouldOverrideInitialRangeTo7Days.value) {
        fetchDailyUsage().catch(() => {
            // If fetch fails, keep initial chart (user can still press Apply).
        });
    }
});

watch(
    () => props.aiUsage,
    (next) => {
        aiUsageState.value = next ?? aiUsageState.value;
        const metaFrom = aiUsageState.value?.meta?.from;
        const metaTo = aiUsageState.value?.meta?.to;

        const metaFromISO = metaFrom && String(metaFrom).trim().length ? metaFrom : "";
        const metaToISO = metaTo && String(metaTo).trim().length ? metaTo : "";

        // If backend sent its own initial default range (last 30 days), override to "today..today+7"
        const shouldOverrideTo7Days =
            metaFromISO && metaToISO && metaFromISO === backendDefaultFromISO && metaToISO === backendDefaultToISO;

        dateFrom.value = shouldOverrideTo7Days ? defaultFromISO : metaFromISO || defaultFromISO;
        dateTo.value = shouldOverrideTo7Days ? defaultToISO : metaToISO || defaultToISO;
        syncRangeFromCommittedDates();
    }
);

const formatDay = (isoDate) => {
    if (!isoDate) return "";
    const d = new Date(`${isoDate}T00:00:00Z`);
    if (Number.isNaN(d.getTime())) return isoDate;
    return d.toLocaleDateString(undefined, {
        month: "short",
        day: "numeric",
    });
};

const chartData = computed(() => {
    const labels = aiUsageState.value?.labels ?? [];
    const series = aiUsageState.value?.series ?? {};

    const documentStyle = getComputedStyle(document.documentElement);
    const cyan = documentStyle.getPropertyValue("--p-cyan-500").trim() || "#3b82f6";
    const gray = documentStyle.getPropertyValue("--p-gray-500").trim() || "#6b7280";
    const purple = documentStyle.getPropertyValue("--p-purple-500").trim() || "#a855f7";

    const openai = series.openai ?? [];
    const gemini = series.gemini ?? [];
    const groq = series.groq ?? [];
    const labelsFormatted = labels.map(formatDay);

    return {
        labels: labelsFormatted,
        datasets: [
            {
                label: "OpenAI",
                data: openai,
                fill: false,
                tension: 1,
                borderColor: cyan,
                backgroundColor: "rgba(59, 130, 246, 0.12)",
                pointRadius: 3,
                pointHoverRadius: 5,
            },
            {
                label: "Gemini",
                data: gemini,
                fill: false,
                tension: 0.4,
                borderColor: gray,
                backgroundColor: "rgba(107, 114, 128, 0.12)",
                pointRadius: 3,
                pointHoverRadius: 5,
            },
            {
                label: "Groq",
                data: groq,
                fill: false,
                tension: 0.4,
                borderColor: purple,
                backgroundColor: "rgba(168, 85, 247, 0.12)",
                pointRadius: 3,
                pointHoverRadius: 5,
            },
        ],
    };
});

const chartOptions = computed(() => {
    const documentStyle = getComputedStyle(document.documentElement);
    const textColor = documentStyle.getPropertyValue("--p-text-color").trim() || "#6b7280";
    const gridColor = documentStyle.getPropertyValue("--p-content-border-color").trim() || "rgba(107, 114, 128, 0.18)";

    return {
        maintainAspectRatio: false,
        plugins: { legend: { labels: { color: textColor } } },
        scales: {
            x: {
                ticks: {
                    color: textColor,
                },
                grid: {
                    color: gridColor,
                },
            },
            y: {
                ticks: {
                    color: textColor,
                    callback: (value) => value.toLocaleString(),
                },
                grid: {
                    color: gridColor,
                },
            },
        },
    };
});

const fetchDailyUsage = async () => {
    if (!dateFrom.value || !dateTo.value) return;

    const baseUrl = "/admin/ai-usage/daily";
    const url = `${baseUrl}?from=${encodeURIComponent(dateFrom.value)}&to=${encodeURIComponent(dateTo.value)}`;

    const res = await fetch(url, {
        headers: {
            Accept: "application/json",
        },
        credentials: "same-origin",
    });

    if (!res.ok) {
        throw new Error(`Failed to fetch AI usage: ${res.status}`);
    }

    const data = await res.json();
    aiUsageState.value = data;
};

const closeDatePickerOverlay = () => {
    const dp = datePickerRef.value;
    if (dp && typeof dp === "object" && "overlayVisible" in dp) {
        dp.overlayVisible = false;
        return;
    }

    // Fallback: blur input to close overlay in case the internal state isn't directly accessible.
    dp?.$el?.blur?.();
};

const onCancelRange = () => {
    syncRangeFromCommittedDates();
    closeDatePickerOverlay();
};

const onApplyRange = async () => {
    if (!isRangeComplete.value) return;

    const [start, end] = rangeValue.value;
    dateFrom.value = formatLocalDateToISODate(start);
    dateTo.value = formatLocalDateToISODate(end);

    await fetchDailyUsage();
    closeDatePickerOverlay();
};
</script>

<template>
    <AdminLayout>
        <Head title="Dashboard" />
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
            <Link
                :href="route('admin.instructors.index')"
                class="card p-3 sm:p-4 block transition-all duration-300 cursor-pointer group hover:shadow-xl hover:border-l-4 hover:border-blue-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-primary/40"
            >
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="text-sm text-text-secondary">Professors</div>
                        <div class="text-3xl font-semibold mt-1">
                            {{ stats.professors }}
                        </div>
                        <div class="text-xs text-text-secondary mt-1">
                            Manage professors
                        </div>
                    </div>
                    <div
                        class="w-10 h-10 rounded-xl bg-blue-500/10 text-blue-600 dark:text-blue-400 flex items-center justify-center group-hover:scale-110 transition-transform"
                    >
                        <Icon icon="simple-line-icons:people" class="w-5 h-5" />
                    </div>
                </div>
            </Link>
            <Link
                :href="route('admin.students.index')"
                class="card p-3 sm:p-4 block transition-all duration-300 cursor-pointer group hover:shadow-xl hover:border-l-4 hover:border-green-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-primary/40"
            >
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="text-sm text-text-secondary">Students</div>
                        <div class="text-3xl font-semibold mt-1">
                            {{ stats.students }}
                        </div>
                        <div class="text-xs text-text-secondary mt-1">
                            Manage students
                        </div>
                    </div>
                    <div
                        class="w-10 h-10 rounded-xl bg-green-500/10 text-green-600 dark:text-green-400 flex items-center justify-center group-hover:scale-110 transition-transform"
                    >
                        <Icon icon="simple-line-icons:people" class="w-5 h-5" />
                    </div>
                </div>
            </Link>
            <Link
                :href="route('admin.departments.index')"
                class="card p-3 sm:p-4 block transition-all duration-300 cursor-pointer group hover:shadow-xl hover:border-l-4 hover:border-amber-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-primary/40"
            >
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="text-sm text-text-secondary">Departments</div>
                        <div class="text-3xl font-semibold mt-1">
                            {{ stats.departments }}
                        </div>
                        <div class="text-xs text-text-secondary mt-1">
                            Manage departments
                        </div>
                    </div>
                    <div
                        class="w-10 h-10 rounded-xl bg-amber-500/10 text-amber-600 dark:text-amber-400 flex items-center justify-center group-hover:scale-110 transition-transform"
                    >
                        <svg
                            class="w-5 h-5"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M4 7h16M4 12h16M4 17h10"
                            />
                        </svg>
                    </div>
                </div>
            </Link>
            <Link
                :href="route('admin.sections.index')"
                class="card p-3 sm:p-4 block transition-all duration-300 cursor-pointer group hover:shadow-xl hover:border-l-4 hover:border-purple-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-primary/40"
            >
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="text-sm text-text-secondary">Sections</div>
                        <div class="text-3xl font-semibold mt-1">
                            {{ stats.sections }}
                        </div>
                        <div class="text-xs text-text-secondary mt-1">
                            Manage sections
                        </div>
                    </div>
                    <div
                        class="w-10 h-10 rounded-xl bg-purple-500/10 text-purple-600 dark:text-purple-400 flex items-center justify-center group-hover:scale-110 transition-transform"
                    >
                        <Icon icon="simple-icons:sanity" class="w-5 h-5" />
                    </div>
                </div>
            </Link>
            <Link
                :href="route('admin.subjects.index')"
                class="card p-3 sm:p-4 block transition-all duration-300 cursor-pointer group hover:shadow-xl hover:border-l-4 hover:border-indigo-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-primary/40"
            >
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="text-sm text-text-secondary">Subjects</div>
                        <div class="text-3xl font-semibold mt-1">
                            {{ stats.subjects }}
                        </div>
                        <div class="text-xs text-text-secondary mt-1">
                            Manage subjects
                        </div>
                    </div>
                    <div
                        class="w-10 h-10 rounded-xl bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 flex items-center justify-center group-hover:scale-110 transition-transform"
                    >
                        <Icon icon="simple-icons:bookstack" class="w-5 h-5" />
                    </div>
                </div>
            </Link>
        </div>
        <div class="card p-4 mb-6">
            <div class="flex items-start justify-between gap-4 mb-3">
                <div>
                    <h2 class="text-lg font-semibold">AI Token Usage (Daily)</h2>
                    <div class="text-xs text-text-secondary mt-1">
                        Daily total tokens by fallback provider
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <DatePicker
                        class="border p-2 rounded-lg min-w-[15rem] flex justify-center"
                        ref="datePickerRef"
                        v-model="rangeValue"
                        selectionMode="range"
                        :numberOfMonths="1"
                        :manualInput="false"
                        :showIcon="true"
                        :showButtonBar="false"
                        :hideOnRangeSelection="false"
                        dateFormat="M-d-yy"
                        placeholder="Select date range"
                        inputClass="w-56"
                    >
                        <template #footer>
                            <div class="flex items-center justify-center gap-2 w-full">
                                <button
                                    type="button"
                                    class="px-4 py-2 rounded-md border border-border-light/30 bg-transparent text-sm text-text-secondary"
                                    @click="onCancelRange"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="button"
                                    class="px-4 py-2 rounded-md border border-accent-primary bg-accent-primary/10 text-sm text-accent-primary disabled:opacity-50 disabled:cursor-not-allowed"
                                    :disabled="!isRangeComplete"
                                    @click="onApplyRange"
                                >
                                    Apply
                                </button>
                            </div>
                        </template>
                    </DatePicker>
                    <!-- Preserves existing behavior: chart updates only after clicking Apply -->
                    <span class="sr-only">Use Apply to update charts</span>
                </div>
            </div>

            <div class="h-[320px]">
                <Chart type="line" :data="chartData" :options="chartOptions" />
            </div>
        </div>
        <!--logs table-->
        <div class="card p-4">
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-lg font-semibold">Recent Activity</h2>
                <div class="flex items-center gap-3">
                    <span class="text-xs text-text-secondary"
                        >Latest 7 entries</span
                    >
                    <Link
                        :href="route('admin.logs.index')"
                        class="btn-ghost text-sm"
                    >
                        Show All
                    </Link>
                </div>
            </div>
            <DataTable
                :headers="['Time', 'User', 'Role', 'Description']"
                :rows="logs"
                :links="[]"
                empty-text="No recent logs."
            >
                <template #row="{ row }">
                    <td
                        class="px-3 py-2 whitespace-nowrap"
                        :title="row.created_at"
                    >
                        {{ formatTimeAgo(row.created_at) }}
                    </td>
                    <td class="px-3 py-2">{{ row.user?.name || "N/A" }}</td>
                    <td class="px-3 py-2 capitalize">{{ row.role }}</td>
                    <td class="px-3 py-2">{{ row.description }}</td>
                </template>
            </DataTable>
        </div>
    </AdminLayout>
</template>
