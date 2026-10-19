<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id', 'appointment_slot_id', 'document_type', 'purpose', 'status', 'remarks'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function slot()
    {
        return $this->belongsTo(AppointmentSlot::class, 'appointment_slot_id');
    }

    public function payments()
    {
        return $this->hasMany(\App\Models\AppointmentPayment::class);
    }

    public function latestPayment()
    {
        return $this->hasOne(\App\Models\AppointmentPayment::class)->latestOfMany();
    }

    public function isPaid(): bool
    {
        return $this->payments()->where('status', 'verified')->exists();
    }
}