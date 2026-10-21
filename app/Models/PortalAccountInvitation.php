<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PortalAccountInvitation extends Model
{
    protected $fillable = [
        'student_profile_id', 'user_id', 'invited_by', 'token_hash', 'status',
        'failure_category', 'expires_at', 'delivered_at', 'consumed_at', 'revoked_at',
    ];

    protected $hidden = ['token_hash'];

    protected $casts = [
        'expires_at' => 'datetime',
        'delivered_at' => 'datetime',
        'consumed_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function studentProfile()
    {
        return $this->belongsTo(StudentProfile::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function invitedBy()
    {
        return $this->belongsTo(User::class, 'invited_by');
    }
}
