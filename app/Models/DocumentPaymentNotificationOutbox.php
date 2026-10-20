<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentPaymentNotificationOutbox extends Model
{
    protected $table = 'document_payment_notification_outbox';

    protected $fillable = [
        'event_key', 'document_request_id', 'recipient_ids', 'title', 'message',
        'type', 'link', 'status', 'attempts', 'max_attempts', 'available_at',
        'delivered_at', 'last_error',
    ];

    protected $casts = [
        'recipient_ids' => 'array',
        'available_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];
}
