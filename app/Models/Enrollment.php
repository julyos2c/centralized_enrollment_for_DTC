<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Enrollment extends Model
{
    protected $fillable = [
        'user_id', 'program_id', 'year_level', 'semester',
        'subject_ids', 'status', 'remarks', 'is_paid',
    ];

    protected $casts = [
        'subject_ids' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function program()
    {
        return $this->belongsTo(Program::class);
    }

    public function payment()
    {
        return $this->hasOne(Payment::class);
    }
}