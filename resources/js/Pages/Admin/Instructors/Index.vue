<script setup>
import AdminLayout from "@/Layouts/AdminLayout.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import Modal from "@/Components/Modal.vue";
import InputError from "@/Components/InputError.vue";
import InputLabel from "@/Components/InputLabel.vue";
import TextInput from "@/Components/TextInput.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import ConfirmationModal from "@/Components/ConfirmationModal.vue";
import Pagination from "@/Components/Pagination.vue";
import SearchableSelect from "@/Components/SearchableSelect.vue";
import { Head, Link, router, useForm } from "@inertiajs/vue3";
import { ref, watch, computed, onMounted, onBeforeUnmount } from "vue";
import { useToast } from "@/Stores/useToast";
import { Icon } from "@iconify/vue";

const props = defineProps({
    instructors: Object,
    departments: Array,
    filters: Object,
});

const { success, error, warning } = useToast();

const showCreateModal = ref(false);
const showEditModal = ref(false);
const showImportModal = ref(false);
const showResetPasswordModal = ref(false);
const showDeleteModal = ref(false);
const editingInstructor = ref(null);
const instructorToDelete = ref(null);
const searchQuery = ref(props.filters?.search || "");
const departmentFilterId = ref(
    props.filters?.department_id != null && props.filters.department_id !== ""
        ? String(props.filters.department_id)
        : ""
);
const detailsSectionRef = ref(null);
const isDetailsStuck = ref(false);
const STICKY_RELEASE_OFFSET = 8;
const importErrors = ref([]);
const importErrorMessage = ref("");
const isImportDragging = ref(false);
const importFileName = ref("");
let searchTimeout = null;

const hasInstructors = computed(() => props.instructors.data?.length > 0);
const hasActiveFilters = computed(
    () => searchQuery.value || departmentFilterId.value
);

const departmentFilterOptions = computed(() => [
    { value: "", label: "All departments" },
    ...(props.departments || []).map((d) => ({
        value: String(d.id),
        label: d.professors_count !== undefined ? `(${d.professors_count}) ${d.name}` : d.name,
    })),
]);

const applyFilters = () => {
    router.get(route("admin.instructors.index"), {
        search: searchQuery.value,
        department_id: departmentFilterId.value || undefined,
        per_page: props.filters?.per_page || 10,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

watch(departmentFilterId, applyFilters);
watch(searchQuery, () => {
    if (searchTimeout) clearTimeout(searchTimeout);
    searchTimeout = setTimeout(applyFilters, 500);
});

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

onMounted(() => {
    updateStickyState();
    window.addEventListener("scroll", updateStickyState, { passive: true });
    window.addEventListener("resize", updateStickyState);
});

onBeforeUnmount(() => {
    window.removeEventListener("scroll", updateStickyState);
    window.removeEventListener("resize", updateStickyState);
});

const createForm = useForm({
    email: "",
    name: "",
    department_id: "",
});

const editForm = useForm({
    email: "",
    name: "",
    department_id: "",
});

const importForm = useForm({
    file: null,
    department_id: "",
});

const departmentOptions = computed(() =>
    (props.departments || []).map((d) => ({
        value: d.id,
        label: d.name,
    }))
);

const openCreateModal = () => {
    showCreateModal.value = true;
};

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
    isImportDragging.value = false;
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
    window.location.href = route("admin.instructors.template");
};

const submitImport = () => {
    importErrorMessage.value = "";
    importForm.post(route("admin.instructors.import"), {
        preserveScroll: true,
        onSuccess: (response) => {
            const flash = response.props.flash;

            if (flash?.type === "error") {
                importErrorMessage.value = flash.message || "Import failed. Please try again.";
                if (flash.errors) importErrors.value = flash.errors;
                error(flash.message);
                return;
            }

            if (flash?.errors && flash.errors.length > 0) {
                importErrors.value = flash.errors;
                if (flash.type === "success") {
                    success(flash.message);
                } else if (flash.type === "warning") {
                    warning(flash.message);
                }
                return;
            }

            closeImportModal();
            if (flash?.type === "success") {
                success(flash.message);
            } else {
                success("Instructors imported successfully");
            }
        },
        onError: () => {
            importErrorMessage.value = "Failed to import instructors. Please check your file format.";
            error("Failed to import instructors. Please check your file format.");
        },
    });
};

const closeCreateModal = () => {
    showCreateModal.value = false;
    createForm.reset();
    createForm.clearErrors();
};

const openEditModal = (instructor) => {
    editingInstructor.value = instructor;
    editForm.email = instructor.email;
    editForm.name = instructor.name;
    editForm.department_id = instructor.professor?.department_id || "";
    editForm.clearErrors();
    showEditModal.value = true;
};

const openResetPasswordModal = () => {
    showResetPasswordModal.value = true;
};

const closeResetPasswordModal = () => {
    showResetPasswordModal.value = false;
};

const confirmResetPassword = () => {
    router.post(
        route("admin.instructors.reset-password", editingInstructor.value.id),
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                closeResetPasswordModal();
                success("Password reset successfully to default (chcc@2025)");
            },
            onError: () => {
                error("Failed to reset password");
            },
        }
    );
};

const closeEditModal = () => {
    showEditModal.value = false;
    editingInstructor.value = null;
    editForm.reset();
    editForm.clearErrors();
};

const submitCreate = () => {
    createForm.post(route("admin.instructors.store"), {
        preserveScroll: true,
        onSuccess: () => {
            closeCreateModal();
            success("Instructor created successfully");
        },
        onError: () => {
            error("Failed to create instructor");
        },
    });
};

const submitEdit = () => {
    editForm.put(
        route("admin.instructors.update", editingInstructor.value.id),
        {
            preserveScroll: true,
            onSuccess: () => {
                closeEditModal();
                success("Instructor updated successfully");
            },
            onError: () => {
                error("Failed to update instructor");
            },
        }
    );
};

const openDeleteModal = (id) => {
    instructorToDelete.value = id;
    showDeleteModal.value = true;
};

const closeDeleteModal = () => {
    showDeleteModal.value = false;
    instructorToDelete.value = null;
};

const confirmDelete = () => {
    router.delete(
        route("admin.instructors.destroy", instructorToDelete.value),
        {
            preserveScroll: true,
            onSuccess: () => {
                closeDeleteModal();
                success("Instructor deleted successfully");
            },
            onError: () => {
                error("Failed to delete instructor");
            },
        }
    );
};

const formatDate = (dateString) => {
    if (!dateString) return "";
    const date = new Date(dateString);
    return date.toLocaleDateString("en-US", {
        year: "numeric",
        month: "short",
        day: "numeric",
    });
};
</script>

<template>
    <AdminLayout>
        <Head title="Instructors" />

        <!-- Header Section -->
        <div class="p-3 sm:p-4 mb-2">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 sm:gap-4 mb-4">
                <div class="w-full sm:w-auto">
                    <h1 class="text-lg sm:text-2xl font-semibold text-text-primary dark:text-text-inverted mb-1">
                        Instructors
                    </h1>
                    <p class="text-xs sm:text-sm text-text-secondary">
                        Manage instructor accounts and departments
                    </p>
                </div>
                <div class="grid grid-cols-2 sm:flex sm:flex-row items-stretch sm:items-center gap-2 w-full sm:w-auto">
                    <button
                        @click="openImportModal"
                        class="inline-flex w-full sm:w-auto justify-center items-center gap-1.5 px-3 sm:px-4 py-2 sm:py-2.5 bg-surface dark:bg-surface-dark-muted text-text-secondary border border-border-light dark:border-border-dark text-xs sm:text-sm font-medium rounded-lg hover:bg-gray-50 dark:hover:bg-white/5 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition-colors duration-200 whitespace-nowrap"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                        </svg>
                        Import Instructors
                    </button>
                    <button
                        @click="openCreateModal"
                        class="inline-flex w-full sm:w-auto justify-center items-center gap-1.5 px-3 sm:px-4 py-2 sm:py-2.5 bg-indigo-600 text-white text-xs sm:text-sm font-medium rounded-lg hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition-colors duration-200 whitespace-nowrap"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        Add Instructor
                    </button>
                </div>
            </div>
            <div class="sm:w-64">
                <label class="block text-xs font-medium text-text-secondary mb-1.5">
                    Filter by department
                </label>
                <SearchableSelect
                    v-model="departmentFilterId"
                    :options="departmentFilterOptions"
                    placeholder="Filter by department..."
                />
            </div>
        </div>

        <!-- Sticky Search -->
        <div ref="detailsSectionRef" class="sticky top-[64px] z-40 mb-6">
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
                        id="search"
                        v-model="searchQuery"
                        type="text"
                        placeholder="Search by email or name..."
                        class="w-full pl-10 pr-4 py-2 text-sm border border-border-light dark:border-border-dark rounded-lg bg-surface dark:bg-surface-dark-muted text-text-primary dark:text-text-inverted placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/40 focus:border-indigo-500 transition-all"
                    />
                </div>
            </div>
        </div>

        <!-- Instructors List -->
        <div
            class="bg-surface dark:bg-surface-dark-muted min-h-[calc(100vh-330px)] flex flex-col relative justify-between rounded-xl shadow-sm border border-border-light dark:border-border-dark overflow-hidden min-w-0"
        >
            <!-- Empty State -->
            <div v-if="!hasInstructors" class="p-8 sm:p-12 text-center min-h-[20rem] flex flex-col items-center justify-center">
                <Icon icon="simple-line-icons:people" class="w-10 h-10 text-blue-600 dark:text-blue-400 mx-auto mb-4" />
                <h3
                    class="text-sm font-medium text-text-primary dark:text-text-inverted mb-1"
                >
                    {{ hasActiveFilters ? "No instructors found" : "No instructors" }}
                </h3>
                <p class="text-sm text-text-secondary mb-4">
                    {{ hasActiveFilters
                        ? "Try adjusting your search or filters."
                        : "Get started by creating a new instructor or importing from a file."
                    }}
                </p>
                <div class="flex flex-col sm:flex-row gap-2 justify-center w-full max-w-xs mx-auto">
                    <button
                        @click="openImportModal"
                        class="inline-flex items-center justify-center gap-2 px-4 py-2 bg-gray-100 text-gray-700 dark:bg-surface-dark-muted dark:text-text-secondary text-sm font-medium rounded-lg hover:bg-gray-200 dark:hover:bg-white/10 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition-colors duration-200 w-full sm:w-auto"
                    >
                        Import Instructors
                    </button>
                    <button
                        @click="openCreateModal"
                        class="inline-flex items-center justify-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition-colors duration-200 w-full sm:w-auto"
                    >
                        Add Instructor
                    </button>
                </div>
            </div>

            <!-- Instructors Table -->
            <div v-else class="divide-y divide-gray-200 border-b border-gray-200 dark:divide-border-dark">
                <div
                    v-for="(instructor, index) in props.instructors.data"
                    :key="instructor.id"
                    class="p-3 sm:p-4 hover:bg-gray-50 dark:hover:bg-white/5 transition-colors duration-150"
                >
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-4 min-w-0">
                        <!-- Instructor Info -->
                        <div class="flex-1 min-w-0">
                            <div class="flex items-start sm:items-center gap-3">
                                <span class="text-sm font-medium text-text-secondary w-6 text-right flex-shrink-0 mt-2 sm:mt-0">{{ (props.instructors.from || 1) + index }}.</span>
                                <div class="flex-shrink-0">
                                    <div
                                        class="w-10 h-10 sm:w-12 sm:h-12 rounded-full bg-gradient-to-br from-purple-400 to-pink-500 flex items-center justify-center text-white font-semibold text-lg"
                                    >
                                        {{ instructor.name.charAt(0).toUpperCase() }}
                                    </div>
                                </div>
                                <div class="flex-1 min-w-0 space-y-1">
                                    <div class="flex items-center gap-2 sm:gap-3 flex-wrap min-w-0">
                                        <p
                                            class="text-base font-medium text-text-primary dark:text-text-inverted truncate"
                                        >
                                            {{ instructor.name }}
                                        </p>
                                        <span
                                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800 dark:bg-indigo-900/30 dark:text-indigo-300 max-w-full sm:max-w-[20rem] truncate"
                                            :title="instructor.email"
                                        >
                                            {{ instructor.email }}
                                        </span>
                                    </div>
                                    <div class="flex items-center gap-2 min-w-0">
                                        <span
                                            class="text-xs font-medium text-text-secondary"
                                        >
                                            Department:
                                        </span>
                                        <span
                                            class="text-sm text-text-secondary truncate"
                                        >
                                            {{
                                                instructor.professor?.department
                                                    ?.name || "No Department"
                                            }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="flex items-center gap-2 self-end sm:self-auto">
                            <button
                                @click="openEditModal(instructor)"
                                class="inline-flex items-center justify-center w-10 h-10 sm:w-auto sm:h-auto sm:p-2 text-text-secondary hover:text-indigo-600 dark:hover:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-900/20 rounded-lg transition-colors duration-150"
                                title="Edit"
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
                                        d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"
                                    />
                                </svg>
                            </button>
                            <button
                                @click="openDeleteModal(instructor.id)"
                                class="inline-flex items-center justify-center w-10 h-10 sm:w-auto sm:h-auto sm:p-2 text-text-secondary hover:text-red-600 dark:hover:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors duration-150"
                                title="Delete"
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
                                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                                    />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pagination -->
            <Pagination
                :links="props.instructors.links || []"
                :current-page="props.instructors.current_page || 1"
                :last-page="props.instructors.last_page || 1"
                :per-page="props.filters?.per_page || 10"
                :total="props.instructors.total || 0"
                :from="props.instructors.from || 0"
                :to="props.instructors.to || 0"
                route-name="admin.instructors.index"
                :filters="{
                    search: searchQuery || props.filters?.search || '',
                    department_id: departmentFilterId || props.filters?.department_id || '',
                }"
            />
        </div>

        <!-- Import Instructors Modal -->
        <Modal :show="showImportModal" @close="closeImportModal" max-width="lg">
            <div class="p-6">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-xl font-semibold text-text-primary dark:text-text-inverted">
                        Import Instructors
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

                    <!-- Department Selection -->
                    <div>
                        <InputLabel value="Fallback Department (Optional)" class="mb-2" />
                        <SearchableSelect
                            v-model="importForm.department_id"
                            :options="departmentOptions"
                            placeholder="Select a fallback department..."
                           
                        />
                        <InputError class="mt-2"  :message="importForm.errors.department_id" />
                        <p class="mt-1 text-xs text-text-secondary">
                            Used only if a row in your Excel file is missing a department.
                        </p>
                    </div>

                    <!-- Drag-and-drop file upload -->
                    <div>
                        <p class="text-sm text-text-secondary mb-3">
                            Upload an Excel or CSV file. Recommended columns: <strong>email</strong>, <strong>name</strong>, and <strong>department</strong>. Default password: <strong>chcc@2025</strong>. Large imports may take a moment—please wait.
                        </p>
                        <InputLabel for="import_file" value="Select File" class="mb-2" />
                        <div
                            class="mt-1 relative overflow-hidden rounded-lg p-[2px]"
                            @dragover.prevent="isImportDragging = true"
                            @dragleave.prevent="isImportDragging = false"
                            @drop.prevent="handleImportDrop"
                        >
                            <!-- Rotating gradient border: 2 lines when empty, full when file selected -->
                            <div
                                class="drop-zone-beam"
                                :class="{ 'drop-zone-beam--full': importForm.file }"
                            />
                            <!-- Inner content -->
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
                                    <p v-if="importFileName" class="text-sm font-medium text-text-secondary mt-2">
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

                    <!-- Skipped rows / warnings -->
                    <div
                        v-if="importErrors.length > 0"
                        class="p-3 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg max-h-32 overflow-y-auto"
                    >
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
                            {{ importForm.processing ? "Importing..." : "Import Instructors" }}
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- Create Instructor Modal -->
        <Modal :show="showCreateModal" @close="closeCreateModal">
            <div class="p-6">
                <div class="flex items-center justify-between mb-6">
                    <h2
                        class="text-xl font-semibold text-text-primary dark:text-text-inverted"
                    >
                        Create Instructor
                    </h2>
                    <button
                        @click="closeCreateModal"
                        class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300 transition-colors"
                    >
                        <svg
                            class="w-6 h-6"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M6 18L18 6M6 6l12 12"
                            />
                        </svg>
                    </button>
                </div>
                <form @submit.prevent="submitCreate" class="space-y-6">
                    <div>
                        <InputLabel
                            for="create_email"
                            value="Email"
                            class="mb-2"
                        />
                        <TextInput
                            id="create_email"
                            v-model="createForm.email"
                            type="email"
                            class="block w-full"
                            placeholder="Enter instructor email"
                            required
                            autofocus
                        />
                        <InputError
                            class="mt-2"
                            :message="createForm.errors.email"
                        />
                    </div>
                    <div>
                        <InputLabel
                            for="create_name"
                            value="Full Name"
                            class="mb-2"
                        />
                        <TextInput
                            id="create_name"
                            v-model="createForm.name"
                            type="text"
                            class="block w-full"
                            placeholder="Enter instructor full name"
                            required
                        />
                        <InputError
                            class="mt-2"
                            :message="createForm.errors.name"
                        />
                    </div>
                    <div>
                        <InputLabel
                            value="Department"
                            class="mb-2"
                        />
                        <SearchableSelect
                            v-model="createForm.department_id"
                            :options="departmentOptions"
                            placeholder="Search and select department..."
                        />
                        <InputError
                            class="mt-2"
                            :message="createForm.errors.department_id"
                        />
                    </div>
                    <div
                        class="flex flex-col-reverse sm:flex-row justify-end gap-3 pt-4 border-t border-border-light dark:border-border-dark"
                    >
                        <SecondaryButton
                            type="button"
                            @click="closeCreateModal"
                            class="px-4 py-2 w-full sm:w-auto"
                        >
                            Cancel
                        </SecondaryButton>
                        <PrimaryButton
                            :disabled="createForm.processing"
                            class="px-4 py-2 w-full sm:w-auto"
                        >
                            Create Instructor
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- Edit Instructor Modal -->
        <Modal :show="showEditModal" @close="closeEditModal">
            <div class="p-6">
                <div class="flex items-center justify-between mb-6">
                    <h2
                        class="text-xl font-semibold text-text-primary dark:text-text-inverted"
                    >
                        Edit Instructor
                    </h2>
                    <button
                        @click="closeEditModal"
                        class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300 transition-colors"
                    >
                        <svg
                            class="w-6 h-6"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M6 18L18 6M6 6l12 12"
                            />
                        </svg>
                    </button>
                </div>
                <form @submit.prevent="submitEdit" class="space-y-6">
                    <div>
                        <InputLabel
                            for="edit_email"
                            value="Email"
                            class="mb-2"
                        />
                        <TextInput
                            id="edit_email"
                            v-model="editForm.email"
                            type="email"
                            class="block w-full"
                            required
                            autofocus
                        />
                        <InputError
                            class="mt-2"
                            :message="editForm.errors.email"
                        />
                    </div>
                    <div>
                        <InputLabel
                            for="edit_name"
                            value="Full Name"
                            class="mb-2"
                        />
                        <TextInput
                            id="edit_name"
                            v-model="editForm.name"
                            type="text"
                            class="block w-full"
                            required
                        />
                        <InputError
                            class="mt-2"
                            :message="editForm.errors.name"
                        />
                    </div>
                    <div>
                        <InputLabel
                            value="Department"
                            class="mb-2"
                        />
                        <SearchableSelect
                            v-model="editForm.department_id"
                            :options="departmentOptions"
                            placeholder="Search and select department..."
                        />
                        <InputError
                            class="mt-2"
                            :message="editForm.errors.department_id"
                        />
                    </div>
                    <div
                        class="pt-4 border-t border-border-light dark:border-border-dark"
                    >
                        <div
                            class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 p-3 bg-gray-50 dark:bg-surface-dark-muted rounded-lg"
                        >
                            <div>
                                <p
                                    class="text-sm font-medium text-text-primary dark:text-text-inverted"
                                >
                                    Password
                                </p>
                                <p
                                    class="text-xs text-text-secondary mt-0.5"
                                >
                                    Default: chcc@2025
                                </p>
                            </div>
                            <SecondaryButton
                                type="button"
                                @click="openResetPasswordModal"
                                class="px-4 py-2"
                            >
                                Reset Password
                            </SecondaryButton>
                        </div>
                    </div>
                    <div
                        class="flex flex-col-reverse sm:flex-row justify-end gap-3 pt-4 border-t border-border-light dark:border-border-dark"
                    >
                        <SecondaryButton
                            type="button"
                            @click="closeEditModal"
                            class="px-4 py-2 w-full sm:w-auto"
                        >
                            Cancel
                        </SecondaryButton>
                        <PrimaryButton
                            :disabled="editForm.processing"
                            class="px-4 py-2 w-full sm:w-auto"
                        >
                            Save Changes
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- Reset Password Confirmation Modal -->
        <ConfirmationModal
            :show="showResetPasswordModal"
            title="Reset Password"
            message="Are you sure you want to reset the password to default (chcc@2025)?"
            confirm-text="Reset Password"
            cancel-text="Cancel"
            variant="warning"
            @close="closeResetPasswordModal"
            @confirm="confirmResetPassword"
        />

        <!-- Delete Confirmation Modal -->
        <ConfirmationModal
            :show="showDeleteModal"
            title="Delete Instructor"
            message="Are you sure you want to delete this instructor? This action cannot be undone and may affect associated lessons, assessments, and assignments."
            confirm-text="Delete"
            cancel-text="Cancel"
            variant="danger"
            @close="closeDeleteModal"
            @confirm="confirmDelete"
        />
    </AdminLayout>
</template>

<style scoped>
.drop-zone-beam {
    position: absolute;
    top: 50%;
    left: 50%;
    /* Perfect square (circle) para umikot ang gradient sa buong border */
    width: 200vmax;
    height: 200vmax;
    margin-left: -100vmax;
    margin-top: -100vmax;
    /* 2 lines na gradient #3238a8 → #00c8ff */
    background: conic-gradient(
        from 0deg,
        /* Line 1 - gradient #3238a8 → #00c8ff */
        transparent 0deg,
        #3238a8 20deg,
        #00c8ff 40deg,
        transparent 60deg,
        /* Dim */
        transparent 60deg 150deg,
        /* Line 2 - gradient #3238a8 → #00c8ff (180° from line 1) */
        transparent 150deg,
        #3238a8 170deg,
        #00c8ff 190deg,
        transparent 210deg,
        /* Dim */
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
    from {
        transform: rotate(0deg);
    }
    to {
        transform: rotate(360deg);
    }
}
</style>
