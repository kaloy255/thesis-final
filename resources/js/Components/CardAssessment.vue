<script setup>
import { computed } from "vue";
import { Link } from "@inertiajs/vue3";

const props = defineProps({
    assessment: {
        type: Object,
        required: true,
    },
});

const hasAttempts = computed(() => props.assessment.attempt_count > 0);

const hasLessonFile = computed(() => !!props.assessment.lesson?.has_file);

const lessonDownloadUrl = computed(() =>
    route("student.assessments.lesson.download", props.assessment.id)
);

const actionLabel = computed(() =>
    hasAttempts.value ? "Retake Assessment" : "Take Assessment"
);

const mobileActionLabel = computed(() =>
    hasAttempts.value ? "Retake" : "Take"
);

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
        class="card overflow-hidden rounded-2xl border border-border-light dark:border-border-dark hover:shadow-lg transition-all duration-200"
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

        <div class="p-5">
            <h3
                class="text-xl font-semibold text-text-primary dark:text-text-inverted leading-tight mb-2"
            >
                {{ assessment.title }}
            </h3>

            <p class="text-sm text-text-secondary mb-1">
                {{ assessment.subject.name }} ({{ assessment.subject.code }})
            </p>
            <p class="text-sm text-text-secondary mb-4">
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
                    'mt-4 gap-2',
                    hasLessonFile
                        ? 'grid grid-cols-1 sm:grid-cols-3'
                        : 'grid grid-cols-2 sm:flex sm:items-center sm:justify-end',
                ]"
            >
                <a
                    v-if="hasLessonFile"
                    :href="lessonDownloadUrl"
                    class="w-full inline-flex items-center justify-center px-3 py-2 border border-border-light dark:border-border-dark bg-surface dark:bg-surface-dark-muted text-text-primary dark:text-text-inverted text-sm font-medium rounded-lg hover:bg-gray-50 dark:hover:bg-slate-800 transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 whitespace-nowrap"
                >
                    <span class="sm:hidden">Download</span>
                    <span class="hidden sm:inline">Download lesson</span>
                </a>

                <!-- History is available even with zero attempts (empty state on history page). -->
                <Link
                    :href="route('student.assessments.history', assessment.id)"
                    class="w-full inline-flex items-center justify-center px-3 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 text-sm font-medium rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 whitespace-nowrap"
                >
                    <span class="sm:hidden">History</span>
                    <span class="hidden sm:inline">View History</span>
                </Link>
                <Link
                    :href="route('student.assessments.show', assessment.id)"
                    :class="[
                        'inline-flex items-center justify-center px-3 py-2 bg-accent-primary text-white text-sm font-medium rounded-lg hover:bg-accent-muted transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-accent-primary focus:ring-offset-2 whitespace-nowrap w-full',
                    ]"
                >
                    <span class="sm:hidden">{{ mobileActionLabel }}</span>
                    <span class="hidden sm:inline">{{ actionLabel }}</span>
                </Link>
            </div>
        </div>
    </article>
</template>
