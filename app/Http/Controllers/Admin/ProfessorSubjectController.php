<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProfessorSubjectRequest;
use App\Models\Log;
use App\Models\Professor;
use App\Models\ProfessorSubject;
use App\Models\Subject;
use App\Models\User;
use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Rap2hpoutre\FastExcel\FastExcel;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Http\Requests\Admin\AssignmentImportRequest;

class ProfessorSubjectController extends Controller
{
    public function index()
    {
        $subjects = Subject::with(['professorSubjects.professor.user', 'professorSubjects.professor.department'])
            ->orderBy('name')
            ->get()
            ->map(function ($subject) {
                return [
                    'id' => $subject->id,
                    'code' => $subject->code,
                    'name' => $subject->name,
                    'assignments' => $subject->professorSubjects->map(function ($ps) {
                        return [
                            'id' => $ps->id,
                            'professor_id' => $ps->professor_id,
                            'professor_name' => $ps->professor->user->name ?? 'Unknown',
                            'department_name' => $ps->professor->department->name ?? 'No Department',
                            'created_at' => $ps->created_at?->toISOString(),
                        ];
                    }),
                ];
            });

        return Inertia::render('Admin/ProfessorSubjects/Index', [
            'subjects' => $subjects,
            'professors' => Professor::with(['user', 'department'])->orderBy('id')->get(),
        ]);
    }

    public function store(ProfessorSubjectRequest $request)
    {
        $data = $request->validated();

        $assignment = ProfessorSubject::firstOrCreate(
            [
                'professor_id' => $data['professor_id'],
                'subject_id' => $data['subject_id'],
            ]
        );

        $this->logAction($request->user(), "Assigned professor {$assignment->professor_id} to subject {$assignment->subject_id}");

        return redirect()->back()->with('success', 'Assignment saved.');
    }

    public function update(ProfessorSubjectRequest $request, ProfessorSubject $assignment)
    {
        $data = $request->validated();

        $assignment->update([
            'professor_id' => $data['professor_id'],
            'subject_id' => $data['subject_id'],
        ]);

        $this->logAction($request->user(), "Updated assignment: Instructor {$assignment->professor_id} / subject {$assignment->subject_id}");

        return redirect()->back()->with('success', 'Assignment updated successfully.');
    }

    public function destroy(Request $request, ProfessorSubject $assignment)
    {
        $info = "Instructor {$assignment->professor_id} / subject {$assignment->subject_id}";
        $assignment->delete();
        $this->logAction($request->user(), "Removed assignment {$info}");

        return redirect()->back()->with('success', 'Assignment removed.');
    }

    public function import(AssignmentImportRequest $request)
    {
        $file = $request->file('file');

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
        $subjectsByCode = Subject::all()->keyBy(fn($s) => strtoupper(trim($s->code)));
        
        // Fetch all professors with their user relationship
        $professors = Professor::with('user')->get();

        try {
            $allRows = (new FastExcel)->import($file);
            if ($allRows->isEmpty()) {
                return back()->with('flash', [
                    'type' => 'error',
                    'message' => 'The file is empty. Please upload a file with data.',
                ]);
            }

            $firstRow = $allRows->first();
            $headers = is_array($firstRow) ? array_keys($firstRow) : array_keys((array) $firstRow);

            // Expected columns
            $subjectKey = null;
            $emailKey = null;

            foreach ($headers as $header) {
                $normalized = strtolower(trim($header));
                if (in_array($normalized, ['subject code', 'subject_code', 'subject', 'code'])) {
                    $subjectKey = $header;
                } elseif (in_array($normalized, ['email', 'email address', 'username'])) {
                    $emailKey = $header;
                }
            }

            if (!$subjectKey || !$emailKey) {
                return back()->with('flash', [
                    'type' => 'error',
                    'message' => "Invalid format. The Excel file must contain a 'Subject Code' column and an Instructor 'Email' column.",
                ]);
            }

            DB::beginTransaction();

            foreach ($allRows as $line) {
                $rowNumber++;
                
                $lineArr = (array) $line;
                $subjectCodeStr = isset($lineArr[$subjectKey]) ? trim((string)$lineArr[$subjectKey]) : null;
                $emailStr = isset($lineArr[$emailKey]) ? strtolower(trim((string)$lineArr[$emailKey])) : null;

                if (empty($subjectCodeStr) || empty($emailStr)) {
                    $skipped++;
                    continue;
                }

                // 1. Find the Subject
                $subjectCodeUpper = strtoupper($subjectCodeStr);
                if (!$subjectsByCode->has($subjectCodeUpper)) {
                    $errors[] = "Row {$rowNumber}: Subject '{$subjectCodeStr}' not found.";
                    $skipped++;
                    continue;
                }
                $subject = $subjectsByCode->get($subjectCodeUpper);

                // 2. Find the Professor by Email
                $professor = null;
                
                foreach ($professors as $p) {
                    if ($p->user && strtolower(trim($p->user->email)) === $emailStr) {
                        $professor = $p;
                        break;
                    }
                }

                if (!$professor) {
                    $errors[] = "Row {$rowNumber}: Instructor with email '{$emailStr}' not found in the system.";
                    $skipped++;
                    continue;
                }

                // 4. Assign the Professor to the Subject
                $assignment = ProfessorSubject::firstOrCreate([
                    'professor_id' => $professor->id,
                    'subject_id' => $subject->id,
                ]);

                if ($assignment->wasRecentlyCreated) {
                    $this->logAction($request->user(), "Imported assignment: professor {$professor->id} to subject {$subject->id} (Row {$rowNumber})");
                    $imported++;
                } else {
                    // Already assigned, just skip without error
                    $skipped++;
                }
            }

            DB::commit();

            $message = $imported > 0
                ? "Successfully imported {$imported} assignment(s)." . ($skipped > 0 ? " {$skipped} skipped (empty, missing, or already assigned)." : '')
                : "No assignments imported." . ($skipped > 0 ? " All {$skipped} row(s) skipped." : ' File may be empty or invalid.');

            return back()->with('flash', [
                'type' => $imported > 0 ? 'success' : 'warning',
                'message' => $message,
                'errors' => empty($errors) ? null : $errors,
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('flash', [
                'type' => 'error',
                'message' => 'Import failed: ' . $e->getMessage(),
            ]);
        }
    }

    public function downloadTemplate(): StreamedResponse
    {
        $data = collect([
            [
                'Subject Code' => 'CSC103',
                'email' => 'email@chcc.edu.ph',
            ],
            [
                'Subject Code' => 'HPC2',
                'email' => 'email@chcc.edu.ph',
            ],
            [
                'Subject Code' => 'EMC117',
                'email' => 'email@chcc.edu.ph',
            ],
        ]);

        return (new FastExcel($data))->download('assignments_template.xlsx');
    }

    private function logAction($actor, string $description): void
    {
        Log::create([
            'user_id' => $actor->id,
            'description' => $description,
            'role' => $actor->role,
        ]);
    }
}