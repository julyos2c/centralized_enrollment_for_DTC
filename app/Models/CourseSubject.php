<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CourseSubject extends Model
{
    use HasFactory;

    protected $fillable = ['program_id', 'subject_code', 'subject_name', 'units', 'year_level', 'semester'];

    public function program()
    {
        return $this->belongsTo(Program::class);
    }

    public function schedules()
    {
        return $this->hasMany(ClassSchedule::class);
    }
}
