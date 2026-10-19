<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Enrollment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'program_id',
        'year_level',
        'semester',
        'school_year',
        'is_irregular',
        'subject_ids',
        'status',
        'remarks',
        'is_paid',
    ];

    protected $casts = [
        'subject_ids' => 'array',
        'is_paid' => 'boolean',
        'is_irregular' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function program()
    {
        return $this->belongsTo(Program::class);
    }

    public function creditedSubjects()
    {
        return $this->hasMany(CreditedSubject::class);
    }

    /**
     * Resolve the student's admission classification for display
     * (regular / transferee / shiftee / returnee / cross_enrollee).
     *
     * Prefers student_profiles.admission_type (set once the student is
     * promoted from new_applicant). Falls back to the linked approved
     * application's academic_status for students who haven't paid yet
     * (student_profiles doesn't exist until payment confirmation).
     */
    public function resolveClassification(): string
    {
        $profile = $this->user?->studentProfile;
        if ($profile) {
            return $profile->admission_type;
        }

        $application = \App\Models\Application::where('user_id', $this->user_id)
            ->where('status', 'approved')
            ->latest()
            ->first();

        return $application->academic_status ?? 'new_student';
    }

    public function payment()
    {
        // Always resolve the latest payment attempt.
        // This handles walk-in payments, GCash submissions,
        // rejected payments, and subsequent resubmissions.
        return $this->hasOne(Payment::class)->latestOfMany();
    }
}