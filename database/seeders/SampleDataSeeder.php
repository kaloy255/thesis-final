<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Professor;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SampleDataSeeder extends Seeder
{
    public function run(): void
    {
        $departments = [
            'Computer Science',
            'Information Technology',
            'Information Systems',
        ];

        $departmentIds = [];
        foreach ($departments as $name) {
            $departmentIds[] = Department::firstOrCreate(['name' => $name])->id;
        }

        $sections = [
            ['department_id' => $departmentIds[0] ?? null, 'name' => 'BSCS-4A'],
            ['department_id' => $departmentIds[0] ?? null, 'name' => 'BSCS-4B'],
            ['department_id' => $departmentIds[1] ?? null, 'name' => 'BSIT-3A'],
            ['department_id' => $departmentIds[2] ?? null, 'name' => 'BSIS-2A'],
        ];

        foreach ($sections as $section) {
            if ($section['department_id']) {
                Section::firstOrCreate(
                    [
                        'department_id' => $section['department_id'],
                        'name' => $section['name'],
                    ]
                );
            }
        }

        $subjects = [
            ['name' => 'Data Structures', 'code' => 'CS201', 'description' => 'Fundamentals of data structures'],
            ['name' => 'Algorithms', 'code' => 'CS301', 'description' => 'Algorithm design and analysis'],
            ['name' => 'Operating Systems', 'code' => 'CS310', 'description' => 'Processes, threads, and memory management'],
            ['name' => 'Database Systems', 'code' => 'IT210', 'description' => 'Relational databases and SQL'],
        ];

        foreach ($subjects as $subject) {
            Subject::firstOrCreate(
                ['code' => $subject['code']],
                [
                    'name' => $subject['name'],
                    'description' => $subject['description'],
                ]
            );
        }

        // Instructors
        $instructors = [
            ['email' => '2001@chcc.edu.ph', 'name' => 'Alice Instructor', 'department_index' => 0],
            ['email' => '2002@chcc.edu.ph', 'name' => 'Bob Instructor', 'department_index' => 1],
        ];

        foreach ($instructors as $instructor) {
            $user = User::firstOrCreate(
                ['email' => $instructor['email']],
                [
                    'name' => $instructor['name'],
                    'role' => 'instructor',
                    'password' => Hash::make('chcc@2025'),
                ]
            );

            $departmentId = $departmentIds[$instructor['department_index']] ?? null;
            if ($departmentId) {
                Professor::firstOrCreate(
                    ['user_id' => $user->id],
                    ['department_id' => $departmentId]
                );
            }
        }

        // Students
        $studentEntries = [
            ['email' => '3001@chcc.edu.ph', 'name' => 'Charlie Student', 'section_name' => 'BSCS-4A'],
            ['email' => '3002@chcc.edu.ph', 'name' => 'Dana Student', 'section_name' => 'BSCS-4B'],
            ['email' => '3003@chcc.edu.ph', 'name' => 'Evan Student', 'section_name' => 'BSIT-3A'],
        ];

        foreach ($studentEntries as $entry) {
            $user = User::firstOrCreate(
                ['email' => $entry['email']],
                [
                    'name' => $entry['name'],
                    'role' => 'student',
                    'password' => Hash::make('chcc@2025'),
                ]
            );

            $section = Section::where('name', $entry['section_name'])->first();
            if ($section) {
                Student::firstOrCreate(
                    ['user_id' => $user->id],
                    ['section_id' => $section->id]
                );
            }
        }
    }
}

