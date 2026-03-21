<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SectionImportRequest;
use App\Http\Requests\Admin\SectionRequest;
use App\Models\Department;
use App\Models\Log;
use App\Models\Section;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Rap2hpoutre\FastExcel\FastExcel;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SectionController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->string('search')->toString();
        $departmentId = $request->input('department_id');
        $perPage = min(max((int)$request->input('per_page', 10), 1), 100);

        $sections = Section::with('department')
            ->when($search, function ($query, $term) {
            $query->where('name', 'like', "%{$term}%");
        })
            ->when($departmentId, function ($query, $id) {
            $query->where('department_id', $id);
        })
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('Admin/Sections/Index', [
            'sections' => $sections,
            'departments' => Department::orderBy('name')->get(),
            'filters' => [
                'search' => $search,
                'department_id' => $departmentId,
                'per_page' => $perPage,
            ],
        ]);
    }

    public function store(SectionRequest $request)
    {
        $section = Section::create($request->validated());
        $this->logAction($request->user(), "Created section {$section->name}");

        return redirect()->back()->with('success', 'Section created.');
    }

    public function update(SectionRequest $request, Section $section)
    {
        $section->update($request->validated());
        $this->logAction($request->user(), "Updated section {$section->name}");

        return redirect()->back()->with('success', 'Section updated.');
    }

    public function destroy(Request $request, Section $section)
    {
        $name = $section->name;
        $section->delete();
        $this->logAction($request->user(), "Deleted section {$name}");

        return redirect()->back()->with('success', 'Section deleted.');
    }

    public function import(SectionImportRequest $request)
    {
        $file = $request->file('file');

        $imported = 0;
        $errors = [];
        $skipped = 0;
        $rowNumber = 1;

        // Hardcoded mapping for Format 2 (program code in section name -> department code)
        $programMapping = [
            'BEED' => 'EDUC',
            'BSEDENG' => 'EDUC',
            'BSEDFIL' => 'EDUC',
            'BSEDMATH' => 'EDUC',
            'BSEDSOCSCI' => 'EDUC',
            'BSEDE' => 'EDUC',
            'BSHM' => 'BSHM',
            'BSA' => 'BSBA',
            'BSBAFM' => 'BSBA',
            'BSCRIM' => 'BSCRIM',
            'BSISM' => 'BSCRIM',
            'BSCS' => 'BSCS',
            'BSN' => 'BSN',       
        ];

        // Fetch all departments into a collection keyed by uppercase code
        $departmentsByCode = Department::all()->keyBy(fn($d) => strtoupper(trim($d->code)));

        try {
            $allRows = (new FastExcel)->import($file);
            if ($allRows->isEmpty()) {
                return back()->with('flash', [
                    'type' => 'error',
                    'message' => 'The file is empty. Please upload a file with data.',
                ]);
            }

            $firstRow = $allRows->first();
            $headers = array_keys($firstRow);

            // Determine format
            $nameKey = null;
            foreach ($headers as $header) {
                $normalized = strtolower(trim($header));
                if (in_array($normalized, ['name', 'section', 'section name', 'section_name'])) {
                    $nameKey = $header;
                    break;
                }
            }

            $isFormat1 = !$nameKey; // Multi-column

            DB::beginTransaction();

            $stats = []; // To track per-department imports

            if ($isFormat1) {
                // FORMAT 1: Columns are department codes
                // Validate headers
                $validColumns = [];
                foreach ($headers as $header) {
                    $code = strtoupper(trim($header));
                    if ($departmentsByCode->has($code)) {
                        $validColumns[$header] = $departmentsByCode->get($code)->id;
                        $stats[$code] = 0;
                    }
                    else if (!empty($code)) {
                        $errors[] = "Column '{$header}' skipped — no matching department found.";
                    }
                }

                if (empty($validColumns)) {
                    return back()->with('flash', [
                        'type' => 'error',
                        'message' => "Invalid format. No matching department codes found in the column headers.",
                    ]);
                }

                foreach ($allRows as $line) {
                    $rowNumber++;
                    foreach ($validColumns as $header => $deptId) {
                        $name = isset($line[$header]) ? trim((string)$line[$header]) : null;
                        if (empty($name))
                            continue;

                        if (Section::where('department_id', $deptId)->where('name', $name)->exists()) {
                            $errors[] = "Row {$rowNumber}, {$header}: Section '{$name}' already exists";
                            $skipped++;
                            continue;
                        }

                        Section::create(['name' => $name, 'department_id' => $deptId]);
                        $this->logAction($request->user(), "Imported section {$name} (Format 1)");
                        $stats[strtoupper(trim($header))]++;
                        $imported++;
                    }
                }
            }
            else {
                // FORMAT 2: Single column 'name' or 'section'
                foreach ($allRows as $line) {
                    $rowNumber++;
                    $name = isset($line[$nameKey]) ? trim((string)$line[$nameKey]) : null;
                    if (empty($name))
                        continue;

                    // Extract program code: usually looks like "1 BSCS-A" -> "BSCS"
                    // Match letters after the first number + space
                    $deptId = null;
                    $deptCode = null;
                    if (preg_match('/^\d+\s*([A-Za-z]+)/', $name, $matches)) {
                        $program = strtoupper(trim($matches[1]));
                        // Look up mapped department code
                        $mappedCode = $programMapping[$program] ?? null;

                        if ($mappedCode && $departmentsByCode->has($mappedCode)) {
                            $deptId = $departmentsByCode->get($mappedCode)->id;
                            $deptCode = $mappedCode;
                        }
                    }

                    if (!$deptId) {
                        $errors[] = "Row {$rowNumber}: Cannot determine department for '{$name}'. Is the program mapped properly?";
                        $skipped++;
                        continue;
                    }

                    if (Section::where('department_id', $deptId)->where('name', $name)->exists()) {
                        $errors[] = "Row {$rowNumber}: Section '{$name}' already exists";
                        $skipped++;
                        continue;
                    }

                    Section::create(['name' => $name, 'department_id' => $deptId]);
                    $this->logAction($request->user(), "Imported section {$name} (Format 2)");

                    if (!isset($stats[$deptCode]))
                        $stats[$deptCode] = 0;
                    $stats[$deptCode]++;
                    $imported++;
                }
            }

            DB::commit();

            $statsMsg = [];
            foreach ($stats as $dept => $count) {
                if ($count > 0)
                    $statsMsg[] = "{$count} for {$dept}";
            }
            $statsString = !empty($statsMsg) ? " (" . implode(', ', $statsMsg) . ")" : "";

            $message = $imported > 0
                ? "Successfully imported {$imported} section(s){$statsString}." . ($skipped > 0 ? " {$skipped} skipped." : '')
                : "No sections imported." . ($skipped > 0 ? " {$skipped} row(s) skipped." : ' File may be empty or invalid.');

            return back()->with('flash', [
                'type' => $imported > 0 ? 'success' : 'warning',
                'message' => $message,
                'errors' => $errors,
            ]);
        }
        catch (\Exception $e) {
            DB::rollBack();
            return back()->with('flash', [
                'type' => 'error',
                'message' => 'Import failed: ' . $e->getMessage(),
                'errors' => $errors,
            ]);
        }
    }

    public function downloadTemplate(): StreamedResponse
    {
        $data = collect([
            [
                'EDUC' => '1 BEED-A',
                'BSHM' => '1 BSHM-A',
                'BSBAA' => '1 BSA-A',
                'BSCRIM' => '1 BSCRIM-A',
                'BSCS' => '1 BSCS-A',
                'BSN' => '1 BSN A'
            ],
            [
                'EDUC' => '1 BSEdEng-A',
                'BSHM' => '2 BSHM-A',
                'BSBAA' => '2 BSBAFM-A',
                'BSCRIM' => '2 BSISM-A',
                'BSCS' => '2 BSCS-A',
                'BSN' => '2 BSN B'
            ],
            [
                'EDUC' => '',
                'BSHM' => '',
                'BSBAA' => '',
                'BSCRIM' => '',
                'BSCS' => '2 BSCS-B',
                'BSN' => ''
            ],
        ]);

        return (new FastExcel($data))->download('sections_template.xlsx');
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