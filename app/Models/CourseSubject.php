<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourseSubject extends Model
{
    protected $fillable = ['program_id', 'subject_code', 'subject_name', 'year_level', 'semester'];

    public function program()
    {
        return $this->belongsTo(Program::class);
    }
}