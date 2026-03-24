<script setup>
import AdminLayout from "@/Layouts/AdminLayout.vue";
import InputError from "@/Components/InputError.vue";
import InputLabel from "@/Components/InputLabel.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import SearchableSelect from "@/Components/SearchableSelect.vue";
import Pagination from "@/Components/Pagination.vue";
import Modal from "@/Components/Modal.vue";
import { Head, router, useForm } from "@inertiajs/vue3";
import { ref, computed, watch, onMounted, onBeforeUnmount } from "vue";
import { useToast } from "@/Stores/useToast";
import ConfirmationModal from "@/Components/ConfirmationModal.vue";
import { Icon } from "@iconify/vue";

const props = defineProps({
    subjects: Object,
    subjectOptions: Array,
    professors: Array,
    filters: Object,
});

const { success, error, warning } = useToast();

// Search
const searchQuery = ref(props.filters?.search || "");

// Subject filter (dropdown to filter by specific subject)
const subjectFilterId = ref(
    props.filters?.subject_id != null && props.filters.subject_id !== ""
        ? String(props.filters.subject_id)
        : ""
);
let searchTimeout = null;

const subjectFilterOptions = computed(() => [
    { value: "", label: "All subjects" },
    ...(props.subjectOptions || []).map((s) => ({
        value: String(s.id),
        label: `(${s.assignments_count || 0}) ${s.code} — ${s.name}`,
    })),
]);

const hasSubjects = computed(() => props.subjects?.data?.length > 0);
const hasActiveFilters = computed(
    () => searchQuery.value || subjectFilterId.value
);
const detailsSectionRef = ref(null);
const isDetailsStuck = ref(false);

const applyFilters = () => {
    router.get(route("admin.assignments.index"), {
        search: searchQuery.value || undefined,
        subject_id: subjectFilterId.value || undefined,
        per_page: props.filters?.per_page || 10,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

watch(subjectFilterId, applyFilters);

watch(searchQuery, () => {
    if (searchTimeout) clearTimeout(searchTimeout);
    searchTimeout = setTimeout(applyFilters, 400);
});

const updateStickyState = () => {
    const el = detailsSectionRef.value;
    if (!el) return;
    const stickyTop = Number.parseFloat(window.getComputedStyle(el).top || "0") || 0;
    isDetailsStuck.value = el.getBoundingClientRect().top <= stickyTop + 0.5;
};

onMounted(() => {
    updateStickyState();
    window.addEventListener("scroll", updateStickyState, { passive: true });
    window.addEventListener("resize", updateStickyState);
});

onBeforeUnmount(() => {
    window.removeEventListener("scroll", updateStickyState);
    window.removeEventListener("resize", updateStickyState);
});

// Assign modal
const showAssignModal = ref(false);
const assignSubjectName = ref("");
const assignForm = useForm({
    professor_id: "",
    subject_id: "",
});

// Import modal
const showImportModal = ref(false);
const importErrors = ref([]);
const importErrorMessage = ref("");
const isImportDragging = ref(false);
const importFileName = ref("");

const importForm = useForm({
    file: null,
});

const openImportModal = () => {
    showImportModal.value = true;
    importErrors.value = [];
};

const closeImportModal = () => {
    showImportModal.value = false;
    importForm.reset();
    importForm.clearErrors();
    importErrors.value = [];
    importErrorMessage.value = "";
    importFileName.value = "";
};

const handleImportFileChange = (event) => {
    const file = event.target.files[0];
    if (file) {
        importForm.file = file;
        importFileName.value = file.name;
    }
};

const handleImportDrop = (event) => {
    isImportDragging.value = false;
    event.preventDefault();
    const file = event.dataTransfer?.files?.[0];
    if (file && /\.(xlsx|xls|csv)$/i.test(file.name)) {
        importForm.file = file;
        importFileName.value = file.name;
    }
};

const downloadTemplate = () => {
    window.location.href = route("admin.assignments.template");
};

const getImportErrorMessage = (errors) => {
    if (!errors) return null;
    const first = (field) => {
        const v = errors[field];
        return Array.isArray(v) ? v[0] : typeof v === "string" ? v : null;
    };
    return first("file") || first("error") || "Failed to import. Please check your file.";
};

const submitImport = () => {
    importErrorMessage.value = "";
    importForm.post(route("admin.assignments.import"), {
        preserveScroll: true,
        onSuccess: (response) => {
            const flash = response.props.flash;
            if (flash?.type === "error") {
                importErrorMessage.value = flash.message || "Import failed. Please try again.";
                if (flash.errors) importErrors.value = flash.errors;
                error(flash.message);
                return;
            }
            if (flash?.errors?.length > 0) {
                importErrors.value = flash.errors;
            }
            closeImportModal();
            if (flash?.type === "success") success(flash.message);
            else if (flash?.type === "warning") warning(flash.message);
            else success("Assignments imported successfully");
        },
        onError: (errors) => {
            const msg = getImportErrorMessage(errors);
            importErrorMessage.value = msg;
            error(msg);
        },
    });
};

// Options for the SearchableSelect
const professorOptions = computed(() =>
    props.professors.map((p) => ({
        value: p.id,
        label: p.user?.name || "Unknown",
        sublabel: p.department?.name || "No Department",
    }))
);

const openAssignModal = (subject) => {
    assignSubjectName.value = `${subject.code} — ${subject.name}`;
    assignForm.subject_id = subject.id;
    assignForm.professor_id = "";
    showAssignModal.value = true;
};

const closeAssignModal = () => {
    showAssignModal.value = false;
    assignForm.reset();
    assignForm.clearErrors();
};

const submitAssign = () => {
    assignForm.post(route("admin.assignments.store"), {
        preserveScroll: true,
        onSuccess: () => {
            closeAssignModal();
            success("Instructor assigned successfully");
        },
        onError: () => {
            error("Failed to assign instructor");
        },
    });
};

// Remove
const showDeleteModal = ref(false);
const assignmentToDelete = ref(null);

const openDeleteModal = (id) => {
    assignmentToDelete.value = id;
    showDeleteModal.value = true;
};

const closeDeleteModal = () => {
    showDeleteModal.value = false;
    assignmentToDelete.value = null;
};

const confirmDelete = () => {
    router.delete(
        route("admin.assignments.destroy", assignmentToDelete.value),
        {
            preserveScroll: true,
            onSuccess: () => {
                closeDeleteModal();
                success("Instructor removed successfully");
            },
            onError: () => {
                error("Failed to remove instructor");
            },
        }
    );
};

// Available professors for a subject (exclude already assigned)
const availableProfessors = (subject) => {
    const assignedIds = subject.assignments.map((a) => a.professor_id);
    return props.professors.filter((p) => !assignedIds.includes(p.id));
};

const formatDate = (dateString) => {
    if (!dateString) return "";
    return new Date(dateString).toLocaleDateString("en-US", {
        year: "numeric",
        month: "short",
        day: "numeric",
    });
};
</script>

<template>
    <AdminLayout>
        <Head title="Assignments" />

        <!-- Sticky Header + Filters -->
        <div ref="detailsSectionRef" class="sticky top-[64px] z-40 mb-6 pb-2">
            <div
                :class="[
                    'rounded-b-xl rounded-t-none transition-all duration-200',
                    isDetailsStuck
                        ? 'px-3 py-2 sm:p-4 bg-white/95 dark:bg-slate-900/95 border border-border-light dark:border-slate-700 shadow-md backdrop-blur-sm'
                        : 'p-3 sm:p-4 bg-transparent border border-transparent shadow-none',
                ]"
            >
                <!-- Header -->
                <div :class="isDetailsStuck ? 'mb-2 sm:mb-4' : 'mb-4'">
                    <div :class="['flex flex-col sm:flex-row sm:items-center justify-between', isDetailsStuck ? 'gap-2 sm:gap-4' : 'gap-4']">
                        <div class="w-full sm:w-auto">
                            <h1 :class="['font-semibold text-text-primary dark:text-text-inverted', isDetailsStuck ? 'text-xl sm:text-2xl mb-0.5 sm:mb-1' : 'text-2xl mb-1']">
                                Assignments
                            </h1>
                            <p :class="['text-text-secondary', isDetailsStuck ? 'text-xs sm:text-sm' : 'text-sm']">
                                Manage instructor assignments per subject
                            </p>
                        </div>
                        <div class="grid grid-cols-2 sm:flex sm:flex-row items-stretch sm:items-center gap-2 w-full sm:w-auto">
                            <button
                                @click="openImportModal"
                                :class="['col-span-2 sm:col-auto inline-flex w-full sm:w-auto justify-center items-center px-4 bg-surface dark:bg-surface-dark-muted text-text-secondary border border-border-light dark:border-border-dark text-sm font-medium rounded-lg hover:bg-gray-50 dark:hover:bg-white/5 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition-colors duration-200', isDetailsStuck ? 'gap-1.5 py-2' : 'gap-2 py-2.5']"
                            >
                                <svg :class="isDetailsStuck ? 'w-4 h-4' : 'w-5 h-5'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                                </svg>
                                Import Assigned
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div :class="['flex flex-col sm:flex-row', isDetailsStuck ? 'gap-2 sm:gap-4' : 'gap-4']">
                    <div class="flex-1 min-w-0">
                        <label :class="['block text-xs font-medium text-text-secondary', isDetailsStuck ? 'mb-1' : 'mb-1.5']">
                            Search
                        </label>
                        <div class="relative">
                            <svg
                                class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
                                />
                            </svg>
                            <input
                                v-model="searchQuery"
                                type="text"
                                placeholder="Search subjects or instructors..."
                                :class="['w-full pl-10 pr-4 text-sm border border-border-light dark:border-border-dark rounded-lg bg-surface dark:bg-surface-dark-muted text-text-primary dark:text-text-inverted placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/40 focus:border-indigo-500 transition-all', isDetailsStuck ? 'py-2' : 'py-2.5']"
                            />
                        </div>
                    </div>
                    <div class="w-full sm:w-64">
                        <label :class="['block text-xs font-medium text-text-secondary', isDetailsStuck ? 'mb-1' : 'mb-1.5']">
                            Filter by subject
                        </label>
                        <SearchableSelect
                            v-model="subjectFilterId"
                            :options="subjectFilterOptions"
                            placeholder="Filter by subject..."
                        />
                    </div>
                </div>
            </div>
        </div>

        <!-- Subject Cards Grid -->
        <div
            v-if="hasSubjects"
            class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5"
        >
            <div
                v-for="subject in props.subjects.data"
                :key="subject.id"
                class="bg-surface dark:bg-surface-dark-muted rounded-xl border border-border-light dark:border-border-dark overflow-hidden flex flex-col min-w-0"
            >
                <!-- Card Header -->
                <div class="px-5 pt-5 pb-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <span class="text-xs font-mono font-medium text-indigo-600 dark:text-indigo-400">
                                {{ subject.code }}
                            </span>
                            <h3 class="text-base font-semibold text-text-primary dark:text-text-inverted mt-0.5 leading-snug">
                                {{ subject.name }}
                            </h3>
                        </div>
                        <span class="flex-shrink-0 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 dark:bg-surface-dark-muted text-text-secondary">
                            {{ subject.assignments.length }} instructor{{ subject.assignments.length !== 1 ? 's' : '' }}
                        </span>
                    </div>
                </div>

                <!-- Assigned Instructors -->
                <div class="flex-1 px-5 pb-3">
                    <!-- Empty state -->
                    <div
                        v-if="subject.assignments.length === 0"
                        class="py-6 text-center"
                    >
                        <Icon icon="simple-line-icons:people" class="w-5 h-5 text-text-secondary mx-auto mb-4" />
                        <p class="text-xs text-text-secondary">
                            No instructor assigned
                        </p>
                    </div>

                    <!-- Instructor list -->
                    <div v-else class="space-y-2">
                        <div
                            v-for="assignment in subject.assignments"
                            :key="assignment.id"
                            class="group flex items-center justify-between gap-2 py-2 px-3 -mx-1 rounded-lg hover:bg-gray-50 dark:hover:bg-white/5 transition-colors"
                        >
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="w-8 h-8 rounded-full bg-indigo-100 dark:bg-indigo-900/40 text-indigo-600 dark:text-indigo-400 flex items-center justify-center text-xs font-bold flex-shrink-0">
                                    {{ assignment.professor_name.charAt(0).toUpperCase() }}
                                </div>
                                <div class="min-w-0">
                                    <p class="text-sm font-medium text-text-primary dark:text-text-inverted truncate">
                                        {{ assignment.professor_name }}
                                    </p>
                                    <p class="text-xs text-text-secondary truncate">
                                        {{ assignment.department_name }}
                                    </p>
                                </div>
                            </div>
                            <button
                                @click="openDeleteModal(assignment.id)"
                                class="opacity-100 sm:opacity-0 sm:group-hover:opacity-100 inline-flex items-center justify-center w-9 h-9 sm:w-auto sm:h-auto sm:p-1.5 text-gray-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-md transition-all"
                                title="Remove"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Card Footer — Add Button -->
                <div class="px-5 py-3 border-t border-gray-100 dark:border-border-dark">
                    <button
                        @click="openAssignModal(subject)"
                        class="w-full flex items-center justify-center gap-1.5 py-2 text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-900/20 rounded-lg transition-colors"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                        </svg>
                        Assign Instructor
                    </button>
                </div>
            </div>
        </div>
        <div v-if="hasSubjects" class="mt-5 bg-surface dark:bg-surface-dark-muted rounded-xl border border-border-light dark:border-border-dark overflow-hidden">
            <Pagination
                :links="props.subjects.links || []"
                :current-page="props.subjects.current_page || 1"
                :last-page="props.subjects.last_page || 1"
                :per-page="props.filters?.per_page || 10"
                :total="props.subjects.total || 0"
                :from="props.subjects.from || 0"
                :to="props.subjects.to || 0"
                route-name="admin.assignments.index"
                :filters="{
                    search: searchQuery || props.filters?.search || '',
                    subject_id: subjectFilterId || props.filters?.subject_id || '',
                }"
            />
        </div>

        <!-- No Results -->
        <div
            v-else
            class="bg-surface dark:bg-surface-dark-muted min-h-[calc(100vh-330px)] flex justify-center items-center rounded-xl border border-border-light dark:border-border-dark p-12 text-center"
        >
            <div class="" >
                <svg
                    class="mx-auto h-12 w-12 text-gray-300 dark:text-gray-600 mb-3"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="1.5"
                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
                    />
                </svg>
                <h3 class="text-sm font-medium text-text-primary dark:text-text-inverted mb-1">
                    No subjects found
                </h3>
                <p class="text-sm text-text-secondary">
                    {{ hasActiveFilters ? "Try adjusting your filters or search query." : "No subjects in database yet." }}
                </p>
            </div>
          
        </div>

        <!-- Assign Instructor Modal -->
        <Modal :show="showAssignModal" @close="closeAssignModal">
            <div class="p-6">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h2 class="text-lg font-semibold text-text-primary dark:text-text-inverted">
                            Assign Instructor
                        </h2>
                        <p class="text-sm text-text-secondary mt-0.5">
                            {{ assignSubjectName }}
                        </p>
                    </div>
                    <button
                        @click="closeAssignModal"
                        class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300 transition-colors"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <form @submit.prevent="submitAssign" class="space-y-5">
                    <div>
                        <InputLabel value="Instructor" class="mb-2" />
                        <SearchableSelect
                            v-model="assignForm.professor_id"
                            :options="professorOptions"
                            placeholder="Search instructor by name or department..."
                        />
                        <InputError class="mt-2" :message="assignForm.errors.professor_id" />
                    </div>
                    <div class="flex flex-col-reverse sm:flex-row justify-end gap-3 pt-4 border-t border-border-light dark:border-border-dark">
                        <SecondaryButton type="button" @click="closeAssignModal" class="px-4 py-2 w-full sm:w-auto">
                            Cancel
                        </SecondaryButton>
                        <PrimaryButton :disabled="assignForm.processing" class="px-4 py-2 w-full sm:w-auto">
                            Assign
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- Delete Confirmation Modal -->
        <ConfirmationModal
            :show="showDeleteModal"
            title="Remove Instructor"
            message="Are you sure you want to remove this instructor from the subject? This action cannot be undone."
            confirm-text="Remove"
            cancel-text="Cancel"
            variant="danger"
            @close="closeDeleteModal"
            @confirm="confirmDelete"
        />

        <!-- Import Assignments Modal -->
        <Modal :show="showImportModal" @close="closeImportModal">
            <div class="p-6">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-xl font-semibold text-text-primary dark:text-text-inverted">
                        Import Assignments
                    </h2>
                    <button
                        @click="closeImportModal"
                        class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300 transition-colors"
                    >
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <form @submit.prevent="submitImport" class="space-y-6">
                    <!-- Error message banner -->
                    <div
                        v-if="importErrorMessage"
                        class="p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg flex items-start gap-3"
                    >
                        <svg class="w-5 h-5 text-red-500 dark:text-red-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-red-800 dark:text-red-200">
                                {{ importErrorMessage }}
                            </p>
                        </div>
                        <button
                            type="button"
                            @click="importErrorMessage = ''"
                            class="text-red-500 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300 shrink-0"
                            aria-label="Dismiss"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    <div>
                        <p class="text-sm text-text-secondary mb-4">
                            Upload a spreadsheet. The file must contain <strong>Subject Code</strong> (or <strong>CODE</strong>) and an Instructor <strong>Email</strong>.
                        </p>
                        <InputLabel for="import_file" value="Select File" class="mb-2" />
                        <div
                            class="mt-1 relative overflow-hidden rounded-lg p-[2px]"
                            @dragover.prevent="isImportDragging = true"
                            @dragleave.prevent="isImportDragging = false"
                            @drop.prevent="handleImportDrop"
                        >
                            <div
                                class="drop-zone-beam"
                                :class="{ 'drop-zone-beam--full': importForm.file }"
                            />
                            <div
                                class="relative flex justify-center px-6 pt-5 pb-6 rounded-[calc(0.5rem-2px)] transition-colors"
                                :class="isImportDragging
                                    ? 'bg-indigo-50 dark:bg-indigo-900/20'
                                    : 'bg-surface dark:bg-surface-dark-muted'"
                            >
                            <div class="space-y-1 text-center">
                                <svg
                                    class="mx-auto h-12 w-12 text-text-secondary"
                                    stroke="currentColor"
                                    fill="none"
                                    viewBox="0 0 48 48"
                                >
                                    <path
                                        d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02"
                                        stroke-width="2"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                    />
                                </svg>
                                <div class="flex text-sm text-text-secondary justify-center">
                                    <label
                                        for="import_file"
                                        class="relative cursor-pointer rounded-md font-medium text-indigo-600 dark:text-indigo-400 hover:text-indigo-500 dark:hover:text-indigo-300 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-indigo-500"
                                    >
                                        <span>Upload a file</span>
                                        <input
                                            id="import_file"
                                            type="file"
                                            class="sr-only"
                                            accept=".xlsx,.xls,.csv"
                                            @change="handleImportFileChange"
                                        />
                                    </label>
                                    <p class="pl-1">or drag and drop</p>
                                </div>
                                <p class="text-xs text-text-secondary">
                                    XLSX, XLS, CSV up to 2MB
                                </p>
                                <p
                                    v-if="importFileName"
                                    class="text-sm font-medium text-text-secondary mt-2"
                                >
                                    Selected: {{ importFileName }}
                                </p>
                            </div>
                            </div>
                        </div>
                        <InputError class="mt-2" :message="importForm.errors.file" />
                        <button
                            type="button"
                            @click="downloadTemplate"
                            class="mt-2 text-sm text-indigo-600 dark:text-indigo-400 hover:text-indigo-700 dark:hover:text-indigo-300 font-medium"
                        >
                            Download template
                        </button>
                    </div>
                    <div v-if="importErrors.length > 0" class="p-3 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg max-h-32 overflow-y-auto">
                        <p class="text-xs font-medium text-amber-800 dark:text-amber-300 mb-2">Skipped rows:</p>
                        <ul class="text-xs text-amber-700 dark:text-amber-400 space-y-1">
                            <li v-for="(err, idx) in importErrors.slice(0, 10)" :key="idx">{{ err }}</li>
                            <li v-if="importErrors.length > 10" class="text-amber-600 dark:text-amber-500">... and {{ importErrors.length - 10 }} more</li>
                        </ul>
                    </div>
                    <div class="flex flex-col-reverse sm:flex-row justify-end gap-3 pt-4 border-t border-border-light dark:border-border-dark">
                        <SecondaryButton type="button" @click="closeImportModal" class="px-4 py-2 w-full sm:w-auto">
                            Cancel
                        </SecondaryButton>
                        <PrimaryButton
                            type="submit"
                            :disabled="!importForm.file || importForm.processing"
                            class="px-4 py-2 w-full sm:w-auto"
                        >
                            {{ importForm.processing ? "Importing..." : "Import" }}
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>
    </AdminLayout>
</template>

<style scoped>
.drop-zone-beam {
    position: absolute;
    top: 50%;
    left: 50%;
    width: 200vmax;
    height: 200vmax;
    margin-left: -100vmax;
    margin-top: -100vmax;
    background: conic-gradient(
        from 0deg,
        transparent 0deg,
        #3238a8 20deg,
        #00c8ff 40deg,
        transparent 60deg,
        transparent 60deg 150deg,
        transparent 150deg,
        #3238a8 170deg,
        #00c8ff 190deg,
        transparent 210deg,
        transparent 210deg 360deg
    );
    animation: border-beam-rotate 3s linear infinite;
    will-change: transform;
    border-radius: 50%;
}

.drop-zone-beam--full {
    background: conic-gradient(
        from 0deg,
        #3238a8,
        #00c8ff,
        #3238a8,
        #00c8ff,
        #3238a8
    );
}

@keyframes border-beam-rotate {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
</style>
