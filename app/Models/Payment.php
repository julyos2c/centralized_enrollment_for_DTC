<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'enrollment_id',
        'payment_method',
        'reference_number',
        'proof_of_payment',
        'status',
        'rejection_reason',
        'processed_by',
        'verified_by',
        'verified_at',
        'amount',
        'receipt_no',
        'paid_at',
    ];

    protected $casts = [
        'paid_at'     => 'datetime',
        'verified_at' => 'datetime',
        'amount'      => 'decimal:2',
    ];

    // ── Relationships ──────────────────────────────────────────────

    public function enrollment()
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function cashier()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function verifier()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    // ── Helpers ───────────────────────────────────────────────────

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isVerified(): bool
    {
        return $this->status === 'verified';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function isGcash(): bool
    {
        return $this->payment_method === 'gcash';
    }

    public function isWalkIn(): bool
    {
        return $this->payment_method === 'walk_in';
    }
}
