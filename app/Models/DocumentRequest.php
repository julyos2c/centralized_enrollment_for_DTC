<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentRequest extends Model
{
    protected $fillable = [
        'user_id', 'appointment_id', 'document_type',
        'copies', 'purpose', 'status', 'fee', 'is_paid',
        'receipt_no', 'remarks'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
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
            default      => '#94A3B8',
        };
    }
}