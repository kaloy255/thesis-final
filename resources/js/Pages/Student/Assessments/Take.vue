<script setup>
import StudentLayout from "@/Layouts/StudentLayout.vue";
import { Head, useForm } from "@inertiajs/vue3";
import { computed, ref, watch, nextTick, onMounted, onUnmounted } from "vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import InputError from "@/Components/InputError.vue";
import axios from "axios";

const props = defineProps({
    assessment: Object,
    items: Array,
});

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

// Initialize form answers and ensure choices is always an array
props.items?.forEach((item) => {
    form.answers[item.id] = { answer: "" };

    // Ensure choices is an array for multiple choice questions
    if (item.type === 'multiple_choice' && item.choices) {
        if (typeof item.choices === 'string') {
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

const updateAnswer = (itemId, answer) => {
    form.answers[itemId] = { answer };
};

// Helper to get choices as array
const getChoices = (item) => {
    if (!item.choices) return [];
    if (Array.isArray(item.choices)) return item.choices;
    if (typeof item.choices === 'string') {
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

// Refs for pagination scroll-into-view
const paginationButtonRefs = ref([]);
const setPaginationButtonRef = (el, index) => {
    if (el) {
        paginationButtonRefs.value[index] = el;
    }
};

// Keep current question button visible when navigating
watch(currentQuestionIndex, async () => {
    await nextTick();
    const btn = paginationButtonRefs.value[currentQuestionIndex.value];
    if (btn) {
        btn.scrollIntoView({ behavior: "smooth", block: "nearest", inline: "center" });
    }
}, { immediate: true });

const isSubmittingAssessment = ref(false);

const submitForm = () => {
    if (
        unansweredQuestions.value > 0 &&
        !confirm(
            `You have ${unansweredQuestions.value} unanswered question(s). Do you want to submit anyway?`
        )
    ) {
        return;
    }

    isSubmittingAssessment.value = true;
    form.post(route("student.assessments.store", props.assessment.id), {
        preserveScroll: true,
        onFinish: () => {
            isSubmittingAssessment.value = false;
        },
    });
};

// ===================== Cheating Detection =====================

const lastEventTime = ref({});
const DEBOUNCE_MS = 5000; // 5 seconds debounce per event type

const logCheatingEvent = async (eventType) => {
    const now = Date.now();
    if (lastEventTime.value[eventType] && now - lastEventTime.value[eventType] < DEBOUNCE_MS) {
        return; // debounce: skip if same event fired within 5s
    }
    lastEventTime.value[eventType] = now;
    violationCount.value++;

    try {
        await axios.post(route("student.assessments.cheating-log", props.assessment.id), {
            event_type: eventType,
        });
    } catch (e) {
        // silently fail – don't block the student
    }
};

const handleVisibilityChange = () => {
    if (document.hidden) {
        logCheatingEvent('tab_switch');
    }
};

const handleWindowBlur = () => {
    logCheatingEvent('window_blur');
};

const handleBeforeUnload = (e) => {
    // Don't log as cheating when user is legitimately submitting the assessment
    if (isSubmittingAssessment.value) return;

    logCheatingEvent('page_leave');
    e.preventDefault();
    e.returnValue = '';
};

onMounted(() => {
    document.addEventListener('visibilitychange', handleVisibilityChange);
    window.addEventListener('blur', handleWindowBlur);
    window.addEventListener('beforeunload', handleBeforeUnload);
});

onUnmounted(() => {
    document.removeEventListener('visibilitychange', handleVisibilityChange);
    window.removeEventListener('blur', handleWindowBlur);
    window.removeEventListener('beforeunload', handleBeforeUnload);
});
</script>

<template>
    <StudentLayout>
        <Head :title="assessment.title" />

        <div
            class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 pb-36 sm:pb-10 pt-4 sm:pt-6"
        >
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
                    Tab switches and leaving this page may be logged for your instructor.
                </p>
            </div>

            <!-- Title + meta: typography only -->
            <header class="mb-8 sm:mb-10">
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
