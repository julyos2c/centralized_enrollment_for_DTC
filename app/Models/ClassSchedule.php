<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClassSchedule extends Model
{
    protected $fillable = [
        'course_subject_id',
        'day_pattern',
        'time_start',
        'time_end',
        'room',
        'instructor_name',
    ];

    public function courseSubject()
    {
        return $this->belongsTo(CourseSubject::class);
    }
}