<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\InstructorImportRequest;
use App\Http\Requests\Admin\InstructorStoreRequest;
use App\Http\Requests\Admin\InstructorUpdateRequest;
use App\Models\Department;
use App\Models\Log;
use App\Models\Professor;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Rap2hpoutre\FastExcel\FastExcel;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InstructorController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->string('search')->toString();
        $departmentId = $request->input('department_id');
        $perPage = min(max((int)$request->input('per_page', 10), 1), 100);

        $instructors = User::with(['professor.department'])
            ->where('role', 'instructor')
            ->when($search, function ($query, $term) {
            $query->where(function ($q) use ($term) {
                    $q->where('name', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%");
                }
                );
            })
            ->when($departmentId, function ($query, $id) {
            $query->whereHas('professor', fn($q) => $q->where('department_id', $id));
        })
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('Admin/Instructors/Index', [
            'instructors' => $instructors,
            'departments' => Department::withCount('professors')->orderBy('name')->get(),
            'filters' => [
                'search' => $search,
                'department_id' => $departmentId,
                'per_page' => $perPage,
            ],
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/Instructors/Create', [
            'departments' => Department::orderBy('name')->get(),
        ]);
    }

    public function store(InstructorStoreRequest $request)
    {
        $data = $request->validated();

        $user = User::create([
            'email' => $data['email'],
            'name' => $data['name'],
            'role' => 'instructor',
            'password' => Hash::make('chcc@2025'),
        ]);

        Professor::create([
            'user_id' => $user->id,
            'department_id' => $data['department_id'],
        ]);

        $this->logAction($request->user(), "Created instructor {$user->name}");

        return redirect()->route('admin.instructors.index')->with('success', 'Instructor created.');
    }

    public function edit(User $instructor)
    {
        if ($instructor->role !== 'instructor') {
            abort(404);
        }

        $instructor->load(['professor']);

        return Inertia::render('Admin/Instructors/Edit', [
            'instructor' => $instructor,
            'departments' => Department::orderBy('name')->get(),
        ]);
    }

    public function update(InstructorUpdateRequest $request, User $instructor)
    {
        if ($instructor->role !== 'instructor') {
            abort(404);
        }

        $data = $request->validated();

        $instructor->email = $data['email'];
        $instructor->name = $data['name'];

        $instructor->save();

        Professor::updateOrCreate(
        ['user_id' => $instructor->id],
        ['department_id' => $data['department_id']]
        );

        $this->logAction($request->user(), "Updated instructor {$instructor->name}");

        return redirect()->route('admin.instructors.index')->with('success', 'Instructor updated.');
    }

    public function resetPassword(Request $request, User $instructor)
    {
        if ($instructor->role !== 'instructor') {
            abort(404);
        }

        $instructor->password = Hash::make('chcc@2025');
        $instructor->save();

        $this->logAction($request->user(), "Reset password for instructor {$instructor->name}");

        return back()->with('success', 'Password reset successfully.');
    }

    public function destroy(Request $request, User $instructor)
    {
        if ($instructor->role !== 'instructor') {
            abort(404);
        }

        $name = $instructor->name;
        $instructor->delete();

        $this->logAction($request->user(), "Deleted instructor {$name}");

        return redirect()->route('admin.instructors.index')->with('success', 'Instructor deleted.');
    }

    public function import(InstructorImportRequest $request)
    {
        set_time_limit(0);
        ini_set('memory_limit', '256M');

        $file = $request->file('file');
        $fallbackDepartmentId = $request->input('department_id');
        $imported = 0;
        $errors = [];
        $skipped = 0;
        $rowNumber = 1;

        // Hardcoded mapping for department mismatches in the Excel file
        $facultyMapping = [
            'LIBERAL ARTS' => 'STE', // Map LIBERAL ARTS to STE based on context, adjust if needed
        ];

        // Fetch lookups
        $departmentsByCode = Department::all()->keyBy(fn($d) => strtoupper(trim($d->code)));
        $departmentsByName = Department::all()->keyBy(fn($d) => strtoupper(trim($d->name)));

        try {
            $allRows = (new FastExcel)->import($file);

            if ($allRows->isEmpty()) {
                return back()->with('flash', [
                    'type' => 'error',
                    'message' => 'The Excel file is empty. Please upload a file with data.',
                ]);
            }

            $firstRow = $allRows->first();
            $headers = array_keys($firstRow);
            $hasEmail = false;
            $hasName = false;
            $emailKey = null;
            $nameKey = null;
            $deptKey = null;

            foreach ($headers as $header) {
                $normalizedHeader = strtolower(trim($header));
                if (in_array($normalizedHeader, ['email', 'email address', 'username'], true)) {
                    $hasEmail = true;
                    $emailKey = $header;
                }
                if (in_array($normalizedHeader, ['name', 'full name', 'fullname'], true)) {
                    $hasName = true;
                    $nameKey = $header;
                }
                if (in_array($normalizedHeader, ['department', 'dept', 'faculty dept', 'faculty'], true)) {
                    $deptKey = $header;
                }
            }

            if (!$hasEmail || !$hasName) {
                $foundHeaders = implode(', ', array_map(fn($h) => '"' . $h . '"', $headers));
                return back()->with('flash', [
                    'type' => 'error',
                    'message' => "Invalid Excel format. Required columns: email/faculty and name/full name. Recommended column: department/faculty dept. Found columns: {$foundHeaders}. Please download the template and follow the correct format.",
                ]);
            }

            // Hash password once (reused for all rows)
            $hashedPassword = Hash::make('chcc@2025');

            // Pre-load existing emails (1 query instead of N)
            $existingEmails = User::pluck('email')->map(fn($v) => strtolower((string)$v))->flip()->toArray();

            DB::beginTransaction();

            $chunkSize = 50;
            $userBatch = [];
            $now = now();

            foreach ($allRows as $line) {
                $rowNumber++;
                $lineArr = (array)$line;
                $email = isset($lineArr[$emailKey]) ? strtolower(trim((string)$lineArr[$emailKey])) : null;
                $name = isset($lineArr[$nameKey]) ? trim((string)$lineArr[$nameKey]) : null;
                $deptStr = $deptKey !== null && isset($lineArr[$deptKey]) ? trim((string)$lineArr[$deptKey]) : null;

                if (empty($email) && empty($name)) {
                    continue;
                }

                if (empty($email)) {
                    $errors[] = "Row {$rowNumber}: Email is required";
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

                // Resolve Department
                $resolvedDeptId = $fallbackDepartmentId;
                if (!empty($deptStr)) {
                    $deptUpper = strtoupper($deptStr);
                    $mappedDept = $facultyMapping[$deptUpper] ?? $deptUpper;

                    if ($departmentsByCode->has($mappedDept)) {
                        $resolvedDeptId = $departmentsByCode->get($mappedDept)->id;
                    }
                    elseif ($departmentsByName->has($mappedDept)) {
                        $resolvedDeptId = $departmentsByName->get($mappedDept)->id;
                    }
                }

                if (!$resolvedDeptId) {
                    $errors[] = "Row {$rowNumber}: Could not determine department for '{$name}' - Provide a valid Department in file or select a fallback in the UI.";
                    $skipped++;
                    continue;
                }

                $existingEmails[$email] = true;

                $userBatch[] = [
                    'email' => $email,
                    'name' => $name,
                    'role' => 'instructor',
                    'password' => $hashedPassword,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                // Track department for this user index so we can insert the professor record correctly
                $departmentBatchMap[] = $resolvedDeptId;

                if (count($userBatch) >= $chunkSize) {
                    User::insert($userBatch);
                    $firstId = DB::connection()->getPdo()->lastInsertId();

                    $professorBatch = [];
                    foreach ($userBatch as $index => $u) {
                        $uid = (int)$firstId + $index;
                        $professorBatch[] = [
                            'user_id' => $uid,
                            'department_id' => $departmentBatchMap[$index],
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }

                    Professor::insert($professorBatch);
                    $userBatch = [];
                    $departmentBatchMap = [];
                }
            }

            if (!empty($userBatch)) {
                User::insert($userBatch);
                $firstId = DB::connection()->getPdo()->lastInsertId();

                $professorBatch = [];
                foreach ($userBatch as $index => $u) {
                    $uid = (int)$firstId + $index;
                    $professorBatch[] = [
                        'user_id' => $uid,
                        'department_id' => $departmentBatchMap[$index],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                Professor::insert($professorBatch);
                $imported += count($userBatch);
            }

            DB::commit();

            $this->logAction(
                $request->user(),
                "Imported {$imported} instructors" . ($skipped > 0 ? " ({$skipped} skipped)" : "")
            );

            if ($imported === 0) {
                return back()->with('flash', [
                    'type' => 'error',
                    'message' => 'No instructors were imported. Please check the errors below.',
                    'errors' => $errors,
                ]);
            }

            // If some instructors were imported, always show success
            $message = "Successfully imported {$imported} instructor(s)";
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
                'Email Address' => 'juandelacruz@chcc.edu.ph',
                'name' => 'Prof. Juan Dela Cruz',
                'Department' => 'STE',
            ],
            [
                'Email Address' => 'mariasantos@chcc.edu.ph',
                'name' => 'Prof. Maria Santos',
                'Department' => 'SCS',
            ],
            [
                'Email Address' => 'joserizal@chcc.edu.ph',
                'name' => 'Prof. Jose Rizal',
                'Department' => 'SHM',
            ],
        ];

        return (new FastExcel(collect($data)))
            ->download('instructor_import_template.xlsx');
    }

    private function logAction(User $actor, string $description): void
    {
        Log::create([
            'user_id' => $actor->id,
            'description' => $description,
            'role' => $actor->role,
        ]);
    }
}