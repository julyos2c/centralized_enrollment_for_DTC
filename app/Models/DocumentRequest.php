<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentRequest extends Model
{
    protected $fillable = [
        'user_id', 'appointment_id', 'document_type',
        'copies', 'purpose', 'status', 'fee', 'is_paid',
        'receipt_no', 'remarks', 'payment_method', 'payment_status',
        'amount_received', 'reference_number', 'proof_of_payment',
        'verified_by', 'verified_at', 'payment_submitted_at', 'payment_rejection_reason',
        'rejected_by', 'rejected_at',
    ];

    protected $casts = [
        'is_paid' => 'boolean',
        'fee' => 'decimal:2',
        'amount_received' => 'decimal:2',
        'verified_at' => 'datetime',
        'payment_submitted_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }

    public function usesBundledPayment(): bool
    {
        return $this->appointment_id && AppointmentPayment::where('appointment_id', $this->appointment_id)
            ->where('is_document_bundle', true)->exists();
    }

    public function paymentNotRequired(): bool
    {
        return $this->fee !== null && (float) $this->fee === 0.0 && !$this->is_paid && $this->payment_status !== 'verified';
    }

    public function paymentSatisfied(): bool
    {
        return $this->paymentNotRequired() || ($this->is_paid && $this->payment_status === 'verified');
    }

    public function verifier()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function rejecter()
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function getDocumentLabelAttribute(): string
    {
        return match($this->document_type) {
            'tor'          => 'Transcript of Records',
            'diploma'      => 'Diploma',
            'certification'=> 'Certification',
            'true_copy'    => 'True Copy of Records',
            default        => ucfirst($this->document_type),
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'submitted'  => '#F59E0B',
            'processing' => '#3B82F6',
            'ready'      => '#8B5CF6',
            'released'   => '#22C55E',
            'cancelled'  => '#DC2626',
            default      => '#94A3B8',
        };
    }
}
