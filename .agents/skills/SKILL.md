---
name: Adaptive Mastery – AI-Powered Assessment LMS
description: Complete codebase guide for the Adaptive Mastery thesis project — an AI-powered Learning Management and Assessment System built with Laravel 10, Inertia.js, Vue 3, PrimeVue, Pinia, and Tailwind CSS.
---

# Adaptive Mastery — Project Skill Guide

## 1. System Purpose & Overview

**Adaptive Mastery** is an AI-powered Learning Management and Assessment System designed for educational institutions. Its core mission is to:

1. **Automate assessment generation** — Instructors upload lesson files (PDF, DOCX, PPTX, TXT), the system extracts text, sends it to AI providers, and generates quiz questions categorized by Bloom's Taxonomy levels and question types (MCQ, Identification, True/False).
2. **Support adaptive learning** — When a student scores below 100%, the system can generate a new *adaptive assessment* focused specifically on the student's weak areas (wrong answers), reinforcing learning gaps.
3. **Provide manual assessment creation** — Instructors can also create assessments manually without uploading files.
4. **Anti-cheating detection** — The system detects and logs tab-switching, page-leaving, and window-blur events during exams, sending real-time notifications to instructors.
5. **Admin oversight** — Admins manage all users, departments, sections, subjects, and monitor AI token usage/costs.

---

## 2. Tech Stack

| Layer            | Technology                                          |
|------------------|------------------------------------------------------|
| **Backend**      | Laravel 10 (PHP 8.3)                                |
| **Frontend**     | Vue 3 via Inertia.js (SPA-like, server-driven)      |
| **Styling**      | Tailwind CSS 3 with CSS custom properties (dark mode)|
| **UI Components**| PrimeVue 4                                          |
| **State Mgmt**   | Pinia (used for lesson review flow)                 |
| **Icons**        | Iconify + PrimeIcons                                |
| **Charts**       | Chart.js (Admin AI usage dashboard)                 |
| **Build Tool**   | Vite 5                                              |
| **Routing**      | Ziggy (Laravel named routes in JS)                  |
| **Auth**         | Laravel Breeze (session-based)                      |
| **AI Providers** | OpenAI (GPT-3.5-turbo), Groq (Llama 3.3 70B), Gemini (2.5 Flash) |
| **Doc Parsing**  | smalot/pdfparser, phpoffice/phpword, phpoffice/phppresentation |
| **Database**     | MySQL                                               |
| **DevOps**       | Docker (Dockerfile present)                         |

---

## 3. User Roles & Access Control

The system has **three roles**, enforced via middleware and route groups:

| Role           | Middleware        | Route Prefix | Dashboard Route          |
|----------------|-------------------|-------------|--------------------------|
| **Admin**      | `EnsureAdmin`     | `/admin`    | `admin.dashboard`        |
| **Instructor** | `EnsureInstructor`| `/instructor`| `instructor.dashboard`  |
| **Student**    | `EnsureStudent`   | `/student`  | `student.dashboard`      |

- The `User` model has a `role` field (`admin`, `instructor`, `student`).
- Instructors are linked to a `Professor` model (1:1 via `user_id`).
- Students are linked to a `Student` model (1:1 via `user_id`), which is associated with a `Section`.
- `RedirectIfAuthenticated` middleware routes authenticated users to their role-specific dashboard.

---

## 4. Project Structure

### 4.1 Backend (`app/`)

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── Admin/              # Admin CRUD controllers
│   │   │   ├── DashboardController.php
│   │   │   ├── StudentController.php
│   │   │   ├── InstructorController.php
│   │   │   ├── DepartmentController.php
│   │   │   ├── SectionController.php
│   │   │   ├── SubjectController.php
│   │   │   ├── ProfessorSubjectController.php
│   │   │   ├── AiUsageController.php
│   │   │   ├── LogController.php
│   │   │   └── ProfileController.php
│   │   ├── Instructor/         # Instructor-specific controllers
│   │   │   ├── DashboardController.php
│   │   │   ├── LessonController.php    # ← Core: AI generation, review, CRUD
│   │   │   ├── SubjectController.php   # Student join requests
│   │   │   ├── AssessmentHistoryController.php
│   │   │   ├── NotificationController.php
│   │   │   ├── LogController.php
│   │   │   └── ProfileController.php
│   │   ├── Student/            # Student-specific controllers
│   │   │   ├── DashboardController.php
│   │   │   ├── AssessmentController.php # ← Core: take, submit, adaptive
│   │   │   ├── SubjectController.php   # Join subjects
│   │   │   ├── NotificationController.php
│   │   │   └── ProfileController.php
│   │   └── Auth/               # Laravel Breeze auth controllers
│   ├── Middleware/
│   │   ├── EnsureAdmin.php           # Blocks non-admin users
│   │   ├── EnsureInstructor.php      # Blocks non-instructor users
│   │   ├── EnsureStudent.php         # Blocks non-student users
│   │   ├── HandleInertiaRequests.php  # Shares auth user + flash data
│   │   └── RedirectIfAuthenticated.php # Role-based redirect
│   └── Requests/
│       ├── Instructor/
│       │   ├── StoreLessonRequest.php       # AI-generated lesson validation
│       │   └── StoreManualLessonRequest.php  # Manual lesson validation
│       └── Student/
│           ├── StoreAssessmentRequest.php
│           └── SubmitAssessmentRequest.php
├── Models/
│   ├── User.php               # role field, hasOne professor/student
│   ├── Professor.php          # belongsTo user, department; belongsToMany subjects
│   ├── Student.php            # belongsTo user, section
│   ├── Department.php
│   ├── Section.php            # belongsTo department
│   ├── Subject.php            # belongsToMany professors, students
│   ├── Lesson.php             # belongsTo subject, professor; hasMany assessments
│   ├── Assessment.php         # belongsTo lesson; hasMany items, attempts, sections
│   ├── AssessmentItem.php     # question, type, choices, correct_answer, bloom_level
│   ├── AssessmentAttempt.php  # student attempt tracking
│   ├── AssessmentSection.php  # pivot: assessment ↔ section
│   ├── StudentAnswer.php      # student's answer per attempt item
│   ├── StudentSubject.php     # pivot: student ↔ subject (with status)
│   ├── ProfessorSubject.php   # pivot: professor ↔ subject
│   ├── Notification.php       # user notifications (cheating alerts, etc.)
│   ├── Log.php                # system activity logs
│   ├── AiTokenUsage.php       # tracks AI API token consumption
│   ├── SystemSetting.php
│   └── LessonProcessingLog.php
└── Services/
    ├── AI/
    │   ├── AIServiceInterface.php    # Contract: generateAssessment(), generateChunk()
    │   ├── AIServiceManager.php      # ← Core: orchestrator with fallback + chunking
    │   ├── OpenAIProvider.php        # OpenAI GPT implementation
    │   ├── GroqProvider.php          # Groq Llama implementation
    │   ├── GeminiProvider.php        # Google Gemini implementation
    │   ├── AIResponseParser.php      # Parses AI JSON output into structured data
    │   ├── BloomsTaxonomyConfig.php  # Bloom's levels, prompt instructions, JSON schema
    │   └── BloomsValidator.php       # Post-generation validation of bloom_level tags
    ├── Assessment/
    │   └── AssessmentGenerator.php   # Saves parsed AI output to DB as AssessmentItems
    ├── ContentProcessing/
    │   ├── ContentChunker.php        # Splits large text into chunks
    │   ├── ContextSummarizer.php     # Summarizes previous chunks for context
    │   └── TokenCalculator.php       # Estimates token count from text
    ├── FileProcessing/
    │   ├── FileExtractorInterface.php # Contract for all extractors
    │   ├── FileExtractorFactory.php   # Factory: MIME → Extractor
    │   ├── DocxExtractor.php
    │   ├── PdfExtractor.php
    │   ├── PptxExtractor.php
    │   ├── TxtExtractor.php
    │   ├── FileValidator.php          # Type, size, password, media validation
    │   └── TextCleaner.php
    └── Logging/
        └── ProcessingLogger.php
```

### 4.2 Frontend (`resources/js/`)

```
resources/js/
├── app.js                     # Inertia + Pinia + PrimeVue + Ziggy bootstrap
├── bootstrap.js               # Axios + Echo setup
├── Components/                # Shared Vue components
│   ├── Breadcrumb.vue
│   ├── CardAssessment.vue     # Assessment card used in Instructor lesson list
│   ├── ConfirmationModal.vue
│   ├── DataTable.vue
│   ├── LoadingIndicator.vue
│   ├── NotificationDropdown.vue
│   ├── Pagination.vue
│   ├── ProcessingModal.vue    # AI processing status modal
│   ├── SearchableSelect.vue
│   ├── SectionAssignment.vue  # Department → Section multi-select component
│   └── Toast.vue
├── Layouts/
│   ├── AdminLayout.vue        # Sidebar + navbar for admin
│   ├── InstructorLayout.vue   # Sidebar + navbar for instructor
│   ├── StudentLayout.vue      # Sidebar + navbar for student
│   └── GuestLayout.vue        # Login/register layout
├── Pages/
│   ├── Admin/
│   │   ├── Dashboard.vue      # Stats + AI token usage chart (Chart.js)
│   │   ├── Students/          # CRUD index
│   │   ├── Instructors/       # CRUD index
│   │   ├── Departments/
│   │   ├── Sections/
│   │   ├── Subjects/
│   │   ├── ProfessorSubjects/ # Assignment management
│   │   ├── Logs/
│   │   └── Settings.vue
│   ├── Instructor/
│   │   ├── Dashboard.vue      # Stats + recent activity
│   │   ├── Lessons/
│   │   │   ├── Index.vue      # Lesson list with filtering (search, status, sections)
│   │   │   ├── Create.vue     # File upload + AI config (Bloom's levels, distribution)
│   │   │   ├── CreateManual.vue # Manual question entry form
│   │   │   ├── Review.vue     # Review AI-generated questions before saving (Pinia)
│   │   │   └── Edit.vue       # Edit existing assessment questions
│   │   ├── Assessments/
│   │   │   ├── History.vue    # Class-level assessment history
│   │   │   └── HistoryStudent.vue # Per-student attempt details
│   │   ├── Subjects/         # Manage subject join requests
│   │   ├── Logs/
│   │   └── Settings.vue
│   └── Student/
│       ├── Dashboard.vue
│       ├── Assessments/
│       │   ├── Index.vue      # Available assessments list
│       │   ├── Take.vue       # Assessment-taking interface
│       │   ├── Results.vue    # Results + adaptive generation trigger
│       │   └── History.vue    # Attempt history with scores
│       ├── Subjects/         # Join subject by code
│       └── Settings.vue
├── Stores/
│   ├── useLessonReview.js     # Pinia store: temp AI data with localStorage backup
│   ├── useBreadcrumbs.js
│   ├── useLoading.js
│   ├── useTheme.js            # Dark/light mode toggle
│   └── useToast.js
└── utils/
    └── formatTime.js
```

### 4.3 Config (`config/`)

- **`ai_models.php`** — Central AI configuration:
  - Provider configs (API keys, models, token limits)
  - Chunking settings (buffer tokens, overlap percentage)
  - Primary provider and fallback order
  - Request timeout

### 4.4 Database Schema

**Core tables** (created in single migration `2025_12_17_141418_create_admin_core_tables.php`):

```
departments       → id, name
sections          → id, department_id (FK), name
subjects          → id, name, code, description
professors        → id, user_id (FK), department_id (FK)
students          → id, user_id (FK), section_id (FK)
professor_subject → professor_id (FK), subject_id (FK)
student_subject   → student_id (FK), subject_id (FK), status
lessons           → id, subject_id (FK), professor_id (FK), title, path, extracted_content
assessments       → id, lesson_id (FK), title, type, status, parent_assessment_id, source_attempt_id
assessment_section → assessment_id (FK), section_id (FK)
assessment_items  → id, assessment_id (FK), question, type, choices (JSON), correct_answer, bloom_level
assessment_attempt → id, student_id (FK), assessment_id (FK), attempt_no
student_answer    → id, attempt_id (FK), assessment_item_id (FK), type, choices (JSON), correct_answer (bool)
notifications     → id, user_id (FK), description, read_at
logs              → id, user_id (FK), description, role
ai_token_usages   → id, user_id, provider, model, feature, input_tokens, output_tokens, total_tokens, is_estimated, meta
```

---

## 5. Core Workflows

### 5.1 AI-Generated Assessment Flow (The Heart of the System)

```
Instructor uploads file → File Validation → Text Extraction → AI Generation → Review → Save to DB
```

**Step-by-step:**

1. **`Create.vue`** — Instructor selects a subject, uploads a file, selects Bloom's Taxonomy levels, and configures question distribution (MCQ/ID/TF per level).
2. **`LessonController@store`** — Backend pipeline:
   - `FileValidator::validateAll()` — Checks file type (DOCX/PDF/PPTX/TXT), size (<10MB), no password protection, no media.
   - `FileExtractorFactory::make()` — Selects the right extractor based on MIME type.
   - `TextCleaner::clean()` — Normalizes extracted text.
   - `AIServiceManager::generateAssessment()` — Orchestrates AI generation:
     - If content fits in model's safe limit → single request
     - If too large → `ContentChunker` splits it, questions distributed pro-rata across chunks
     - Provider fallback: tries `openai` → `gemini` → `groq`, with one retry per provider
     - `BloomsValidator::validate()` — Post-generation validation ensuring bloom_level tags are correct
   - `AIResponseParser::parse()` — Validates the AI JSON output structure.
3. **Response** — Returns `lessonReviewData` + a `reviewToken` (64-char random string) back to `Create.vue`.
4. **`Create.vue`** → Saves data to Pinia store (`useLessonReviewStore`) → Redirects to `Review.vue` via token.
5. **`Review.vue`** — Loads data from Pinia (backed by localStorage). Instructor can:
   - Edit/delete/reorder questions
   - Assign sections (department → section picker)
   - Choose draft or published status
   - Save → `LessonController@saveFromReview` → DB transaction
   - Cancel → `LessonController@cancelReview` → deletes uploaded file

### 5.2 Manual Assessment Creation

```
Instructor fills form → Submits → LessonController@storeManual → DB
```

- `CreateManual.vue` — Instructor enters title, subject, and adds questions one by one with type, choices, correct answer.
- No AI involved, no file upload. Directly saved via `storeManual()`.

### 5.3 Student Assessment Taking

```
Student sees available assessments → Takes quiz → Submits → Scores → (Optional) Adaptive retake
```

1. **`Student/Assessments/Index.vue`** — Lists assessments accessible by the student (scoped by section or enrolled subjects).
2. **`Take.vue`** — Displays questions (choices shuffled for MCQ). Anti-cheat listeners detect tab switches/window blur and POST to `logCheating()`.
3. **`AssessmentController@store`** — Processes all answers:
   - `formatAnswer()` — Normalizes answer format per type.
   - `compareAnswer()` — MCQ: exact match; Identification: case-insensitive; T/F: case-insensitive.
4. **`Results.vue`** — Shows score, correct/wrong/skipped breakdown, and each question's result.
5. **Adaptive Generation** — If score < 100%, student can trigger `generateAdaptive()`:
   - Collects wrong answers → sends to AI with lesson content → generates new focused assessment
   - New assessment is linked via `parent_assessment_id` + `source_attempt_id`
   - Student is redirected to immediately take the adaptive assessment.

### 5.4 Subject Enrollment

- Students join subjects by searching/requesting. Instructors approve/decline requests via `Instructor/SubjectController`.
- Status flow: `pending` → `approved` / `declined`. Instructors can also `drop` students.

---

## 6. Key Code Patterns & Conventions

### 6.1 Service Layer Architecture
Business logic is **extracted into Services** (`app/Services/`), keeping controllers thin. Controllers call services, services handle the heavy lifting:
- `AIServiceManager` — Orchestrates multi-provider AI calls
- `AssessmentGenerator` — Transforms parsed AI data into DB records
- `FileValidator` / `FileExtractorFactory` — File processing pipeline

### 6.2 Interface + Factory Pattern (AI & File Processing)
- **`AIServiceInterface`** defines `generateAssessment()` and `generateChunk()`.
- **`FileExtractorInterface`** defines `extract()` and `validateNoMedia()`.
- **`FileExtractorFactory::make()`** maps MIME types to concrete extractors.
- Adding a new AI provider = implement the interface + register in `AIServiceManager::createProvider()`.

### 6.3 Fallback Strategy with Retry
`AIServiceManager` iterates through providers in `config('ai_models.fallback_order')`. For each provider, if the first call fails, it retries once before moving to the next provider. This ensures maximum resilience against API outages.

### 6.4 Content Chunking for Large Documents
When the input content exceeds a provider's safe token limit:
1. `ContentChunker` splits text into word-based chunks with configurable overlap.
2. Questions are distributed proportionally across chunks.
3. `ContextSummarizer` provides previous chunk summaries as context for coherent generation.

### 6.5 DB Transactions Everywhere
All multi-table writes use `DB::beginTransaction()` / `DB::commit()` / `DB::rollBack()` to ensure atomicity. This prevents orphan records if any step fails.

### 6.6 Pinia + localStorage for Review Flow
The AI-generated data is NOT stored in the database during review. Instead:
1. Data lives in Pinia state (`useLessonReviewStore`)
2. Backed by `localStorage` keyed by a 64-char token
3. Only persisted to DB when the instructor explicitly saves
4. `cancelReview()` cleans up the uploaded file and clears the store

### 6.7 Inertia.js Page Props Pattern
Controllers return data via `Inertia::render('Page/Path', [...props])`. Frontend components receive these as Vue `defineProps()`. Shared data (auth user, flash messages, CSRF token) is provided by `HandleInertiaRequests` middleware.

### 6.8 Role-Based Route Isolation
Routes are completely separated by role via middleware groups:
```php
Route::middleware(['auth', 'admin'])->prefix('admin')->as('admin.')->group(...)
Route::middleware(['auth', 'instructor'])->prefix('instructor')->as('instructor.')->group(...)
Route::middleware(['auth', 'student'])->prefix('student')->as('student.')->group(...)
```
Each role has its own controllers, even for overlapping features (e.g., `ProfileController` exists per role).

### 6.9 Assessment Access Control (Model-Level Scoping)
`Assessment::canBeAccessedBy($student)` and `Assessment::scopeAccessibleBy()` enforce that students can only see assessments that are:
- Published (`status = 'published'`)
- Assigned to their section OR linked to a subject they're enrolled in (with `approved` status)

### 6.10 Tailwind CSS Custom Properties for Theming
`tailwind.config.js` uses CSS custom properties (e.g., `--color-surface`, `--color-text-primary`) via `withOpacity()` helper, enabling dark mode toggle (class-based via `darkMode: 'class'`). Theme state managed by `useTheme.js` composable.

### 6.11 Bloom's Taxonomy Integration
- **`BloomsTaxonomyConfig`** — Centralized config with 6 levels (Remember → Create), each with action verbs, guidelines per question type, and prompt template.
- **Prompt injection** — `getPromptInstructions()` dynamically builds AI prompt sections based on the instructor's selected levels and distribution.
- **Post-validation** — `BloomsValidator` ensures every generated question has a valid `bloom_level` tag within the allowed range. Invalid/missing tags are randomly reassigned from allowed levels.

---

## 7. AI Token Tracking

Every AI call records usage in the `ai_token_usages` table via `AIServiceManager::recordTokenUsage()`:
- Captures provider, model, feature type, input/output/total tokens
- Supports both actual token counts (from provider response) and estimated counts (via `TokenCalculator`)
- Admin dashboard visualizes daily usage per provider via Chart.js

---

## 8. File Support Matrix

| Format | MIME Type                                                           | Extractor         |
|--------|---------------------------------------------------------------------|-------------------|
| DOCX   | `application/vnd.openxmlformats-officedocument.wordprocessingml.document` | `DocxExtractor`   |
| PDF    | `application/pdf`                                                   | `PdfExtractor`    |
| PPTX   | `application/vnd.openxmlformats-officedocument.presentationml.presentation` | `PptxExtractor`   |
| TXT    | `text/plain`                                                        | `TxtExtractor`    |

**Validation rules:** Max 10MB, no password protection, no embedded media (images/shapes).

---

## 9. Common Development Tasks

### Adding a New AI Provider
1. Create `app/Services/AI/NewProvider.php` implementing `AIServiceInterface`
2. Add config to `config/ai_models.php` under `providers`
3. Register in `AIServiceManager::createProvider()` match statement
4. Add to `fallback_order` array in config

### Adding a New Question Type
1. Update `AIResponseParser` to handle the new type
2. Update `AssessmentGenerator::generate()` to save the new type
3. Update `BloomsTaxonomyConfig::getJsonStructure()` to include the new type in the AI prompt
4. Update `BloomsValidator` to process the new type
5. Update frontend components (`CreateManual.vue`, `Review.vue`, `Edit.vue`, `Take.vue`, `Results.vue`)
6. Update `AssessmentController::formatAnswer()` and `compareAnswer()` for the new type

### Adding a New File Format
1. Create a new extractor class implementing `FileExtractorInterface`
2. Register the MIME type in `FileExtractorFactory::make()`
3. Update `FileValidator::$allowedMimeTypes`

### Adding a New Admin CRUD Module
1. Create model in `app/Models/`
2. Create controller in `app/Http/Controllers/Admin/`
3. Add routes in `routes/web.php` under the admin group
4. Create Vue page in `resources/js/Pages/Admin/`
5. Use `AdminLayout.vue` and update sidebar navigation

---

## 10. Environment Variables

| Variable          | Purpose                              |
|-------------------|--------------------------------------|
| `OPENAI_API_KEY`  | OpenAI GPT-3.5 Turbo API key        |
| `GROQ_API_KEY`    | Groq Llama 3.3 API key              |
| `GEMINI_API_KEY`  | Google Gemini 2.5 Flash API key     |
| `DB_DATABASE`     | MySQL database name (default: `thesis`) |
| `VITE_APP_NAME`   | Application display name            |

---

## 11. Running the Project

```bash
# Install dependencies
composer install
npm install

# Environment setup
cp .env.example .env
php artisan key:generate

# Database
php artisan migrate
php artisan db:seed   # if seeders exist

# Development
npm run dev           # Vite dev server
php artisan serve     # Laravel dev server (or use thesis.test vhost)
```

---

## 12. Key Frontend Components Reference

| Component                | Purpose                                                    |
|--------------------------|------------------------------------------------------------|
| `SectionAssignment.vue`  | Department → Section cascading multi-select for assessments |
| `ProcessingModal.vue`    | Multi-step AI processing status indicator                  |
| `CardAssessment.vue`     | Assessment card with status, items, expand/collapse        |
| `NotificationDropdown.vue` | Real-time notification dropdown (cheating alerts, etc.)  |
| `ConfirmationModal.vue`  | Reusable confirmation dialog                               |
| `SearchableSelect.vue`   | Dropdown with search filtering                             |
| `DataTable.vue`          | Reusable data table with slots                             |
| `Pagination.vue`         | Laravel pagination component for Inertia                   |
| `Toast.vue`              | Flash notification toasts                                  |
| `LoadingIndicator.vue`   | Global page transition loading indicator                   |
