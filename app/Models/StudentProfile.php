<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * StudentProfile — student-specific institutional identity.
 *
 * Separates authentication identity (User) from academic identity.
 *
 * users            = account / authentication / role
 * student_profiles = student number, LRN, admission type, program, etc.
 *
 * New fields (migration 2026_09_01_000001):
 *   enrolled_ay      — AY the student first paid; e.g. "2025-2026".
 *                      Set once at payment confirmation, never changed.
 *   graduation_year  — calendar year the student graduated; e.g. 2026.
 *                      Set by Registrar on alumni promotion.
 */
class StudentProfile extends Model
{
    protected $fillable = [
        'user_id',
        'student_number',
        'lrn',
        'admission_type',
        'gender',
        'birthdate',
        'phone',
        'address',
        'program_id',
        'enrolled_ay',
        'graduation_year',
    ];

    protected $casts = [
        'birthdate'       => 'date',
        'graduation_year' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function program()
    {
        return $this->belongsTo(Program::class);
    }

    public static function generateStudentNumber(): string
    {
        $year   = date('Y');
        $prefix = $year . '-';

        $last = static::where('student_number', 'like', $prefix . '%')
            ->orderByDesc('student_number')
            ->value('student_number');

        $seq = $last
            ? ((int) substr($last, strlen($prefix))) + 1
            : 1;

        return $prefix . str_pad($seq, 5, '0', STR_PAD_LEFT);
    }
}
