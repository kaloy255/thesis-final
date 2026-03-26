<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\Notification;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();
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

        $recentAttempts = AssessmentAttempt::query()
            ->where('student_id', $student->id)
            ->with(['assessment.lesson.subject'])
            ->latest()
            ->limit(5)
            ->get()
            ->map(function (AssessmentAttempt $attempt) {
                $assessment = $attempt->assessment;
                $subjectCode = $assessment?->lesson?->subject?->code;
                $subjectName = $assessment?->lesson?->subject?->name;
                $subjectLabel = $subjectCode ? "{$subjectCode}" : ($subjectName ?? null);

                return [
                    'id' => "attempt-{$attempt->id}",
                    'type' => 'assessment_attempt',
                    'title' => $assessment?->title ?? 'Assessment attempt',
                    'description' => $subjectLabel
                        ? "Attempt #{$attempt->attempt_no} • {$subjectLabel}"
                        : "Attempt #{$attempt->attempt_no}",
                    'occurred_at' => $attempt->created_at,
                    'url' => $assessment ? route('student.assessments.history', $assessment->id) : null,
                ];
            });

        $recentNotifications = Notification::query()
            ->where('user_id', $user->id)
            ->latest()
            ->limit(5)
            ->get()
            ->map(function (Notification $notification) {
                return [
                    'id' => "notification-{$notification->id}",
                    'type' => 'notification',
                    'title' => 'Update',
                    'description' => $notification->description,
                    'occurred_at' => $notification->created_at,
                    'url' => route('student.subjects.index'),
                    'read_at' => $notification->read_at,
                ];
            });

        $recentActivities = $recentAttempts
            ->concat($recentNotifications)
            ->sortByDesc('occurred_at')
            ->values()
            ->take(8)
            ->values();

        return Inertia::render('Student/Dashboard', [
            'stats' => [
                'assessments_count' => $assessmentsCount,
                'joined_subjects_count' => $joinedSubjectsCount,
            ],
            'recentActivities' => $recentActivities,
        ]);
    }
}

