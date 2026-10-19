<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * CreditedSubject — registrar-entered record documenting that a DTC subject
 * was satisfied by an equivalent subject at a previous school.
 *
 * Documentation only. See migration for the reasoning — this deliberately
 * does not touch COR, subject_ids, or payment calculations.
 */
class CreditedSubject extends Model
{
    protected $fillable = [
        'enrollment_id',
        'course_subject_id',
        'equivalent_subject',
        'equivalent_school',
        'credited_units',
        'remarks',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'credited_units' => 'decimal:1',
    ];

    public function enrollment()
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function courseSubject()
    {
        return $this->belongsTo(CourseSubject::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
