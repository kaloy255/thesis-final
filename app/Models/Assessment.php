<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Assessment extends Model
{
    use HasFactory;

    protected $fillable = [
        'lesson_id',
        'title',
        'type',
        'parent_assessment_id',
        'source_attempt_id',
        'status',
        'time_limit_minutes',
    ];

    protected $casts = [
        'status' => 'string',
        'time_limit_minutes' => 'integer',
    ];

    public function lesson()
    {
        return $this->belongsTo(Lesson::class);
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_assessment_id');
    }

    public function sourceAttempt()
    {
        return $this->belongsTo(AssessmentAttempt::class, 'source_attempt_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_assessment_id');
    }

    /**
     * Top-level assessment in an adaptive chain (no parent), or self if already root.
     */
    public function rootAssessment(): self
    {
        $current = $this;
        $guard = 0;

        while ($current->parent_assessment_id !== null && $guard < 64) {
            $parent = $current->parent;
            if (! $parent) {
                break;
            }
            $current = $parent;
            $guard++;
        }

        return $current;
    }

    public function sections()
    {
        return $this->belongsToMany(Section::class, 'assessment_section');
    }

    public function items()
    {
        return $this->hasMany(AssessmentItem::class);
    }

    public function attempts()
    {
        return $this->hasMany(AssessmentAttempt::class);
    }

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    /**
     * Check if a student can access this assessment.
     */
    public function canBeAccessedBy(Student $student): bool
    {
        // Must be published
        if ($this->status !== 'published') {
            return false;
        }

        // Check if assigned to student's section
        $isAssignedToSection = $this->sections()
            ->where('section_id', $student->section_id)
            ->exists();

        if ($isAssignedToSection) {
            return true;
        }

        // Check if student is enrolled in the subject (status = 'approved')
        $isEnrolledInSubject = $this->lesson->subject->students()
            ->where('student_id', $student->id)
            ->wherePivot('status', 'approved')
            ->exists();

        return $isEnrolledInSubject;
    }

    /**
     * Scope to get assessments accessible by a student.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeAccessibleBy($query, Student $student)
    {
        return $query->where('status', 'published')
            ->where(function ($q) use ($student) {
                // Assigned to student's section
                $q->whereHas('sections', function ($sectionQuery) use ($student) {
                    $sectionQuery->where('section_id', $student->section_id);
                })
                    // OR enrolled in subject
                    ->orWhereHas('lesson', function ($lessonQuery) use ($student) {
                        $lessonQuery->whereHas('subject', function ($subjectQuery) use ($student) {
                            $subjectQuery->whereHas('students', function ($studentQuery) use ($student) {
                                $studentQuery->where('student_id', $student->id)
                                    ->where('student_subject.status', 'approved');
                            });
                        });
                    });
            });
    }
}
