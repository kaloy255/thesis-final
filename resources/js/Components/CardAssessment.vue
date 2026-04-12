<script setup>
import { computed } from "vue";
import { Link, usePage } from "@inertiajs/vue3";

const props = defineProps({
    assessment: {
        type: Object,
        required: true,
    },
});

const page = usePage();

const hasAttempts = computed(() => props.assessment.attempt_count > 0);

const hasLessonFile = computed(() => !!props.assessment.lesson?.has_file);

const lessonDownloadUrl = computed(() =>
    route("student.assessments.lesson.download", props.assessment.id)
);

/** Matches Take.vue — draft answers before submit (same browser only) */
function localDraftHasAnswers(assessmentId, userId) {
    if (!userId || !assessmentId) {
        return false;
    }
    try {
        const key = `assessment_draft_${assessmentId}_${userId}`;
        const raw = localStorage.getItem(key);
        if (!raw) {
            return false;
        }
        const parsed = JSON.parse(raw);
        if (!parsed?.answers || typeof parsed.answers !== "object") {
            return false;
        }
        for (const item of Object.values(parsed.answers)) {
            if (
                item?.answer !== undefined &&
                item?.answer !== null &&
                String(item.answer).trim() !== ""
            ) {
                return true;
            }
        }
        return false;
    } catch {
        return false;
    }
}

const userId = computed(() => page.props.auth?.user?.id ?? null);

const hasLocalDraft = computed(() =>
    localDraftHasAnswers(props.assessment.id, userId.value)
);

/** In-progress: timed session not cleared yet, or saved draft in this browser */
const isOngoing = computed(
    () => !!props.assessment.has_timer_session || hasLocalDraft.value
);

const actionLabel = computed(() => {
    if (isOngoing.value) {
        return "Continue Assessment";
    }
    if (hasAttempts.value) {
        return "Retake Assessment";
    }
    return "Take Assessment";
});

const mobileActionLabel = computed(() => {
    if (isOngoing.value) {
        return "Continue";
    }
    if (hasAttempts.value) {
        return "Retake";
    }
    return "Take";
});

const formattedLastAttempt = computed(() => {
    if (!props.assessment.last_attempt_at) return null;

    const date = new Date(props.assessment.last_attempt_at);
    return date.toLocaleDateString("en-US", {
        year: "numeric",
        month: "short",
        day: "numeric",
        hour: "2-digit",
        minute: "2-digit",
    });
});

const cardHeaderImages = [
    "/images/images/card-images/card-picture-1.png",
    "/images/images/card-images/card-picture-2.png",
    "/images/images/card-images/card-picture-3.png",
    "/images/images/card-images/card-picture-4.png",
    "/images/images/card-images/card-picture-5.png",
];

const headerImageStyle = computed(() => {
    const id = Number(props.assessment.id);
    const imageIndex = Number.isFinite(id)
        ? Math.abs(id) % cardHeaderImages.length
        : 0;
    const imageUrl = cardHeaderImages[imageIndex];

    return {
        backgroundImage: `linear-gradient(to right, rgba(255, 255, 255, 0.18), rgba(255, 255, 255, 0.08)), url(${imageUrl})`,
        backgroundSize: "cover",
        backgroundPosition: "center",
    };
});
</script>

<template>
    <article
        class="card min-w-0 overflow-hidden rounded-2xl border border-border-light dark:border-border-dark hover:shadow-lg transition-all duration-200"
    >
        <div
            class="h-28 p-4 flex items-start"
            :style="headerImageStyle"
        >
            <span
                class="inline-flex items-center rounded-full bg-white/80 dark:bg-black/20 px-3 py-1 text-xs font-medium text-text-primary dark:text-text-inverted"
            >
                {{ assessment.item_count }} Questions
            </span>
        </div>

        <div class="p-5 min-w-0">
            <h3
                class="text-xl font-semibold text-text-primary dark:text-text-inverted leading-tight mb-2 break-words"
            >
                {{ assessment.title }}
            </h3>

            <p class="text-sm text-text-secondary mb-1">
                {{ assessment.subject.name }} ({{ assessment.subject.code }})
            </p>
            <p class="text-sm text-text-secondary mb-4 break-words">
                {{ assessment.lesson.title }}
            </p>

            <div
                class="text-sm text-text-secondary border-t border-border-light dark:border-border-dark pt-3"
            >
                <span v-if="hasAttempts">
                    Attempts: {{ assessment.attempt_count }}
                    <span v-if="formattedLastAttempt">
                        - Last: {{ formattedLastAttempt }}
                    </span>
                </span>
                <span v-else>No attempts yet</span>
            </div>

            <div
                :class="[
                    'mt-4 grid min-w-0 gap-2',
                    hasLessonFile
                        ? 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-3'
                        : 'grid-cols-1 sm:grid-cols-2',
                ]"
            >
                <a
                    v-if="hasLessonFile"
                    :href="lessonDownloadUrl"
                    class="min-w-0 w-full inline-flex items-center justify-center px-3 py-2.5 border border-border-light dark:border-border-dark bg-surface dark:bg-surface-dark-muted text-text-primary dark:text-text-inverted text-sm font-medium text-center leading-snug rounded-lg hover:bg-gray-50 dark:hover:bg-slate-800 transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                >
                    <span class="sm:hidden">Download</span>
                    <span class="hidden sm:inline">Download lesson</span>
                </a>

                <!-- History is available even with zero attempts (empty state on history page). -->
                <Link
                    :href="route('student.assessments.history', assessment.id)"
                    class="min-w-0 w-full inline-flex items-center justify-center px-3 py-2.5 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 text-sm font-medium text-center leading-snug rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2"
                >
                    <span class="sm:hidden">History</span>
                    <span class="hidden sm:inline">View History</span>
                </Link>
                <Link
                    :href="route('student.assessments.show', assessment.id)"
                    class="min-w-0 w-full inline-flex items-center justify-center px-3 py-2.5 bg-accent-primary text-white text-sm font-medium text-center leading-snug rounded-lg hover:bg-accent-muted transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-accent-primary focus:ring-offset-2"
                >
                    <span class="sm:hidden">{{ mobileActionLabel }}</span>
                    <span class="hidden sm:inline">{{ actionLabel }}</span>
                </Link>
            </div>
        </div>
    </article>
</template>
