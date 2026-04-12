<script setup>
import StudentLayout from "@/Layouts/StudentLayout.vue";
import { Head, useForm, usePage } from "@inertiajs/vue3";
import { computed, ref, watch, nextTick, onMounted, onUnmounted } from "vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import InputError from "@/Components/InputError.vue";
import axios from "axios";

const props = defineProps({
    assessment: Object,
    items: Array,
    /** ISO8601 from server session when assessment is timed; null otherwise */
    timerStartedAt: {
        type: String,
        default: null,
    },
});

const page = usePage();

const form = useForm({
    answers: {},
});

const currentQuestionIndex = ref(0);
const violationCount = ref(0);

const totalQuestions = computed(() => props.items?.length || 0);

const currentQuestion = computed(() => {
    return props.items?.[currentQuestionIndex.value] || null;
});

const isFirstQuestion = computed(() => currentQuestionIndex.value === 0);

const isLastQuestion = computed(() => currentQuestionIndex.value === totalQuestions.value - 1);

const answeredQuestions = computed(() => {
    return Object.keys(form.answers).filter(
        (key) => form.answers[key]?.answer !== null && form.answers[key]?.answer !== ""
    ).length;
});

const unansweredQuestions = computed(() => {
    return totalQuestions.value - answeredQuestions.value;
});

/** localStorage key: draft answers until submit (same browser only) */
const draftStorageKey = computed(() => {
    const uid = page.props.auth?.user?.id;
    if (!uid || !props.assessment?.id) {
        return null;
    }
    return `assessment_draft_${props.assessment.id}_${uid}`;
});

function saveDraftToStorage() {
    try {
        const key = draftStorageKey.value;
        if (!key) {
            return;
        }
        const payload = {
            answers: JSON.parse(JSON.stringify(form.answers)),
            savedAt: new Date().toISOString(),
        };
        localStorage.setItem(key, JSON.stringify(payload));
    } catch {
        // quota / private mode
    }
}

function loadDraftFromStorage() {
    try {
        const key = draftStorageKey.value;
        if (!key) {
            return false;
        }
        const raw = localStorage.getItem(key);
        if (!raw) {
            return false;
        }
        const parsed = JSON.parse(raw);
        if (!parsed?.answers || typeof parsed.answers !== "object") {
            return false;
        }
        let restored = false;
        for (const itemId of Object.keys(form.answers)) {
            const v = parsed.answers[itemId];
            if (v && v.answer !== undefined && v.answer !== null && v.answer !== "") {
                form.answers[itemId] = { answer: v.answer };
                restored = true;
            }
        }
        return restored;
    } catch {
        return false;
    }
}

function clearAssessmentDraft() {
    try {
        const key = draftStorageKey.value;
        if (key) {
            localStorage.removeItem(key);
        }
    } catch {
        // ignore
    }
}

// Initialize form answers and ensure choices is always an array
props.items?.forEach((item) => {
    form.answers[item.id] = { answer: "" };

    if (item.type === "multiple_choice" && item.choices) {
        if (typeof item.choices === "string") {
            try {
                item.choices = JSON.parse(item.choices);
            } catch (e) {
                item.choices = [];
            }
        }
        if (!Array.isArray(item.choices)) {
            item.choices = [];
        }
    }
});

const draftRestored = ref(loadDraftFromStorage());

let draftDebounce = null;
watch(
    () => form.answers,
    () => {
        clearTimeout(draftDebounce);
        draftDebounce = setTimeout(() => saveDraftToStorage(), 400);
    },
    { deep: true }
);

const updateAnswer = (itemId, answer) => {
    form.answers[itemId] = { answer };
};

const getChoices = (item) => {
    if (!item.choices) {
        return [];
    }
    if (Array.isArray(item.choices)) {
        return item.choices;
    }
    if (typeof item.choices === "string") {
        try {
            return JSON.parse(item.choices);
        } catch (e) {
            return [];
        }
    }
    return [];
};

const nextQuestion = () => {
    if (currentQuestionIndex.value < totalQuestions.value - 1) {
        currentQuestionIndex.value++;
    }
};

const previousQuestion = () => {
    if (currentQuestionIndex.value > 0) {
        currentQuestionIndex.value--;
    }
};

const goToQuestion = (index) => {
    if (index >= 0 && index < totalQuestions.value) {
        currentQuestionIndex.value = index;
    }
};

const paginationButtonRefs = ref([]);
const setPaginationButtonRef = (el, index) => {
    if (el) {
        paginationButtonRefs.value[index] = el;
    }
};

watch(currentQuestionIndex, async () => {
    await nextTick();
    const btn = paginationButtonRefs.value[currentQuestionIndex.value];
    if (btn) {
        btn.scrollIntoView({ behavior: "smooth", block: "nearest", inline: "center" });
    }
}, { immediate: true });

const isSubmittingAssessment = ref(false);

const hasTimeLimit = computed(
    () => (props.assessment?.time_limit_minutes ?? 0) > 0 && !!props.timerStartedAt
);

const remainingSeconds = ref(null);
const autoExpiredSubmit = ref(false);
let timerIntervalId = null;

const formatCountdown = (totalSec) => {
    const m = Math.floor(totalSec / 60);
    const s = totalSec % 60;
    return `${String(m).padStart(2, "0")}:${String(s).padStart(2, "0")}`;
};

const doSubmit = (fromTimer = false) => {
    if (
        !fromTimer &&
        unansweredQuestions.value > 0 &&
        !confirm(
            `You have ${unansweredQuestions.value} unanswered question(s). Do you want to submit anyway?`
        )
    ) {
        return;
    }

    isSubmittingAssessment.value = true;
    saveDraftToStorage();
    form.post(route("student.assessments.store", props.assessment.id), {
        preserveScroll: true,
        onSuccess: () => {
            clearAssessmentDraft();
        },
        onFinish: () => {
            isSubmittingAssessment.value = false;
        },
    });
};

const submitForm = () => doSubmit(false);

const tickTimer = () => {
    if (!hasTimeLimit.value) {
        return;
    }
    const end =
        new Date(props.timerStartedAt).getTime() +
        props.assessment.time_limit_minutes * 60 * 1000;
    const sec = Math.max(0, Math.floor((end - Date.now()) / 1000));
    remainingSeconds.value = sec;
    if (sec <= 0 && !autoExpiredSubmit.value && !form.processing && !isSubmittingAssessment.value) {
        autoExpiredSubmit.value = true;
        doSubmit(true);
    }
};

// ===================== Cheating Detection =====================

const lastEventTime = ref({});
const DEBOUNCE_MS = 5000;

const logCheatingEvent = async (eventType) => {
    const now = Date.now();
    if (lastEventTime.value[eventType] && now - lastEventTime.value[eventType] < DEBOUNCE_MS) {
        return;
    }
    lastEventTime.value[eventType] = now;
    violationCount.value++;

    try {
        await axios.post(route("student.assessments.cheating-log", props.assessment.id), {
            event_type: eventType,
        });
    } catch (e) {
        // silently fail
    }
};

const handleVisibilityChange = () => {
    if (document.hidden) {
        saveDraftToStorage();
        logCheatingEvent("tab_switch");
    }
};

const handleWindowBlur = () => {
    logCheatingEvent("window_blur");
};

const handleBeforeUnload = (e) => {
    if (isSubmittingAssessment.value) {
        return;
    }
    saveDraftToStorage();
    logCheatingEvent("page_leave");
    e.preventDefault();
    e.returnValue = "";
};

onMounted(() => {
    document.addEventListener("visibilitychange", handleVisibilityChange);
    window.addEventListener("blur", handleWindowBlur);
    window.addEventListener("beforeunload", handleBeforeUnload);
    if (hasTimeLimit.value) {
        tickTimer();
        timerIntervalId = setInterval(tickTimer, 1000);
    }
});

onUnmounted(() => {
    if (timerIntervalId) {
        clearInterval(timerIntervalId);
        timerIntervalId = null;
    }
    document.removeEventListener("visibilitychange", handleVisibilityChange);
    window.removeEventListener("blur", handleWindowBlur);
    window.removeEventListener("beforeunload", handleBeforeUnload);
});
</script>

<template>
    <StudentLayout>
        <Head :title="assessment.title" />

        <div
            class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 pb-36 sm:pb-10 pt-4 sm:pt-6"
        >
            <!-- Draft + leave UX -->
            <div
                v-if="draftRestored"
                class="mb-4 rounded-lg border border-emerald-200/90 bg-emerald-50/90 px-3 py-2.5 text-sm text-emerald-950 dark:border-emerald-800/60 dark:bg-emerald-950/35 dark:text-emerald-100"
            >
                Your previous answers were restored from this browser. Nothing is saved on the server until you submit.
            </div>
            <div
                class="mb-6 rounded-lg border border-sky-200/90 bg-sky-50/90 px-3 py-2.5 text-xs sm:text-sm text-sky-950 dark:border-sky-800/60 dark:bg-sky-950/35 dark:text-sky-100"
            >
                <p class="font-medium text-sky-900 dark:text-sky-50">Answers stay in this browser until you submit</p>
                <p class="mt-1 text-sky-800/95 dark:text-sky-200/90 leading-relaxed">
                    If you leave or refresh, your work is kept locally so you can continue (same device/browser). Your instructor only sees results after you submit.
                </p>
            </div>

            <!-- Monitoring: thin accent strip, no card -->
            <div
                class="mb-8 sm:mb-10 pl-6 border-l-2 border-amber-500/90 dark:border-amber-400/80 py-0.5"
            >
                <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                    <p class="text-[13px] sm:text-sm font-medium text-text-primary dark:text-text-inverted">
                        Session is monitored
                    </p>
                    <span
                        v-if="violationCount > 0"
                        class="text-xs font-medium text-amber-800 dark:text-amber-200 tabular-nums"
                    >
                        {{ violationCount }} notice{{ violationCount !== 1 ? "s" : "" }}
                    </span>
                </div>
                <p class="text-xs sm:text-[13px] text-text-secondary mt-1.5 max-w-prose leading-relaxed">
                    Tab switches and leaving this page may be logged for your instructor. Closing the tab may show a browser warning.
                </p>
            </div>

            <!-- Title + meta: typography only -->
            <header class="mb-8 sm:mb-10">
                <div
                    v-if="hasTimeLimit && remainingSeconds !== null"
                    class="mb-4 flex flex-wrap items-center justify-between gap-2 rounded-lg border border-amber-200/80 bg-amber-50/90 px-3 py-2.5 text-sm dark:border-amber-800/60 dark:bg-amber-950/40"
                >
                    <span class="font-medium text-amber-950 dark:text-amber-100">Time remaining</span>
                    <span
                        class="tabular-nums text-lg font-semibold tracking-tight"
                        :class="remainingSeconds <= 60 ? 'text-red-600 dark:text-red-400' : 'text-amber-900 dark:text-amber-200'"
                    >
                        {{ formatCountdown(remainingSeconds) }}
                    </span>
                </div>
                <h1
                    class="text-[clamp(1.375rem,4vw,1.875rem)] font-semibold tracking-tight text-text-primary dark:text-text-inverted leading-tight"
                >
                    {{ assessment.title }}
                </h1>
                <p
                    class="mt-3 text-sm text-text-secondary leading-relaxed max-w-prose"
                >
                    <span>{{ assessment.subject.name }} ({{ assessment.subject.code }})</span>
                    <span class="mx-2 text-border-light dark:text-border-dark" aria-hidden="true">·</span>
                    <span>{{ assessment.lesson.title }}</span>
                </p>
            </header>

            <!-- Progress: single slim bar + caption -->
            <div class="mb-10 sm:mb-12">
                <div
                    class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 text-xs sm:text-sm text-text-secondary"
                >
                    <span class="tabular-nums font-medium text-text-primary dark:text-text-inverted">
                        {{ currentQuestionIndex + 1 }} / {{ totalQuestions }}
                    </span>
                    <span class="tabular-nums">
                        {{ answeredQuestions }} answered
                    </span>
                </div>
                <div
                    class="mt-3 h-1 w-full rounded-full bg-border-light/80 dark:bg-border-dark/90 overflow-hidden"
                    role="progressbar"
                    :aria-valuenow="currentQuestionIndex + 1"
                    :aria-valuemax="totalQuestions"
                    aria-label="Question progress"
                >
                    <div
                        class="h-full bg-accent-primary transition-[width] duration-300 ease-out rounded-full"
                        :style="{
                            width: `${totalQuestions ? ((currentQuestionIndex + 1) / totalQuestions) * 100 : 0}%`,
                        }"
                    />
                </div>
            </div>

            <form @submit.prevent="submitForm" class="space-y-0">
                <div v-if="currentQuestion" class="pb-6 sm:pb-8">
                    <p
                        class="text-[11px] sm:text-xs font-medium uppercase tracking-[0.12em] text-text-secondary mb-4 sm:mb-5"
                    >
                        Question {{ currentQuestionIndex + 1 }}
                    </p>
                    <h2
                        class="text-[clamp(1.0625rem,2.8vw,1.25rem)] font-medium text-text-primary dark:text-text-inverted leading-snug mb-8 sm:mb-10"
                    >
                        {{ currentQuestion.question }}
                    </h2>

                    <!-- Multiple choice: list + dividers, no option boxes -->
                    <div
                        v-if="currentQuestion.type === 'multiple_choice'"
                        class="divide-y divide-border-light dark:divide-border-dark -mx-1 sm:mx-0"
                    >
                        <label
                            v-for="(choice, choiceIndex) in getChoices(currentQuestion)"
                            :key="choiceIndex"
                            :class="[
                                'flex items-start gap-3 sm:gap-4 py-4 sm:py-[1.125rem] px-1 sm:px-2 rounded-lg cursor-pointer transition-colors min-h-[3.25rem] sm:min-h-0',
                                form.answers[currentQuestion.id]?.answer === choice
                                    ? 'bg-accent-primary/[0.06] dark:bg-accent-primary/10'
                                    : 'hover:bg-surface-muted/80 dark:hover:bg-surface-dark-muted/50',
                            ]"
                        >
                            <input
                                type="radio"
                                :name="`answer_${currentQuestion.id}`"
                                :value="choice"
                                :checked="form.answers[currentQuestion.id]?.answer === choice"
                                class="mt-1.5 shrink-0 w-4 h-4 text-accent-primary border-border-light dark:border-border-dark focus:ring-accent-primary focus:ring-offset-0"
                                @change="updateAnswer(currentQuestion.id, choice)"
                            />
                            <span
                                class="text-[15px] sm:text-base text-text-primary dark:text-text-inverted leading-relaxed flex-1"
                            >
                                {{ choice }}
                            </span>
                        </label>
                    </div>

                    <!-- Identification -->
                    <div v-else-if="currentQuestion.type === 'identification'" class="pt-1">
                        <label class="sr-only" :for="`id-${currentQuestion.id}`">Your answer</label>
                        <input
                            :id="`id-${currentQuestion.id}`"
                            :value="form.answers[currentQuestion.id]?.answer || ''"
                            type="text"
                            autocomplete="off"
                            placeholder="Type your answer"
                            class="w-full bg-transparent border-0 border-b border-border-light dark:border-border-dark px-0 py-3 text-base text-text-primary dark:text-text-inverted placeholder:text-text-secondary/70 focus:border-accent-primary focus:ring-0 focus:outline-none transition-colors rounded-none"
                            @input="updateAnswer(currentQuestion.id, $event.target.value)"
                        />
                    </div>

                    <!-- True / False: minimal pill pair -->
                    <div
                        v-else-if="currentQuestion.type === 'true_or_false'"
                        class="flex flex-col sm:flex-row gap-3 sm:gap-4 max-w-md"
                    >
                        <label
                            :class="[
                                'flex flex-1 items-center justify-center min-h-[52px] sm:min-h-[48px] rounded-xl cursor-pointer text-base font-medium transition-all',
                                form.answers[currentQuestion.id]?.answer === 'True'
                                    ? 'bg-accent-primary text-white shadow-sm'
                                    : 'bg-surface-muted/70 dark:bg-surface-dark-muted text-text-primary dark:text-text-inverted hover:bg-surface-muted dark:hover:bg-surface-dark-muted/80',
                            ]"
                        >
                            <input
                                type="radio"
                                :name="`answer_${currentQuestion.id}`"
                                value="True"
                                class="sr-only"
                                :checked="form.answers[currentQuestion.id]?.answer === 'True'"
                                @change="updateAnswer(currentQuestion.id, 'True')"
                            />
                            True
                        </label>
                        <label
                            :class="[
                                'flex flex-1 items-center justify-center min-h-[52px] sm:min-h-[48px] rounded-xl cursor-pointer text-base font-medium transition-all',
                                form.answers[currentQuestion.id]?.answer === 'False'
                                    ? 'bg-accent-primary text-white shadow-sm'
                                    : 'bg-surface-muted/70 dark:bg-surface-dark-muted text-text-primary dark:text-text-inverted hover:bg-surface-muted dark:hover:bg-surface-dark-muted/80',
                            ]"
                        >
                            <input
                                type="radio"
                                :name="`answer_${currentQuestion.id}`"
                                value="False"
                                class="sr-only"
                                :checked="form.answers[currentQuestion.id]?.answer === 'False'"
                                @change="updateAnswer(currentQuestion.id, 'False')"
                            />
                            False
                        </label>
                    </div>
                </div>

                <!-- Single nav block: sticky on small screens, inline on lg+ -->
                <div
                    class="mt-10 sm:mt-12 pt-6 sm:pt-8 border-t border-border-light dark:border-border-dark max-sm:fixed max-sm:inset-x-0 max-sm:bottom-0 max-sm:z-30 max-sm:mt-0 max-sm:pt-3 max-sm:px-4 max-sm:border-t max-sm:border-border-light/80 max-sm:dark:border-border-dark/80 max-sm:bg-surface/95 max-sm:dark:bg-surface-dark/95 max-sm:backdrop-blur-md max-sm:shadow-[0_-4px_24px_rgba(0,0,0,0.06)] max-sm:dark:shadow-[0_-4px_24px_rgba(0,0,0,0.25)] max-sm:pb-[max(0.75rem,env(safe-area-inset-bottom))]"
                >
                    <div
                        class="flex gap-1.5 overflow-x-auto pb-3 sm:pb-2 -mx-1 px-1 scroll-smooth max-sm:[scrollbar-width:none] max-sm:[-ms-overflow-style:none] max-sm:[&::-webkit-scrollbar]:hidden"
                    >
                        <button
                            v-for="(item, index) in items"
                            :key="item.id"
                            type="button"
                            :ref="(el) => setPaginationButtonRef(el, index)"
                            class="flex-shrink-0 w-9 h-9 sm:min-w-[2.25rem] rounded-full text-xs font-medium transition-all focus:outline-none focus-visible:ring-2 focus-visible:ring-accent-primary focus-visible:ring-offset-2 dark:focus-visible:ring-offset-surface-dark"
                            :class="[
                                index === currentQuestionIndex
                                    ? 'bg-accent-primary text-white shadow-sm'
                                    : form.answers[item.id]?.answer
                                        ? 'text-accent-primary dark:text-indigo-300 bg-accent-primary/10 dark:bg-accent-primary/15 hover:bg-accent-primary/15'
                                        : 'text-text-secondary bg-surface-muted/60 dark:bg-surface-dark-muted hover:bg-surface-muted dark:hover:bg-surface-dark-muted/90',
                            ]"
                            :title="`Question ${index + 1}`"
                            @click="goToQuestion(index)"
                        >
                            {{ index + 1 }}
                        </button>
                    </div>
                    <div class="flex items-center gap-2 sm:gap-4 sm:justify-between sm:pt-2">
                        <button
                            type="button"
                            class="flex-1 sm:flex-none inline-flex items-center justify-center min-h-[48px] sm:min-h-[44px] sm:px-5 rounded-full text-sm font-medium text-text-secondary border border-transparent sm:border-0 max-sm:border-border-light max-sm:dark:border-border-dark hover:text-text-primary dark:hover:text-text-inverted active:bg-surface-muted dark:active:bg-surface-dark-muted sm:hover:bg-transparent transition-colors disabled:opacity-35 disabled:pointer-events-none"
                            :disabled="isFirstQuestion"
                            @click="previousQuestion"
                        >
                            Back
                        </button>
                        <div class="flex flex-1 sm:flex-none items-center justify-end gap-3 sm:min-w-0">
                            <PrimaryButton
                                v-if="isLastQuestion"
                                type="submit"
                                class="w-full sm:w-auto min-h-[48px] sm:min-h-[44px] px-8 rounded-full justify-center !shadow-none sm:min-w-[7.5rem]"
                                :class="{ 'opacity-50 cursor-not-allowed': form.processing }"
                                :disabled="form.processing"
                            >
                                <span v-if="form.processing">Submitting…</span>
                                <span v-else>Submit</span>
                            </PrimaryButton>
                            <button
                                v-else
                                type="button"
                                class="w-full sm:w-auto min-h-[48px] sm:min-h-[44px] px-8 rounded-full text-sm font-medium bg-accent-primary text-white hover:bg-accent-muted active:bg-accent-muted transition-colors sm:min-w-[7.5rem]"
                                @click="nextQuestion"
                            >
                                Next
                            </button>
                        </div>
                    </div>
                </div>

                <InputError :message="form.errors.error" class="mt-4 sm:mt-6" />
            </form>
        </div>
    </StudentLayout>
</template>
