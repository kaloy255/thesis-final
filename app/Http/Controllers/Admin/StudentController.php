<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StudentImportRequest;
use App\Http\Requests\Admin\StudentStoreRequest;
use App\Http\Requests\Admin\StudentUpdateRequest;
use App\Models\Department;
use App\Models\Log as LogModel;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Rap2hpoutre\FastExcel\FastExcel;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudentController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->string('search')->toString();
        $departmentId = $request->input('department_id');
        $sectionId = $request->input('section_id');
        $perPage = min(max((int)$request->input('per_page', 10), 1), 100);

        $students = User::with(['student.section.department'])
            ->where('role', 'student')
            ->when($search, function ($query, $term) {
            $query->where(function ($q) use ($term) {
                    $q->where('name', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%");
                }
                );
            })
            ->when($sectionId, function ($query, $id) {
            $query->whereHas('student', fn($q) => $q->where('section_id', $id));
        })
            ->when($departmentId && !$sectionId, function ($query, $id) {
            $query->whereHas('student.section', fn($q) => $q->where('department_id', $id));
        })
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('Admin/Students/Index', [
            'students' => $students,
            'departments' => Department::orderBy('name')->get(),
            'sections' => Section::with('department')->withCount('students')->orderBy('name')->get(),
            'filters' => [
                'search' => $search,
                'department_id' => $departmentId,
                'section_id' => $sectionId,
                'per_page' => $perPage,
            ],
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/Students/Create', [
            'sections' => Section::orderBy('name')->get(),
        ]);
    }

    public function store(StudentStoreRequest $request)
    {
        $data = $request->validated();

        $user = User::create([
            'email' => $data['email'],
            'name' => $data['name'],
            'role' => 'student',
            'password' => Hash::make('chcc@2025'),
        ]);

        Student::create([
            'user_id' => $user->id,
            'section_id' => $data['section_id'],
        ]);

        $this->logAction($request->user(), "Created student {$user->name}");

        return redirect()->route('admin.students.index')->with('success', 'Student created.');
    }

    public function edit(User $student)
    {
        if ($student->role !== 'student') {
            abort(404);
        }

        $student->load(['student']);

        return Inertia::render('Admin/Students/Edit', [
            'student' => $student,
            'sections' => Section::orderBy('name')->get(),
        ]);
    }

    public function update(StudentUpdateRequest $request, User $student)
    {
        if ($student->role !== 'student') {
            abort(404);
        }

        $data = $request->validated();

        $student->email = $data['email'];
        $student->name = $data['name'];

        $student->save();

        Student::updateOrCreate(
        ['user_id' => $student->id],
        ['section_id' => $data['section_id']]
        );

        $this->logAction($request->user(), "Updated student {$student->name}");

        return redirect()->route('admin.students.index')->with('success', 'Student updated.');
    }

    public function resetPassword(Request $request, User $student)
    {
        if ($student->role !== 'student') {
            abort(404);
        }

        $student->password = Hash::make('chcc@2025');
        $student->save();

        $this->logAction($request->user(), "Reset password for student {$student->name}");

        return back()->with('success', 'Password reset successfully.');
    }

    public function destroy(Request $request, User $student)
    {
        if ($student->role !== 'student') {
            abort(404);
        }

        $name = $student->name;
        $student->delete();

        $this->logAction($request->user(), "Deleted student {$name}");

        return redirect()->route('admin.students.index')->with('success', 'Student deleted.');
    }

    public function import(StudentImportRequest $request)
    {
        set_time_limit(0);
        ini_set('memory_limit', '256M');

        $file = $request->file('file');
        $fallbackSectionId = $request->input('section_id');
        $imported = 0;
        $errors = [];
        $skipped = 0;
        $rowNumber = 1;

        $programMapping = [
            'BEED' => 'STE',
            'BSEDENG' => 'STE',
            'BSEDFIL' => 'STE',
            'BSEDMATH' => 'STE',
            'BSEDSOCSCI' => 'STE',
            'BSEDE' => 'STE',
            'BSHM' => 'SHM',
            'BSA' => 'SBAA',
            'BSBAFM' => 'SBAA',
            'BSCRIM' => 'SCJE',
            'BSISM' => 'SCJE',
            'BSCS' => 'SCS',
            'BSN' => 'SN',
        ];

        try {
            $allRows = (new FastExcel)->import($file);
            if ($allRows->isEmpty()) {
                return back()->with('flash', [
                    'type' => 'error',
                    'message' => 'The Excel file is empty. Please upload a file with data.',
                ]);
            }

            $firstRow = $allRows->first();
            $headers = is_array($firstRow) ? array_keys($firstRow) : array_keys((array) $firstRow);
            $hasId = false;
            $hasName = false;
            $idKey = null;
            $nameKey = null;
            $emailKey = null;
            $sectionKey = null;
            $courseKey = null;

            foreach ($headers as $header) {
                $normalizedHeader = strtolower(trim($header));
                if (in_array($normalizedHeader, ['id number', 'id_number', 'student#', 'student #'], true)) {
                    $hasId = true;
                    $idKey = $header;
                } elseif (in_array($normalizedHeader, ['name', 'full name', 'names'], true)) {
                    $hasName = true;
                    $nameKey = $header;
                } elseif (in_array($normalizedHeader, ['email', 'emails', 'email address'], true)) {
                    $emailKey = $header;
                } elseif (in_array($normalizedHeader, ['section'], true)) {
                    $sectionKey = $header;
                } elseif (in_array($normalizedHeader, ['course', 'program'], true)) {
                    $courseKey = $header;
                }
            }

            if (!$hasId || !$hasName) {
                $foundHeaders = implode(', ', array_map(fn($h) => '"' . $h . '"', $headers));
                return back()->with('flash', [
                    'type' => 'error',
                    'message' => "Invalid Excel format. Required columns: 'id_number' and 'name'. Accepted ID headers: 'id number', 'id_number', 'student#', 'student #'. Found columns: {$foundHeaders}. Please download the template and follow the correct format.",
                ]);
            }

            // Reference Data
            $departmentsByCode = Department::all()->keyBy(fn($d) => strtoupper(trim($d->code)));
            $existingSections = Section::all()->keyBy(fn($s) => strtoupper(trim($s->name)));

            // Hash password once (reused for all rows)
            $hashedPassword = Hash::make('chcc@2025');

            // Pre-load existing emails (1 query instead of N)
            $existingEmails = User::pluck('email')->map(fn($v) => strtolower((string)$v))->flip()->toArray();

            DB::beginTransaction();

            $chunkSize = 50;
            $userBatch = [];
            $sectionBatchMap = []; // Map the section for each user in the batch array
            $now = now();

            foreach ($allRows as $line) {
                $rowNumber++;
                
                $lineArr = (array) $line;

                $idValue = isset($lineArr[$idKey]) ? trim((string)$lineArr[$idKey]) : null;
                $name = isset($lineArr[$nameKey]) ? trim((string)$lineArr[$nameKey]) : null;
                $emailStr = $emailKey !== null && isset($lineArr[$emailKey]) ? trim((string)$lineArr[$emailKey]) : null;
                $sectionStr = $sectionKey !== null && isset($lineArr[$sectionKey]) ? trim((string)$lineArr[$sectionKey]) : null;
                $courseStr = $courseKey !== null && isset($lineArr[$courseKey]) ? trim((string)$lineArr[$courseKey]) : null;

                if (empty($idValue) && empty($name)) {
                    continue; // skip empty rows
                }

                if ($idValue === null || $idValue === '') {
                    $email = null;
                } else {
                    if (!empty($emailStr) && str_contains($emailStr, '@')) {
                        $email = strtolower($emailStr);
                    } elseif (str_contains($idValue, '@')) {
                        $email = strtolower($idValue);
                    } else {
                        $email = strtolower($idValue) . '@chcc.edu.ph';
                    }
                }

                if (empty($email)) {
                    $errors[] = "Row {$rowNumber}: ID or Email is required";
                    $skipped++;
                    continue;
                }
                if (!str_ends_with($email, '@chcc.edu.ph')) {
                    $errors[] = "Row {$rowNumber}: Email must end with @chcc.edu.ph ({$email})";
                    $skipped++;
                    continue;
                }
                if (empty($name)) {
                    $errors[] = "Row {$rowNumber}: Name is required (Email: {$email})";
                    $skipped++;
                    continue;
                }
                if (isset($existingEmails[$email])) {
                    $errors[] = "Row {$rowNumber}: Email '{$email}' already exists";
                    $skipped++;
                    continue;
                }

                // Resolve Section ID dynamically
                $resolvedSectionId = $fallbackSectionId;
                if (!empty($sectionStr)) {
                    $sectionUpper = strtoupper($sectionStr);
                    
                    if ($existingSections->has($sectionUpper)) {
                        $resolvedSectionId = $existingSections->get($sectionUpper)->id;
                    } else {
                        // Attempt autocreation
                        $programCode = null;
                        if (!empty($courseStr)) {
                            $programCode = strtoupper($courseStr);
                        } elseif (preg_match('/^\d+\s*([A-Za-z]+)/', $sectionUpper, $matches)) {
                            $programCode = strtoupper($matches[1]);
                        }

                        $deptId = null;
                        if ($programCode) {
                            $mappedDeptCode = $programMapping[$programCode] ?? $programCode;
                            if ($departmentsByCode->has($mappedDeptCode)) {
                                $deptId = $departmentsByCode->get($mappedDeptCode)->id;
                            }
                        }

                        if ($deptId) {
                            $newSection = Section::create([
                                'name' => $sectionUpper,
                                'department_id' => $deptId
                            ]);
                            $existingSections->put($sectionUpper, $newSection);
                            $resolvedSectionId = $newSection->id;
                        } else {
                            if (!$fallbackSectionId) {
                                $errors[] = "Row {$rowNumber}: Section '{$sectionUpper}' is unrecognized and its Department could not be determined to be autocreated.";
                                $skipped++;
                                continue;
                            }
                        }
                    }
                }

                if (!$resolvedSectionId) {
                    $errors[] = "Row {$rowNumber}: Could not determine section for '{$name}' - Provide a Section in the file or select a Fallback Section in the UI.";
                    $skipped++;
                    continue;
                }

                $existingEmails[$email] = true;

                $userBatch[] = [
                    'email' => $email,
                    'name' => $name,
                    'role' => 'student',
                    'password' => $hashedPassword,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                $sectionBatchMap[] = $resolvedSectionId;

                if (count($userBatch) >= $chunkSize) {
                    User::insert($userBatch);
                    $firstId = DB::connection()->getPdo()->lastInsertId();
                    
                    $studentBatch = [];
                    foreach ($userBatch as $index => $u) {
                        $uid = (int)$firstId + $index;
                        $studentBatch[] = [
                            'user_id' => $uid,
                            'section_id' => $sectionBatchMap[$index],
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                    Student::insert($studentBatch);
                    $imported += count($userBatch);
                    
                    $userBatch = [];
                    $sectionBatchMap = [];
                }
            }

            if (!empty($userBatch)) {
                User::insert($userBatch);
                $firstId = DB::connection()->getPdo()->lastInsertId();
                
                $studentBatch = [];
                foreach ($userBatch as $index => $u) {
                    $uid = (int)$firstId + $index;
                    $studentBatch[] = [
                        'user_id' => $uid,
                        'section_id' => $sectionBatchMap[$index],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
                Student::insert($studentBatch);
                $imported += count($userBatch);
            }

            DB::commit();

            $this->logAction(
                $request->user(),
                "Imported {$imported} students" . ($skipped > 0 ? " ({$skipped} skipped)" : "")
            );

            if ($imported === 0) {
                return back()->with('flash', [
                    'type' => 'error',
                    'message' => 'No students were imported. Please check the errors below.',
                    'errors' => $errors,
                ]);
            }

            $message = "Successfully imported {$imported} student(s)";
            if ($skipped > 0) {
                $message .= " ({$skipped} skipped)";
            }

            return back()->with('flash', [
                'type' => 'success',
                'message' => $message,
                'errors' => count($errors) > 0 ? $errors : null,
            ]);

        }
        catch (\Exception $e) {
            DB::rollBack();

            Log::error('Import failed:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return back()->with('flash', [
                'type' => 'error',
                'message' => 'Import failed: ' . $e->getMessage(),
            ]);
        }
    }

    public function downloadTemplate(): StreamedResponse
    {
        $data = [
            [
                'Student#' => '20210001',
                'name' => 'Juan Dela Cruz',
            ],
            [
                'Student#' => '20210002',
                'name' => 'Maria Santos',
            ],
            [
                'Student#' => '20210003',
                'name' => 'Jose Rizal',
            ],
        ];

        return (new FastExcel(collect($data)))
            ->download('student_import_template.xlsx');
    }

    private function logAction(User $actor, string $description): void
    {
        LogModel::create([
            'user_id' => $actor->id,
            'description' => $description,
            'role' => $actor->role,
        ]);
    }
}