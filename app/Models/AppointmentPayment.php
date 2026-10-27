<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppointmentPayment extends Model
{
    use HasFactory;
    protected $fillable = [
        'appointment_id',
        'is_document_bundle',
        'payment_method',
        'reference_number',
        'proof_of_payment',
        'status',
        'amount',
        'receipt_no',
        'rejection_reason',
        'verified_by',
        'verified_at',
    ];

    protected $casts = [
        'verified_at' => 'datetime',
        'amount'      => 'decimal:2',
        'is_document_bundle' => 'boolean',
    ];

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }

    public function verifier()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function isPending(): bool  { return $this->status === 'pending'; }
    public function isVerified(): bool { return $this->status === 'verified'; }
    public function isRejected(): bool { return $this->status === 'rejected'; }
    public function isGcash(): bool    { return $this->payment_method === 'gcash'; }
}
