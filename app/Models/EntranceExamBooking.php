<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EntranceExamBooking extends Model
{
    /** Statuses that hold a seat in the session. */
    public const SEATED_STATUSES = ['pending', 'confirmed', 'attended', 'absent'];

    /** Statuses that hold the applicant's single active booking. */
    public const ACTIVE_STATUSES = ['pending', 'confirmed', 'attended'];

    /** Statuses the applicant may still cancel or move. */
    public const CHANGEABLE_STATUSES = ['pending', 'confirmed'];

    public const STATUS_LABELS = [
        'pending' => 'Awaiting approval',
        'confirmed' => 'Confirmed',
        'rejected' => 'Not approved',
        'cancelled' => 'Cancelled',
        'rescheduled' => 'Rescheduled',
        'attended' => 'Attended',
        'absent' => 'Absent',
    ];

    protected $fillable = [
        'application_id',
        'entrance_exam_session_id',
        'status',
        'active_application_id',
        'score',
        'result',
        'result_remarks',
        'result_recorded_at',
        'result_recorded_by',
        'result_released_at',
        'attendance_marked_at',
        'attendance_marked_by',
        'staff_remarks',
        'rescheduled_to_id',
        'reminder_sent_at',
    ];

    protected $casts = [
        'score' => 'decimal:2',
        'result_recorded_at' => 'datetime',
        'result_released_at' => 'datetime',
        'attendance_marked_at' => 'datetime',
        'reminder_sent_at' => 'datetime',
    ];

    public function application()
    {
        return $this->belongsTo(Application::class);
    }

    public function session()
    {
        return $this->belongsTo(EntranceExamSession::class, 'entrance_exam_session_id');
    }

    public function rescheduledTo()
    {
        return $this->belongsTo(self::class, 'rescheduled_to_id');
    }

    public function isActive(): bool
    {
        return in_array($this->status, self::ACTIVE_STATUSES, true);
    }

    public function isResultReleased(): bool
    {
        return $this->result !== null && $this->result_released_at !== null;
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? ucfirst($this->status);
    }

    /** Keep the double-booking guard column in step with the status. */
    public static function activeKeyFor(string $status, int $applicationId): ?int
    {
        return in_array($status, self::ACTIVE_STATUSES, true) ? $applicationId : null;
    }
}
