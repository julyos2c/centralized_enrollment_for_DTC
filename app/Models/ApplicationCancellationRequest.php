<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApplicationCancellationRequest extends Model
{
    protected $fillable = [
        'application_id',
        'requested_by',
        'reviewed_by',
        'previous_status',
        'status',
        'reason',
        'review_remarks',
        'reviewed_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    public function application()
    {
        return $this->belongsTo(Application::class);
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
