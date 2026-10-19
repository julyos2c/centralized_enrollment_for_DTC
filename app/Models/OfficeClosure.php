<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OfficeClosure extends Model
{
    protected $fillable = ['start_date', 'end_date', 'start_time', 'end_time', 'title', 'type', 'reason', 'is_confirmed', 'is_active', 'created_by', 'updated_by'];

    protected $casts = ['is_confirmed' => 'boolean', 'is_active' => 'boolean'];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
