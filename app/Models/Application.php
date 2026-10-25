<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Support\NameNormalizer;

class Application extends Model
{
    protected $fillable = [
        'reference_no',
        'confirmation_token_hash',
        'confirmation_token_expires_at',
        'submission_token_hash',
        'program_id',
        'first_name',
        'last_name',
        'middle_name',
        'honorific',
        'suffix',
        'email',
        'phone',
        'address',
        'birthdate',
        'guardian_name',
        'guardian_contact',
        'status',
        'reviewed_by',
        'reviewed_at',
        'remarks',
        'user_id',
        'academic_status',
        'gender',
        'birth_place',
        'religion',
        'nationality',
        'lrn',
        'marital_status',
        'current_address',
        'city',
        'province',
        'region',
        'region_code',
        'barangay',
        'province_code',
        'city_code',
        'postal_code',
        'country',
        'father_name',
        'father_occupation',
        'mother_name',
        'mother_occupation',
        'parent_address',
        'parent_contact',
        'spouse_name',
        'occupation',
        'disability',
        'pwd_id',
        'education_level',
        'education_status',
        'most_recent_school',
        'school_year_completed',
        'school_location',
        'shs_strand',
        'previous_program',
        'last_year_level',
        'home_institution',
        'home_program',
        'acknowledged_at',
    ];

    // Normalize names on both reads and writes. Existing rows are not rewritten;
    // their displayed values are normalized through these accessors.
    public function getFirstNameAttribute($value): ?string
    {
        return NameNormalizer::normalize($value);
    }

    public function setFirstNameAttribute($value): void
    {
        $this->attributes['first_name'] = NameNormalizer::normalize($value);
    }

    public function getMiddleNameAttribute($value): ?string
    {
        return NameNormalizer::normalize($value);
    }

    public function setMiddleNameAttribute($value): void
    {
        $this->attributes['middle_name'] = NameNormalizer::normalize($value);
    }

    public function getLastNameAttribute($value): ?string
    {
        return NameNormalizer::normalize($value);
    }

    public function setLastNameAttribute($value): void
    {
        $this->attributes['last_name'] = NameNormalizer::normalize($value);
    }

    public function getFatherNameAttribute($value): ?string
    {
        return NameNormalizer::normalize($value);
    }

    public function setFatherNameAttribute($value): void
    {
        $this->attributes['father_name'] = NameNormalizer::normalize($value);
    }

    public function getMotherNameAttribute($value): ?string
    {
        return NameNormalizer::normalize($value);
    }

    public function setMotherNameAttribute($value): void
    {
        $this->attributes['mother_name'] = NameNormalizer::normalize($value);
    }

    public function getSpouseNameAttribute($value): ?string
    {
        return NameNormalizer::normalize($value);
    }

    public function setSpouseNameAttribute($value): void
    {
        $this->attributes['spouse_name'] = NameNormalizer::normalize($value);
    }

    public function getFullNameAttribute(): string
    {
        return implode(' ', array_values(array_filter([
            $this->honorific,
            $this->first_name,
            $this->middle_name,
            $this->last_name,
            $this->suffix,
        ], function ($part) {
            return is_string($part) && trim($part) !== '';
        })));
    }

    public function getHonorificAttribute($value): ?string
    {
        return NameNormalizer::normalizeAffix($value, 'honorific');
    }

    public function setHonorificAttribute($value): void
    {
        $this->attributes['honorific'] = NameNormalizer::normalizeAffix($value, 'honorific');
    }

    public function getSuffixAttribute($value): ?string
    {
        return NameNormalizer::normalizeAffix($value, 'suffix');
    }

    public function setSuffixAttribute($value): void
    {
        $this->attributes['suffix'] = NameNormalizer::normalizeAffix($value, 'suffix');
    }

    protected $casts = [
        'birthdate'   => 'date',
        'reviewed_at' => 'datetime',
        'confirmation_token_expires_at' => 'datetime',
        'acknowledged_at' => 'datetime',
    ];

    /**
     * Applicant's current age, calculated from birthdate. Not stored in the database.
     */
    public function getAgeAttribute(): ?int
    {
        return $this->birthdate?->age;
    }

    /**
     * The program the applicant applied for.
     */
    public function program()
    {
        return $this->belongsTo(Program::class);
    }

    /**
     * The account created for this applicant once approved (nullable until then).
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * The Registrar/Admin user who reviewed this application (nullable until reviewed).
     */
    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Uploaded supporting documents for this application.
     */
    public function documents()
    {
        return $this->hasMany(ApplicationDocument::class);
    }

    public function cancellationRequests()
    {
        return $this->hasMany(ApplicationCancellationRequest::class);
    }

    public function pendingCancellationRequest()
    {
        return $this->hasOne(ApplicationCancellationRequest::class)->where('status', 'pending')->latestOfMany();
    }

    /**
     * Resolve the enrollment associated with this application for progress
     * display, preferring the verified first academic-year enrollment.
     */
    public function getEnrollmentForProgress(): ?Enrollment
    {
        $user = $this->user;
        if (!$user) {
            return null;
        }

        $enrollments = $user->enrollments->where('program_id', $this->program_id);
        $profile = $user->studentProfile;

        if ($profile && $profile->enrolled_ay) {
            $initialEnrollment = $enrollments->first(function ($enrollment) use ($profile) {
                return $enrollment->school_year === $profile->enrolled_ay
                    && $enrollment->status === 'approved'
                    && $enrollment->is_paid
                    && $enrollment->payment?->status === 'verified';
            });

            if ($initialEnrollment) {
                return $initialEnrollment;
            }
        }

        return $enrollments->sortByDesc('id')->first();
    }

    /**
     * Get the application progress timeline for student-facing display.
     * Maps existing statuses to a visual progress pipeline.
     */
    public function getProgressTimeline(?Enrollment $enrollment = null): array
    {
        $timeline = [
            ['key' => 'submitted', 'label' => 'Application submitted', 'description' => 'Your application was received.', 'status' => 'completed', 'timestamp' => $this->created_at],
            ['key' => 'review', 'label' => 'Registrar review', 'description' => 'The Registrar is reviewing your application.', 'status' => 'pending', 'timestamp' => null],
            ['key' => 'corrections', 'label' => 'Applicant corrections', 'description' => 'No corrections have been requested.', 'status' => 'skipped', 'timestamp' => null],
            ['key' => 'decision', 'label' => 'Application decision', 'description' => 'A decision is pending.', 'status' => 'pending', 'timestamp' => null],
            ['key' => 'enrollment', 'label' => 'Enrollment and payment', 'description' => 'This step becomes available after application approval.', 'status' => 'pending', 'timestamp' => null],
        ];

        switch ($this->status) {
            case 'submitted':
                $timeline[1]['status'] = 'current';
                break;

            case 'under_review':
                $timeline[1]['status'] = 'current';
                $timeline[1]['timestamp'] = $this->reviewed_at;
                break;

            case 'revision_required':
                $timeline[1]['status'] = 'completed';
                $timeline[1]['timestamp'] = $this->reviewed_at;
                $timeline[2] = [
                    'key' => 'corrections',
                    'label' => 'Applicant corrections',
                    'description' => 'Read the Registrar remarks and contact the Registrar to resolve the requested corrections.',
                    'status' => 'action_required',
                    'timestamp' => $this->reviewed_at,
                ];
                $timeline[3]['description'] = 'The application decision is pending the requested corrections and further Registrar review.';
                break;

            case 'approved':
                $timeline[1]['status'] = 'completed';
                $timeline[1]['timestamp'] = $this->reviewed_at;
                $timeline[3] = [
                    'key' => 'decision',
                    'label' => 'Application approved',
                    'description' => 'The Registrar approved your application. You are eligible to begin enrollment.',
                    'status' => 'completed',
                    'timestamp' => $this->reviewed_at,
                ];
                $timeline[4] = $this->enrollmentTimelineStep($enrollment);
                break;

            case 'rejected':
                $timeline[1]['status'] = 'completed';
                $timeline[1]['timestamp'] = $this->reviewed_at;
                $timeline[3] = [
                    'key' => 'decision',
                    'label' => 'Application not approved',
                    'description' => 'The application process ended at the Registrar decision.',
                    'status' => 'rejected',
                    'timestamp' => $this->reviewed_at,
                ];
                $timeline[4] = [
                    'key' => 'enrollment',
                    'label' => 'Enrollment and payment',
                    'description' => 'Enrollment is not available for this application.',
                    'status' => 'skipped',
                    'timestamp' => null,
                ];
                break;

            case 'cancelled':
                $timeline[1]['status'] = 'completed';
                $timeline[1]['timestamp'] = $this->reviewed_at;
                $timeline[3] = [
                    'key' => 'decision',
                    'label' => 'Application cancelled',
                    'description' => 'The Registrar approved a cancellation request. The application record remains on file.',
                    'status' => 'completed',
                    'timestamp' => $this->cancellationRequests()->where('status', 'approved')->latest('id')->first()?->reviewed_at,
                ];
                $timeline[4] = [
                    'key' => 'enrollment',
                    'label' => 'Enrollment and payment',
                    'description' => 'Enrollment is not available for a cancelled application.',
                    'status' => 'skipped',
                    'timestamp' => null,
                ];
                break;
        }

        return $timeline;
    }

    private function enrollmentTimelineStep(?Enrollment $enrollment): array
    {
        $step = [
            'key' => 'enrollment',
            'label' => 'Enrollment and payment',
            'description' => 'Activate your account, submit an enrollment request, and complete payment after Registrar approval.',
            'status' => 'current',
            'timestamp' => null,
        ];

        if (!$enrollment) {
            return $step;
        }

        if ($enrollment->status === 'rejected') {
            $step['description'] = 'Your enrollment request was not approved. Sign in to review the Registrar remarks and contact the Registrar for guidance.';
            $step['status'] = 'action_required';
            return $step;
        }

        if ($enrollment->status === 'pending') {
            $step['description'] = 'Your enrollment request is awaiting Registrar review.';
            return $step;
        }

        $payment = $enrollment->payment;
        if ($enrollment->status === 'approved' && $payment?->status === 'verified' && !$enrollment->is_paid) {
            $step['description'] = 'A verified payment record exists, but enrollment is not marked paid. Contact the Cashier to confirm the record before making another payment.';
            $step['status'] = 'action_required';
            return $step;
        }
        if ($enrollment->status === 'approved' && !$enrollment->is_paid) {
            if ($payment?->status === 'pending') {
                $step['description'] = 'Your payment proof is awaiting Cashier verification.';
            } elseif ($payment?->status === 'rejected') {
                $step['description'] = 'Your payment proof was rejected. Sign in to review the reason and use the supported payment resubmission flow.';
                $step['status'] = 'action_required';
            } else {
                $step['description'] = 'Your enrollment is approved. Pay the enrollment fee; completion is recorded after payment verification.';
            }
            return $step;
        }

        if ($this->isEnrollmentComplete($enrollment)) {
            $step['label'] = 'Enrollment and payment complete';
            $step['description'] = 'The Registrar approved your enrollment and the Cashier verified payment. Your student record was created.';
            $step['status'] = 'completed';
            $step['timestamp'] = $payment?->verified_at;
            return $step;
        }

        $step['description'] = 'Enrollment or payment is not yet fully confirmed. Sign in to the portal or contact the Registrar or Cashier for assistance.';
        return $step;
    }

    public function isEnrollmentComplete(?Enrollment $enrollment): bool
    {
        return $this->status === 'approved'
            && $enrollment !== null
            && $enrollment->status === 'approved'
            && $enrollment->is_paid
            && $enrollment->payment?->status === 'verified'
            && $enrollment->payment?->verified_at !== null
            && $this->user?->studentProfile !== null;
    }

    /**
     * Get the current status description for students.
     */
    public function getStatusDescription(): string
    {
        return match ($this->status) {
            'submitted' => 'Your application has been submitted and is awaiting initial review.',
            'under_review' => 'Your application is currently being reviewed by the Registrar. No action is needed from you at this time.',
            'revision_required' => 'Your application requires document corrections. Please review the remarks and take the necessary action.',
            'approved' => 'Your application has been approved. You are eligible to begin enrollment; approval alone does not mean enrollment or payment is complete.',
            'rejected' => 'Your application was not approved. Please review the remarks and contact the Registrar if you have questions.',
            'cancelled' => 'The Registrar approved your cancellation request. The application remains on file.',
            default => 'Application status unknown.',
        };
    }

    /**
     * Check if the application requires student action.
     */
    public function requiresAction(): bool
    {
        return $this->status === 'revision_required';
    }

    public function getStatusNextStep(?Enrollment $enrollment = null): string
    {
        return match ($this->status) {
            'submitted' => 'No action is needed while the Registrar reviews your application. Check this page for updates.',
            'under_review' => 'Your application is being reviewed. Monitor this status and follow any instructions from the Registrar.',
            'revision_required' => 'Read the Registrar remarks and contact the Registrar using your reference number. This status page cannot receive corrected documents.',
            'approved' => $this->approvedApplicationNextStep($enrollment),
            'rejected' => 'Review the Registrar remarks and contact the Registrar using your reference number if you need clarification. This page does not offer an appeal or resubmission process.',
            'cancelled' => 'This application is cancelled and cannot proceed to enrollment. Contact the Registrar if you need guidance about applying again.',
            default => 'Contact the Registrar and provide your application reference number for guidance.',
        };
    }

    private function approvedApplicationNextStep(?Enrollment $enrollment): string
    {
        if ($this->isEnrollmentComplete($enrollment)) {
            return 'Your enrollment and payment are complete. Sign in to the student portal to access your enrollment records.';
        }

        if (!$enrollment) {
            return 'Use the account activation email (or request another activation link), sign in, then submit your enrollment request. Application approval alone does not complete enrollment.';
        }

        if ($enrollment->status === 'pending') {
            return 'Your enrollment request is awaiting Registrar review. Monitor the student portal for the result.';
        }

        if ($enrollment->status === 'rejected') {
            return 'Sign in to review the Registrar remarks on your enrollment request and contact the Registrar for guidance.';
        }

        if ($enrollment->status === 'approved' && $enrollment->payment?->status === 'pending') {
            return 'Your payment proof is awaiting Cashier verification. Monitor the student portal for an update.';
        }

        if ($enrollment->status === 'approved' && $enrollment->payment?->status === 'rejected') {
            return 'Sign in to review the payment rejection reason and resubmit proof through the supported enrollment payment flow.';
        }

        if ($enrollment->status === 'approved' && $enrollment->payment?->status === 'verified' && !$enrollment->is_paid) {
            return 'A verified payment record exists, but enrollment is not marked paid. Contact the Cashier to confirm the record before making another payment.';
        }

        if ($enrollment->status === 'approved' && !$enrollment->is_paid) {
            return 'Your enrollment is approved. Pay the enrollment fee through the student portal or Cashier; enrollment completes after payment verification.';
        }

        return 'Your enrollment is not fully confirmed yet. Sign in to the portal or contact the Registrar or Cashier for assistance.';
    }
}
