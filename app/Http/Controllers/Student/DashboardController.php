<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $student = auth()->user()->student;

        if (! $student) {
            abort(403, 'Student record not found');
        }

        $assessmentsCount = Assessment::accessibleBy($student)
            ->where('type', '!=', 'adaptive')
            ->count();

        $joinedSubjectsCount = $student->subjects()
            ->wherePivot('status', 'approved')
            ->count();

        return Inertia::render('Student/Dashboard', [
            'stats' => [
                'assessments_count' => $assessmentsCount,
                'joined_subjects_count' => $joinedSubjectsCount,
            ],
        ]);
    }
}

