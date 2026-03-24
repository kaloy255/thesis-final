<template>
    <InstructorLayout>
        <Head title="Create Manual Assessment" />
        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-surface dark:bg-surface-dark-muted overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <!-- Header -->
                        <div class="flex justify-between items-center mb-6">
                            <div class="border-l-4 border-indigo-500 pl-4">
                                <h2 class="text-2xl font-bold text-text-primary dark:text-text-inverted tracking-tight">
                                    Create Manual Assessment
                                </h2>
                                <p class="text-sm text-text-secondary mt-1">
                                    Manually create assessment questions
                                </p>
                            </div>
                        </div>

            <form @submit.prevent="submitForm" class="relative">
                <!-- Assessment Info Row -->
                <div class="mb-6">
                    <!-- Status & Config + Question Stats in a single row -->
                    <div class="flex flex-col lg:flex-row gap-4 items-stretch">
                        <!-- Assessment Config & Status -->
                        <div class="flex flex-col gap-4 p-4 bg-gradient-to-br from-indigo-50 to-purple-50 dark:from-indigo-900/20 dark:to-purple-900/20 rounded-xl border border-indigo-100 dark:border-indigo-800 shadow-sm lg:w-[320px] flex-shrink-0">
                            <!-- Status Header -->
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <div class="p-1.5 bg-indigo-100 dark:bg-indigo-800 rounded-lg">
                                        <svg class="w-5 h-5 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    </div>
                                    <span class="text-xs font-semibold text-indigo-800 dark:text-indigo-300 uppercase tracking-wide">Status</span>
                                </div>
                                <span
                                    :class="{
                                        'px-2.5 py-0.5 inline-flex text-xs font-bold rounded-full uppercase tracking-wide': true,
                                        'bg-yellow-200 text-yellow-800 dark:bg-yellow-900/50 dark:text-yellow-300': form.status === 'draft',
                                        'bg-green-200 text-green-800 dark:bg-green-900/50 dark:text-green-300': form.status === 'published',
                                    }"
                                >
                                    {{ form.status }}
                                </span>
                            </div>
                            <!-- Basic Information Fields -->
                            <div class="space-y-3">
                                <div>
                                    <InputLabel for="subject_id" value="Subject" class="text-xs font-semibold text-indigo-900 dark:text-indigo-200" />
                                    <select
                                        id="subject_id"
                                        v-model="form.subject_id"
                                        class="mt-1 block w-full text-sm border-indigo-200 dark:border-indigo-700/50 bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm"
                                        required
                                    >
                                        <option value="">Select a subject</option>
                                        <option v-for="subject in subjects" :key="subject.id" :value="subject.id">
                                            {{ subject.name }} ({{ subject.code }})
                                        </option>
                                    </select>
                                    <InputError class="mt-1" :message="form.errors.subject_id" />
                                </div>
                                <div>
                                    <InputLabel for="title" value="Lesson Title" class="text-xs font-semibold text-indigo-900 dark:text-indigo-200" />
                                    <TextInput
                                        id="title"
                                        v-model="form.title"
                                        type="text"
                                        class="mt-1 block w-full text-sm border-indigo-200 dark:border-indigo-700/50 focus:border-indigo-500 focus:ring-indigo-500"
                                        required
                                        placeholder="Enter lesson title"
                                    />
                                    <InputError class="mt-1" :message="form.errors.title" />
                                </div>
                            </div>
                            <!-- Status Toggles -->
                            <div class="flex gap-2 w-full mt-1">
                                <button
                                    type="button"
                                    v-if="form.status === 'published'"
                                    @click="form.status = 'draft'"
                                    class="w-full inline-flex justify-center items-center px-3 py-1.5 bg-yellow-100 text-yellow-800 hover:bg-yellow-200 dark:bg-yellow-900/40 dark:text-yellow-300 dark:hover:bg-yellow-900/60 border border-yellow-200 dark:border-yellow-800/50 rounded-lg font-semibold text-xs transition-colors"
                                >
                                    Set to Draft
                                </button>
                                <button
                                    type="button"
                                    v-if="form.status === 'draft'"
                                    @click="form.status = 'published'"
                                    class="w-full inline-flex justify-center items-center px-3 py-1.5 bg-green-600 text-white hover:bg-green-700 border border-transparent rounded-lg font-semibold text-xs transition-colors"
                                >
                                    Publish Directly
                                </button>
                            </div>
                        </div>

                        <!-- Question Type Counts -->
                        <div class="flex-1 grid grid-cols-4 gap-3">
                            <div class="flex flex-col items-center justify-center p-3 bg-blue-50 dark:bg-blue-900/20 rounded-xl border border-blue-100 dark:border-blue-800/50 shadow-sm transition-all hover:-translate-y-0.5 hover:shadow-md">
                                <span class="text-2xl font-bold text-blue-700 dark:text-blue-400">{{ questionCounts.multiple_choice }}</span>
                                <span class="text-[10px] font-semibold text-blue-600 dark:text-blue-500 uppercase tracking-wide text-center mt-1">Multiple<br>Choice</span>
                            </div>
                            <div class="flex flex-col items-center justify-center p-3 bg-green-50 dark:bg-green-900/20 rounded-xl border border-green-100 dark:border-green-800/50 shadow-sm transition-all hover:-translate-y-0.5 hover:shadow-md">
                                <span class="text-2xl font-bold text-green-700 dark:text-green-400">{{ questionCounts.identification }}</span>
                                <span class="text-[10px] font-semibold text-green-600 dark:text-green-500 uppercase tracking-wide text-center mt-1">Identifi-<br>cation</span>
                            </div>
                            <div class="flex flex-col items-center justify-center p-3 bg-purple-50 dark:bg-purple-900/20 rounded-xl border border-purple-100 dark:border-purple-800/50 shadow-sm transition-all hover:-translate-y-0.5 hover:shadow-md">
                                <span class="text-2xl font-bold text-purple-700 dark:text-purple-400">{{ questionCounts.true_or_false }}</span>
                                <span class="text-[10px] font-semibold text-purple-600 dark:text-purple-500 uppercase tracking-wide text-center mt-1">True /<br>False</span>
                            </div>
                            <div class="flex flex-col items-center justify-center p-3 bg-indigo-50 dark:bg-indigo-900/20 rounded-xl border border-indigo-200 dark:border-indigo-800 shadow-sm relative overflow-hidden transition-all hover:-translate-y-0.5 hover:shadow-md">
                                <div class="absolute -right-2 -bottom-2 opacity-10">
                                    <svg class="w-12 h-12 text-indigo-900" fill="currentColor" viewBox="0 0 20 20"><path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"></path><path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z" clip-rule="evenodd"></path></svg>
                                </div>
                                <span class="text-3xl font-black text-indigo-700 dark:text-indigo-400 relative z-10">{{ questionCounts.total }}</span>
                                <span class="text-xs font-bold text-indigo-900 dark:text-indigo-300 uppercase tracking-widest mt-1 relative z-10">Total</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Support File Upload Panel (Collapsible) -->
                <div class="mb-6 border border-border-light dark:border-border-dark rounded-xl overflow-hidden shadow-sm bg-surface dark:bg-surface-dark-muted">
                    <!-- Toggle Header -->
                    <button
                        type="button"
                        @click="showFilePanel = !showFilePanel"
                        class="w-full flex items-center justify-between px-5 py-3.5 bg-gradient-to-r from-gray-50 to-white dark:from-gray-800 dark:to-gray-800/50 hover:from-gray-100 hover:to-gray-50 dark:hover:from-gray-700 dark:hover:to-gray-800 transition-colors group"
                    >
                        <div class="flex items-center gap-3">
                            <div class="p-1.5 bg-blue-100 dark:bg-blue-900/50 rounded-lg group-hover:bg-blue-200 dark:group-hover:bg-blue-800 transition-colors">
                                <svg class="w-4 h-4 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path></svg>
                            </div>
                            <div class="text-left">
                                <h3 class="text-sm font-semibold text-text-primary dark:text-text-inverted">Attach Support File <span class="text-xs font-normal text-gray-500 ml-1">(Optional)</span></h3>
                                <p class="text-xs text-text-secondary mt-0.5">
                                    <template v-if="fileName">
                                        <span class="text-indigo-600 dark:text-indigo-400 font-medium truncate max-w-[200px] sm:max-w-md inline-block align-bottom">{{ fileName }}</span>
                                    </template>
                                    <template v-else>
                                        Upload a document to enable AI adaptive questions
                                    </template>
                                </p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <div class="p-1 text-gray-400 transition-transform duration-200" :class="{ 'rotate-180': showFilePanel }">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                            </div>
                        </div>
                    </button>

                    <!-- Expandable Content -->
                    <div v-show="showFilePanel" class="border-t border-border-light dark:border-border-dark p-5 bg-surface dark:bg-surface-dark-muted">
                        <div
                            class="relative overflow-hidden rounded-xl border-2 border-dashed transition-colors"
                            :class="[
                                isDragging ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/20' : 'border-border-light dark:border-border-dark hover:border-indigo-400 dark:hover:border-indigo-600',
                                fileError || form.errors.file ? 'border-red-500 bg-red-50 dark:bg-red-900/10' : ''
                            ]"
                            @dragover.prevent="isDragging = true"
                            @dragleave.prevent="isDragging = false"
                            @drop.prevent="handleDrop"
                        >
                            <div class="flex flex-col items-center justify-center p-8 text-center">
                                <div v-if="!form.file" class="space-y-3">
                                    <div class="w-12 h-12 mx-auto bg-indigo-100 dark:bg-indigo-900/50 rounded-full flex items-center justify-center">
                                        <svg class="w-6 h-6 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path></svg>
                                    </div>
                                    <div class="flex text-sm text-text-secondary justify-center">
                                        <label for="file_upload" class="relative cursor-pointer bg-surface dark:bg-surface-dark-muted rounded-md font-medium text-indigo-600 hover:text-indigo-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-indigo-500">
                                            <span>Click to upload</span>
                                            <input id="file_upload" type="file" class="sr-only" accept=".docx,.pdf,.pptx,.txt" @change="handleFileSelect" />
                                        </label>
                                        <p class="pl-1">or drag and drop</p>
                                    </div>
                                    <p class="text-xs text-text-secondary">DOCX, PDF, PPTX or TXT (Max 10MB)</p>
                                </div>
                                <div v-else class="flex flex-col items-center gap-3">
                                    <div class="w-12 h-12 bg-green-100 dark:bg-green-900/30 rounded-full flex items-center justify-center">
                                        <svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    </div>
                                    <div class="text-sm font-medium text-text-primary dark:text-text-inverted">{{ fileName }}</div>
                                    <button type="button" @click="removeFile" class="text-xs font-semibold text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300">
                                        Remove file
                                    </button>
                                </div>
                            </div>
                        </div>
                        <InputError class="mt-2" :message="fileError || form.errors.file" />
                    </div>
                </div>

                <!-- Section Assignment Panel (Collapsible) -->
                <div class="mb-6 border border-border-light dark:border-border-dark rounded-xl overflow-hidden shadow-sm">
                    <!-- Toggle Header -->
                    <button
                        type="button"
                        @click="showSectionPanel = !showSectionPanel"
                        class="w-full flex items-center justify-between px-5 py-3.5 bg-gradient-to-r from-indigo-50 to-purple-50 dark:from-indigo-900/20 dark:to-purple-900/20 hover:from-indigo-100 hover:to-purple-100 dark:hover:from-indigo-900/30 dark:hover:to-purple-900/30 transition-colors group"
                    >
                        <div class="flex items-center gap-3">
                            <div class="p-1.5 bg-indigo-100 dark:bg-indigo-800 rounded-lg group-hover:bg-indigo-200 dark:group-hover:bg-indigo-700 transition-colors">
                                <svg class="w-4 h-4 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                            </div>
                            <div class="text-left">
                                <h3 class="text-sm font-semibold text-text-primary dark:text-text-inverted">Assign to Sections</h3>
                                <p class="text-xs text-text-secondary mt-0.5">
                                    <template v-if="form.section_ids.length > 0">
                                        {{ form.section_ids.length }} section{{ form.section_ids.length > 1 ? 's' : '' }} assigned
                                    </template>
                                    <template v-else>
                                        No sections assigned yet
                                    </template>
                                </p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <!-- Selected section chips (show first 3) -->
                            <div v-if="form.section_ids.length > 0 && !showSectionPanel" class="hidden sm:flex items-center gap-1.5">
                                <span
                                    v-for="sectionId in form.section_ids.slice(0, 3)"
                                    :key="sectionId"
                                    class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300 border border-indigo-200/50 dark:border-indigo-700/50"
                                >
                                    {{ getSectionDisplayName(sectionId) }}
                                </span>
                                <span v-if="form.section_ids.length > 3" class="text-xs text-indigo-600 dark:text-indigo-400 font-medium">
                                    +{{ form.section_ids.length - 3 }} more
                                </span>
                            </div>
                            <div class="p-1 text-gray-400 transition-transform duration-200" :class="{ 'rotate-180': showSectionPanel }">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                            </div>
                        </div>
                    </button>

                    <!-- Expandable Content -->
                    <div v-show="showSectionPanel" class="border-t border-border-light dark:border-border-dark">
                        <SectionAssignment
                            :departments="departments || []"
                            :sections="sections || []"
                            v-model:selectedSectionIds="form.section_ids"
                            :errors="[]"
                            class="!border-0 !shadow-none !rounded-none !mt-0 !mb-0"
                        />
                    </div>
                </div>

                <!-- Add Question Button Bar -->
                <div class="mb-6 flex justify-between items-center bg-gray-50 dark:bg-surface-dark-muted/50 p-4 rounded-xl border border-border-light dark:border-border-dark">
                    <div>
                        <h3 class="text-sm font-semibold text-text-primary dark:text-text-inverted">Questions</h3>
                        <p class="text-xs text-text-secondary">Add or edit questions below</p>
                    </div>
                    <button
                        type="button"
                        @click="showAddQuestionForm = true"
                        class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition ease-in-out duration-150 shadow-sm"
                    >
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                        <span class="sm:hidden">Questions</span>
                        <span class="hidden sm:inline">Add Question</span>
                    </button>
                </div>

                    <!-- Empty State -->
                    <div
                        v-if="questions.length === 0 && !showAddQuestionForm"
                        class="text-center py-12"
                    >
                        <div
                            class="w-16 h-16 mx-auto mb-4 rounded-full bg-gray-100 dark:bg-surface-dark-muted flex items-center justify-center"
                        >
                            <svg
                                class="w-8 h-8 text-text-secondary"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                                />
                            </svg>
                        </div>
                        <h3
                            class="text-lg font-medium text-text-primary dark:text-text-inverted mb-2"
                        >
                            No questions yet
                        </h3>
                        <p
                            class="text-sm text-text-secondary mb-6"
                        >
                            Get started by adding your first question
                        </p>
                        <button
                            type="button"
                            @click="showAddQuestionForm = true"
                            class="inline-flex items-center px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition-colors"
                        >
                            <svg
                                class="w-4 h-4 mr-2"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M12 4v16m8-8H4"
                                />
                            </svg>
                            Add First Question
                        </button>
                    </div>

                    <!-- Add Question Modal -->
                    <div
                        v-if="showAddQuestionForm"
                        class="fixed inset-0 z-[100] overflow-y-auto"
                        aria-labelledby="modal-title"
                        role="dialog"
                        aria-modal="true"
                    >
                        <div
                            class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0"
                        >
                            <!-- Background overlay -->
                            <div
                                class="fixed inset-0 bg-gray-500/75 dark:bg-gray-900/90 backdrop-blur-sm transition-opacity"
                                aria-hidden="true"
                                @click="cancelAddQuestion"
                            ></div>

                            <!-- center modal -->
                            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                            <div
                                class="inline-block align-bottom bg-surface dark:bg-surface-dark-muted rounded-xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full border border-border-light dark:border-border-dark"
                            >
                                <!-- Modal Header -->
                                <div class="px-6 py-4 border-b border-border-light dark:border-border-dark flex items-center justify-between bg-gray-50 dark:bg-gray-900/50">
                                    <h3 class="text-lg font-semibold text-text-primary dark:text-text-inverted" id="modal-title">
                                        Add New Question
                                    </h3>
                                    <button
                                        type="button"
                                        @click="cancelAddQuestion"
                                        class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300 transition-colors"
                                    >
                                        <span class="sr-only">Close</span>
                                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>

                                <!-- Modal Body -->
                                <div class="px-6 py-6 space-y-6">
                                    <!-- Question Type -->
                                    <div>
                                        <InputLabel
                                            for="new_question_type"
                                            value="Question Type"
                                        />

                                        <!-- Mobile/Tablet Type Selector -->
                                        <div class="mt-1 grid grid-cols-1 sm:grid-cols-3 gap-2 lg:hidden">
                                            <button
                                                type="button"
                                                @click="setQuestionType('multiple_choice')"
                                                :class="[
                                                    'w-full rounded-lg border px-3 py-2 text-sm font-medium text-left transition-colors',
                                                    newQuestion.type === 'multiple_choice'
                                                        ? 'border-indigo-500 bg-indigo-50 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300'
                                                        : 'border-border-light dark:border-border-dark text-text-secondary hover:bg-gray-50 dark:hover:bg-white/5'
                                                ]"
                                            >
                                                Multiple Choice
                                            </button>
                                            <button
                                                type="button"
                                                @click="setQuestionType('identification')"
                                                :class="[
                                                    'w-full rounded-lg border px-3 py-2 text-sm font-medium text-left transition-colors',
                                                    newQuestion.type === 'identification'
                                                        ? 'border-indigo-500 bg-indigo-50 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300'
                                                        : 'border-border-light dark:border-border-dark text-text-secondary hover:bg-gray-50 dark:hover:bg-white/5'
                                                ]"
                                            >
                                                Identification
                                            </button>
                                            <button
                                                type="button"
                                                @click="setQuestionType('true_or_false')"
                                                :class="[
                                                    'w-full rounded-lg border px-3 py-2 text-sm font-medium text-left transition-colors',
                                                    newQuestion.type === 'true_or_false'
                                                        ? 'border-indigo-500 bg-indigo-50 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300'
                                                        : 'border-border-light dark:border-border-dark text-text-secondary hover:bg-gray-50 dark:hover:bg-white/5'
                                                ]"
                                            >
                                                True/False
                                            </button>
                                        </div>

                                        <!-- Desktop Dropdown -->
                                        <select
                                            id="new_question_type"
                                            v-model="newQuestion.type"
                                            @change="handleTypeChange"
                                            class="mt-1 hidden lg:block w-full border-border-light dark:border-border-dark dark:bg-gray-900 dark:text-text-secondary focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-lg shadow-sm"
                                        >
                                            <option value="multiple_choice">
                                                Multiple Choice
                                            </option>
                                            <option value="identification">
                                                Identification
                                            </option>
                                            <option value="true_or_false">
                                                True/False
                                            </option>
                                        </select>
                                    </div>

                                    <!-- Question Text -->
                                    <div>
                                        <InputLabel
                                            for="new_question_text"
                                            value="Question Text"
                                        />
                                        <textarea
                                            id="new_question_text"
                                            v-model="newQuestion.question"
                                            @blur="validateQuestion"
                                            @input="errors.question = ''"
                                            rows="3"
                                            :class="[
                                                'mt-1 block w-full dark:bg-gray-900 dark:text-text-secondary focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-lg shadow-sm',
                                                errors.question
                                                    ? 'border-red-500 dark:border-red-500 focus:border-red-500 dark:focus:border-red-500'
                                                    : 'border-border-light dark:border-border-dark focus:border-indigo-500 dark:focus:border-indigo-600',
                                            ]"
                                            placeholder="Enter your question here..."
                                        ></textarea>
                                        <InputError
                                            class="mt-1"
                                            :message="errors.question"
                                        />
                                    </div>

                                    <!-- Choices (Multiple Choice Only) -->
                                    <div v-if="newQuestion.type === 'multiple_choice'" class="bg-gray-50 dark:bg-gray-900/30 p-4 rounded-lg border border-gray-100 dark:border-gray-800">
                                        <div
                                            class="flex items-center justify-between mb-3"
                                        >
                                            <InputLabel value="Choices" />
                                            <button
                                                type="button"
                                                v-if="newQuestion.choices.length < 8"
                                                @click="addChoice"
                                                class="text-xs font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300 bg-indigo-50 dark:bg-indigo-900/30 px-2.5 py-1 rounded-md transition-colors"
                                            >
                                                + Add Choice
                                            </button>
                                        </div>
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                            <div
                                                v-for="(
                                                    choice, index
                                                ) in newQuestion.choices"
                                                :key="index"
                                                class="flex items-center gap-2"
                                            >
                                                <div class="flex-shrink-0 w-6 text-center text-sm font-medium text-gray-500">
                                                    {{ String.fromCharCode(65 + index) }}.
                                                </div>
                                                <input
                                                    v-model="newQuestion.choices[index]"
                                                    @input="
                                                        validateChoices();
                                                        errors.choices = '';
                                                    "
                                                    type="text"
                                                    :class="[
                                                        'flex-1 text-sm dark:bg-gray-900 dark:text-text-secondary focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-lg shadow-sm',
                                                        errors.choices
                                                            ? 'border-red-500 dark:border-red-500 focus:border-red-500 dark:focus:border-red-500'
                                                            : 'border-border-light dark:border-border-dark focus:border-indigo-500 dark:focus:border-indigo-600',
                                                    ]"
                                                    :placeholder="`Choice ${index + 1}`"
                                                />
                                                <button
                                                    type="button"
                                                    v-if="newQuestion.choices.length > 4"
                                                    @click="removeChoice(index)"
                                                    class="text-red-500 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300 p-1"
                                                >
                                                    <svg
                                                        class="w-4 h-4"
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
                                        </div>
                                        <InputError
                                            class="mt-2"
                                            :message="errors.choices"
                                        />
                                    </div>

                                    <!-- Correct Answer -->
                                    <div>
                                        <InputLabel
                                            for="new_question_answer"
                                            value="Correct Answer"
                                        />

                                        <!-- Mobile/Tablet Checkbox Selector for Multiple Choice -->
                                        <div
                                            v-if="newQuestion.type === 'multiple_choice'"
                                            class="lg:hidden mt-1 space-y-2 rounded-lg border border-border-light dark:border-border-dark p-3"
                                        >
                                            <label
                                                v-for="(choice, index) in newQuestion.choices.filter((c) => c.trim() !== '')"
                                                :key="`mobile-choice-${index}`"
                                                class="flex items-center gap-2 cursor-pointer"
                                            >
                                                <input
                                                    type="checkbox"
                                                    :checked="newQuestion.correct_answer === choice"
                                                    @change="setCorrectAnswerOption(choice, $event.target.checked)"
                                                    class="rounded border-border-light text-indigo-600 focus:ring-indigo-500 dark:border-border-dark dark:bg-gray-900"
                                                />
                                                <span class="text-sm text-text-secondary dark:text-text-secondary">
                                                    {{ String.fromCharCode(65 + index) }}. {{ choice }}
                                                </span>
                                            </label>
                                        </div>

                                        <!-- Desktop Dropdown for Multiple Choice -->
                                        <select
                                            v-if="newQuestion.type === 'multiple_choice'"
                                            id="new_question_answer"
                                            v-model="newQuestion.correct_answer"
                                            @change="validateCorrectAnswer"
                                            @focus="errors.correct_answer = ''"
                                            :class="[
                                                'mt-1 hidden lg:block w-full dark:bg-gray-900 dark:text-text-secondary focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-lg shadow-sm',
                                                errors.correct_answer
                                                    ? 'border-red-500 dark:border-red-500 focus:border-red-500 dark:focus:border-red-500'
                                                    : 'border-border-light dark:border-border-dark focus:border-indigo-500 dark:focus:border-indigo-600',
                                            ]"
                                        >
                                            <option value="">
                                                Select correct answer
                                            </option>
                                            <option
                                                v-for="(choice, index) in newQuestion.choices.filter((c) => c.trim() !== '')"
                                                :key="`desktop-choice-${index}`"
                                                :value="choice"
                                            >
                                                {{ String.fromCharCode(65 + index) }}: {{ choice }}
                                            </option>
                                        </select>

                                        <!-- Mobile/Tablet Checkbox Selector for True/False -->
                                        <div
                                            v-else-if="newQuestion.type === 'true_or_false'"
                                            class="lg:hidden mt-1 space-y-2 rounded-lg border border-border-light dark:border-border-dark p-3"
                                        >
                                            <label class="flex items-center gap-2 cursor-pointer">
                                                <input
                                                    type="checkbox"
                                                    :checked="newQuestion.correct_answer === 'True'"
                                                    @change="setCorrectAnswerOption('True', $event.target.checked)"
                                                    class="rounded border-border-light text-indigo-600 focus:ring-indigo-500 dark:border-border-dark dark:bg-gray-900"
                                                />
                                                <span class="text-sm text-text-secondary dark:text-text-secondary">True</span>
                                            </label>
                                            <label class="flex items-center gap-2 cursor-pointer">
                                                <input
                                                    type="checkbox"
                                                    :checked="newQuestion.correct_answer === 'False'"
                                                    @change="setCorrectAnswerOption('False', $event.target.checked)"
                                                    class="rounded border-border-light text-indigo-600 focus:ring-indigo-500 dark:border-border-dark dark:bg-gray-900"
                                                />
                                                <span class="text-sm text-text-secondary dark:text-text-secondary">False</span>
                                            </label>
                                        </div>

                                        <!-- Desktop Dropdown for True/False -->
                                        <select
                                            v-else-if="newQuestion.type === 'true_or_false'"
                                            id="new_question_answer"
                                            v-model="newQuestion.correct_answer"
                                            @change="validateCorrectAnswer"
                                            @focus="errors.correct_answer = ''"
                                            :class="[
                                                'mt-1 hidden lg:block w-full dark:bg-gray-900 dark:text-text-secondary focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-lg shadow-sm',
                                                errors.correct_answer
                                                    ? 'border-red-500 dark:border-red-500 focus:border-red-500 dark:focus:border-red-500'
                                                    : 'border-border-light dark:border-border-dark focus:border-indigo-500 dark:focus:border-indigo-600',
                                            ]"
                                        >
                                            <option value="">Select answer</option>
                                            <option value="True">True</option>
                                            <option value="False">False</option>
                                        </select>
                                        <!-- Text Input for Identification -->
                                        <textarea
                                            v-else
                                            id="new_question_answer"
                                            v-model="newQuestion.correct_answer"
                                            @blur="validateCorrectAnswer"
                                            @input="errors.correct_answer = ''"
                                            rows="1"
                                            :class="[
                                                'mt-1 block w-full dark:bg-gray-900 dark:text-text-secondary focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-lg shadow-sm',
                                                errors.correct_answer
                                                    ? 'border-red-500 dark:border-red-500 focus:border-red-500 dark:focus:border-red-500'
                                                    : 'border-border-light dark:border-border-dark focus:border-indigo-500 dark:focus:border-indigo-600',
                                            ]"
                                            placeholder="Enter the correct answer..."
                                        ></textarea>
                                        <InputError
                                            class="mt-1"
                                            :message="errors.correct_answer"
                                        />
                                    </div>
                                </div>

                                <!-- Modal Footer -->
                                <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900/50 border-t border-border-light dark:border-border-dark sm:flex sm:flex-row-reverse">
                                    <button
                                        type="button"
                                        @click="addQuestion"
                                        class="w-full inline-flex justify-center rounded-lg border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-base font-medium text-white hover:bg-indigo-700 focus:outline-none sm:ml-3 sm:w-auto sm:text-sm transition-colors"
                                    >
                                        Add Question
                                    </button>
                                    <button
                                        type="button"
                                        @click="cancelAddQuestion"
                                        class="mt-3 w-full inline-flex justify-center rounded-lg border border-border-light dark:border-border-dark shadow-sm px-4 py-2 bg-surface dark:bg-surface-dark-muted text-base font-medium text-text-secondary hover:bg-gray-50 dark:hover:bg-white/5 focus:outline-none sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm transition-colors"
                                    >
                                        Cancel
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Questions List -->
                    <div v-if="questions.length > 0" class="space-y-4">
                        <div
                            v-for="(item, index) in questions"
                            :key="index"
                            class="border border-border-light dark:border-border-dark rounded-xl bg-gray-50 dark:bg-surface-dark-muted/50 overflow-hidden transition-all duration-200"
                            :class="{ 'ring-2 ring-indigo-500/50 shadow-md': expandedQuestionIndex === index }"
                        >
                            <!-- Compact Header Row -->
                            <div
                                @click="toggleQuestion(index)"
                                class="flex items-center gap-4 px-4 py-3 cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
                            >
                                <!-- Drag Handle & Number -->
                                <div class="flex items-center gap-3 w-16 flex-shrink-0">
                                    <svg class="w-4 h-4 text-gray-400 cursor-move hover:text-gray-600 dark:hover:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16" /></svg>
                                    <span class="font-bold text-text-secondary">{{ index + 1 }}.</span>
                                </div>

                                <!-- Question Summary & Badges -->
                                <div class="flex-1 min-w-0 flex items-center justify-between gap-4">
                                    <p class="truncate text-sm font-medium" :class="expandedQuestionIndex === index ? 'text-indigo-700 dark:text-indigo-400' : 'text-gray-900 dark:text-gray-100'">
                                        {{ item.question || 'New Question' }}
                                    </p>
                                    <div class="flex items-center gap-2 flex-shrink-0">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-gray-200 text-gray-800 dark:bg-surface-dark-muted dark:text-text-secondary hidden sm:inline-flex">
                                            {{ formatType(item.type) }}
                                        </span>
                                    </div>
                                </div>

                                <!-- Actions -->
                                <div class="flex items-center gap-2 flex-shrink-0">
                                    <button
                                        @click.stop="removeQuestion(index)"
                                        class="p-1.5 text-gray-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors"
                                        title="Delete Question"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                    </button>
                                    <div class="w-px h-5 bg-gray-300 dark:bg-gray-600 mx-1"></div>
                                    <div class="p-1.5 text-gray-400 transition-transform duration-200" :class="{ 'rotate-180 text-indigo-500': expandedQuestionIndex === index }">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                                    </div>
                                </div>
                            </div>

                            <!-- Expanded Editable Form -->
                            <div
                                v-show="expandedQuestionIndex === index"
                                class="px-5 py-5 border-t border-border-light dark:border-border-dark bg-white dark:bg-gray-900"
                            >
                                <!-- Question Text -->
                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-text-secondary mb-1">
                                        Question Text
                                    </label>
                                    <textarea
                                        v-model="item.question"
                                        rows="3"
                                        class="mt-1 block w-full resize-none border-border-light dark:border-border-dark dark:bg-gray-900 dark:text-text-secondary focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm sm:text-sm"
                                    ></textarea>
                                </div>

                                <!-- Choices (Multiple Choice Only) -->
                                <div
                                    v-if="item.type === 'multiple_choice'"
                                    class="mb-4 bg-gray-50 dark:bg-surface-dark-muted/50 p-4 rounded-xl border border-border-light dark:border-border-dark"
                                >
                                    <label class="block text-sm font-medium text-text-secondary mb-3">
                                        Choices
                                    </label>
                                    <div class="space-y-2.5">
                                        <div
                                            v-for="(choice, choiceIndex) in item.choices"
                                            :key="choiceIndex"
                                            class="flex items-center gap-2 min-w-0"
                                        >
                                            <div class="flex-shrink-0 w-6 flex justify-center text-sm font-medium text-gray-500">{{ String.fromCharCode(65 + choiceIndex) }}.</div>
                                            <input
                                                v-model="item.choices[choiceIndex]"
                                                type="text"
                                                class="flex-1 min-w-0 w-0 truncate border-border-light dark:border-border-dark dark:bg-gray-900 dark:text-text-secondary focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm sm:text-sm"
                                            />
                                        </div>
                                    </div>
                                </div>

                                <!-- Correct Answer -->
                                <div>
                                    <label class="block text-sm font-medium text-text-secondary mb-1">
                                        Correct Answer
                                    </label>

                                    <!-- Mobile/Tablet Checkbox Selector for Multiple Choice -->
                                    <div
                                        v-if="item.type === 'multiple_choice'"
                                        class="lg:hidden mt-1 space-y-2 rounded-lg border border-border-light dark:border-border-dark p-3"
                                    >
                                        <label
                                            v-for="(choice, choiceIdx) in item.choices.filter((c) => c && c.trim() !== '')"
                                            :key="`existing-mobile-choice-${index}-${choiceIdx}`"
                                            class="flex items-center gap-2 cursor-pointer"
                                        >
                                            <input
                                                type="checkbox"
                                                :checked="item.correct_answer === choice"
                                                @change="setExistingCorrectAnswerOption(item, choice, $event.target.checked)"
                                                class="rounded border-border-light text-indigo-600 focus:ring-indigo-500 dark:border-border-dark dark:bg-gray-900"
                                            />
                                            <span class="text-sm text-text-secondary dark:text-text-secondary">
                                                {{ String.fromCharCode(65 + choiceIdx) }}. {{ choice }}
                                            </span>
                                        </label>
                                    </div>

                                    <!-- Desktop Dropdown for Multiple Choice -->
                                    <select
                                        v-if="item.type === 'multiple_choice'"
                                        v-model="item.correct_answer"
                                        class="mt-1 hidden lg:block w-full border-border-light dark:border-border-dark dark:bg-gray-900 dark:text-text-secondary focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm sm:text-sm"
                                    >
                                        <option value="">Select correct answer</option>
                                        <option
                                            v-for="(choice, choiceIdx) in item.choices.filter((c) => c && c.trim() !== '')"
                                            :key="`existing-desktop-choice-${index}-${choiceIdx}`"
                                            :value="choice"
                                        >
                                            {{ String.fromCharCode(65 + choiceIdx) }}. {{ choice }}
                                        </option>
                                    </select>

                                    <!-- Mobile/Tablet Checkbox Selector for True/False -->
                                    <div
                                        v-else-if="item.type === 'true_or_false'"
                                        class="lg:hidden mt-1 space-y-2 rounded-lg border border-border-light dark:border-border-dark p-3"
                                    >
                                        <label class="flex items-center gap-2 cursor-pointer">
                                            <input
                                                type="checkbox"
                                                :checked="item.correct_answer === 'True'"
                                                @change="setExistingCorrectAnswerOption(item, 'True', $event.target.checked)"
                                                class="rounded border-border-light text-indigo-600 focus:ring-indigo-500 dark:border-border-dark dark:bg-gray-900"
                                            />
                                            <span class="text-sm text-text-secondary dark:text-text-secondary">True</span>
                                        </label>
                                        <label class="flex items-center gap-2 cursor-pointer">
                                            <input
                                                type="checkbox"
                                                :checked="item.correct_answer === 'False'"
                                                @change="setExistingCorrectAnswerOption(item, 'False', $event.target.checked)"
                                                class="rounded border-border-light text-indigo-600 focus:ring-indigo-500 dark:border-border-dark dark:bg-gray-900"
                                            />
                                            <span class="text-sm text-text-secondary dark:text-text-secondary">False</span>
                                        </label>
                                    </div>

                                    <!-- Desktop Dropdown for True/False -->
                                    <select
                                        v-else-if="item.type === 'true_or_false'"
                                        v-model="item.correct_answer"
                                        class="mt-1 hidden lg:block w-full border-border-light dark:border-border-dark dark:bg-gray-900 dark:text-text-secondary focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm sm:text-sm"
                                    >
                                        <option value="">Select answer</option>
                                        <option value="True">True</option>
                                        <option value="False">False</option>
                                    </select>
                                    <!-- Textarea for Identification -->
                                    <textarea
                                        v-else
                                        v-model="item.correct_answer"
                                        rows="1"
                                        class="mt-1 block w-full border-border-light dark:border-border-dark dark:bg-gray-900 dark:text-text-secondary focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm sm:text-sm"
                                    ></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- Sticky Form Actions Footer (Outside form but triggers it) -->
            <div
                class="fixed bottom-0 right-0 z-30 w-full lg:w-[calc(100%-16rem)] flex items-center justify-end gap-3 p-4 bg-white/90 dark:bg-gray-900/90 backdrop-blur-sm border-t border-gray-200 dark:border-gray-800 shadow-[0_-4px_6px_-1px_rgba(0,0,0,0.05)]"
            >
                <Link
                    :href="route('instructor.lessons.index')"
                    class="px-5 py-2.5 text-sm font-medium text-text-secondary bg-surface dark:bg-surface-dark-muted border border-border-light dark:border-border-dark rounded-lg hover:bg-gray-50 dark:hover:bg-white/5 transition-colors shadow-sm"
                >
                    Cancel
                </Link>
                <button
                    @click="submitForm"
                    :disabled="form.processing || questions.length === 0"
                    class="px-5 py-2.5 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed shadow-sm"
                >
                    <span v-if="form.processing">Saving...</span>
                    <span v-else>Save Assessment</span>
                </button>
            </div>
        </div>
    </div>
</div>
    </InstructorLayout>
</template>

<script setup>
import { ref, computed } from "vue";
import { useForm, Link, router } from "@inertiajs/vue3";
import { Head } from "@inertiajs/vue3";
import InstructorLayout from "@/Layouts/InstructorLayout.vue";
import InputLabel from "@/Components/InputLabel.vue";
import TextInput from "@/Components/TextInput.vue";
import InputError from "@/Components/InputError.vue";
import SectionAssignment from "@/Components/SectionAssignment.vue";

const props = defineProps({
    subjects: Array,
    departments: Array,
    sections: Array,
});

const form = useForm({
    subject_id: "",
    title: "",
    status: "draft",
    section_ids: [],
    questions: [],
    file: null,
});

const questions = ref([]);
const showAddQuestionForm = ref(false);

const fileName = ref("");
const fileError = ref("");
const isDragging = ref(false);
const showFilePanel = ref(false);

const expandedQuestionIndex = ref(null);
const toggleQuestion = (index) => {
    expandedQuestionIndex.value = expandedQuestionIndex.value === index ? null : index;
};

// Section Assignment panel toggle
const showSectionPanel = ref(false);

// Get section name for display chips
const getSectionDisplayName = (sectionId) => {
    const section = (props.sections || []).find(s => s.id === sectionId);
    return section ? section.name : 'Unknown';
};

// Question type counts
const questionCounts = computed(() => {
    return {
        multiple_choice: questions.value.filter(
            (item) => item.type === "multiple_choice"
        ).length,
        identification: questions.value.filter(
            (item) => item.type === "identification"
        ).length,
        true_or_false: questions.value.filter(
            (item) => item.type === "true_or_false"
        ).length,
        total: questions.value.length,
    };
});

const newQuestion = ref({
    type: "multiple_choice",
    question: "",
    choices: ["", "", "", ""],
    correct_answer: "",
});
const errors = ref({
    question: "",
    choices: "",
    correct_answer: "",
});

const formatType = (type) => {
    const types = {
        multiple_choice: "Multiple Choice",
        identification: "Identification",
        true_or_false: "True/False",
    };
    return types[type] || type;
};

const handleTypeChange = () => {
    if (newQuestion.value.type === "multiple_choice") {
        if (newQuestion.value.choices.length === 0) {
            newQuestion.value.choices = ["", "", "", ""];
        }
    } else {
        newQuestion.value.choices = [];
    }
    newQuestion.value.correct_answer = "";
    clearErrors();
};

const setQuestionType = (type) => {
    if (newQuestion.value.type === type) return;
    newQuestion.value.type = type;
    handleTypeChange();
};

const setCorrectAnswerOption = (value, checked) => {
    newQuestion.value.correct_answer = checked ? value : "";
    validateCorrectAnswer();
};

const setExistingCorrectAnswerOption = (item, value, checked) => {
    item.correct_answer = checked ? value : "";
};

const handleFileSelect = (event) => {
    const file = event.target.files[0];
    if (file) {
        form.file = file;
        fileName.value = file.name;
        fileError.value = "";
    }
};

const handleDrop = (event) => {
    isDragging.value = false;
    event.preventDefault();
    const file = event.dataTransfer?.files?.[0];
    if (file) {
        form.file = file;
        fileName.value = file.name;
        fileError.value = "";
    }
};

const removeFile = () => {
    form.file = null;
    fileName.value = "";
    fileError.value = "";
    // Reset the input so the same file can be selected again
    const input = document.getElementById('file_upload');
    if (input) input.value = '';
};

const addChoice = () => {
    if (newQuestion.value.choices.length < 8) {
        newQuestion.value.choices.push("");
    }
};

const removeChoice = (index) => {
    // Allow removal only if there are more than 4 choices
    if (newQuestion.value.choices.length > 4) {
        // Store the choice being removed before removing it
        const removedChoice = newQuestion.value.choices[index];
        newQuestion.value.choices.splice(index, 1);
        // Reset correct answer if it was the removed choice
        if (newQuestion.value.correct_answer === removedChoice) {
            newQuestion.value.correct_answer = "";
        }
        // Clear choices error when valid
        if (validateChoices()) {
            errors.value.choices = "";
        }
    }
};

// Validation functions
const validateQuestion = () => {
    if (!newQuestion.value.question.trim()) {
        errors.value.question = "Question is required";
        return false;
    }
    errors.value.question = "";
    return true;
};

const validateChoices = () => {
    if (newQuestion.value.type === "multiple_choice") {
        const filledChoices = newQuestion.value.choices.filter(
            (c) => c.trim() !== ""
        );
        if (filledChoices.length < 4) {
            errors.value.choices =
                "Multiple choice questions must have at least 4 choices";
            return false;
        }
    }
    errors.value.choices = "";
    return true;
};

const validateCorrectAnswer = () => {
    if (newQuestion.value.type === "multiple_choice") {
        if (!newQuestion.value.correct_answer) {
            errors.value.correct_answer = "Please select the correct answer";
            return false;
        }
        const filledChoices = newQuestion.value.choices.filter(
            (c) => c.trim() !== ""
        );
        if (!filledChoices.includes(newQuestion.value.correct_answer)) {
            errors.value.correct_answer =
                "Correct answer must match one of the choices";
            return false;
        }
    } else if (newQuestion.value.type === "true_or_false") {
        if (!newQuestion.value.correct_answer) {
            errors.value.correct_answer = "Please select True or False";
            return false;
        }
    } else {
        // Identification
        if (!newQuestion.value.correct_answer.trim()) {
            errors.value.correct_answer = "Please enter the correct answer";
            return false;
        }
    }
    errors.value.correct_answer = "";
    return true;
};

const clearErrors = () => {
    errors.value = {
        question: "",
        choices: "",
        correct_answer: "",
    };
};

const resetForm = () => {
    newQuestion.value = {
        type: "multiple_choice",
        question: "",
        choices: ["", "", "", ""],
        correct_answer: "",
    };
    clearErrors();
};

const addQuestion = () => {
    // Run all validations
    const isQuestionValid = validateQuestion();
    const isChoicesValid = validateChoices();
    const isCorrectAnswerValid = validateCorrectAnswer();

    // If any validation fails, stop here (errors are already set)
    if (!isQuestionValid || !isChoicesValid || !isCorrectAnswerValid) {
        return;
    }

    // All validations passed, add the question
    if (newQuestion.value.type === "multiple_choice") {
        const filledChoices = newQuestion.value.choices.filter(
            (c) => c.trim() !== ""
        );

        questions.value.push({
            type: "multiple_choice",
            question: newQuestion.value.question.trim(),
            choices: filledChoices,
            correct_answer: newQuestion.value.correct_answer,
        });
    } else if (newQuestion.value.type === "true_or_false") {
        questions.value.push({
            type: "true_or_false",
            question: newQuestion.value.question.trim(),
            choices: null,
            correct_answer: newQuestion.value.correct_answer,
        });
    } else {
        // Identification
        questions.value.push({
            type: "identification",
            question: newQuestion.value.question.trim(),
            choices: null,
            correct_answer: newQuestion.value.correct_answer.trim(),
        });
    }

    // Reset form and close
    resetForm();
    clearErrors();
    showAddQuestionForm.value = false;
};

const cancelAddQuestion = () => {
    resetForm();
    showAddQuestionForm.value = false;
};

const removeQuestion = (index) => {
    if (confirm("Are you sure you want to delete this question?")) {
        questions.value.splice(index, 1);
        form.questions = questions.value;
    }
};

const submitForm = () => {
    if (questions.value.length === 0) {
        alert("Please add at least one question before saving.");
        return;
    }

    form.questions = questions.value;

    form.post(route("instructor.lessons.storeManual"), {
        forceFormData: true,
        onSuccess: () => {
            // Success handled by redirect
        },
        onError: (errors) => {
            // Errors are displayed via form.errors
            console.error("Validation errors:", errors);
        },
    });
};
</script>
