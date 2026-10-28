<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

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

    public function documentRequest()
    {
        return $this->hasOne(DocumentRequest::class);
    }

    public function documentRequests()
    {
        return $this->hasMany(DocumentRequest::class);
    }

    public function getDocumentLabelsAttribute(): Collection
    {
        $labels = $this->documentRequests
            ->pluck('document_label')
            ->filter()
            ->unique()
            ->values();

        if ($labels->isNotEmpty()) {
            return $labels;
        }

        $legacyLabel = match ($this->document_type) {
            'transcript', 'tor' => 'Transcript of Records',
            'diploma' => 'Diploma',
            'certification' => 'Certification',
            'true_copy' => 'True Copy of Records',
            default => ucfirst(str_replace('_', ' ', (string) $this->document_type)),
        };

        return collect([$legacyLabel])->filter()->values();
    }

    public function isPaid(): bool
    {
        return $this->payments()->where('status', 'verified')->exists();
    }

    public function scheduleHasPassed(): bool
    {
        if (! in_array($this->status, ['pending', 'confirmed'], true) || ! $this->slot) {
            return false;
        }

        return \Carbon\Carbon::parse($this->slot->date.' '.$this->slot->end_time)->lte(now());
    }

    public function paymentNotRequired(): bool
    {
        $documents = $this->documentRequests;
        return $documents->isNotEmpty() && $documents->every(fn ($document) => $document->paymentNotRequired());
    }

    public function canBeConfirmed(): bool
    {
        if ($this->status !== 'pending') {
            return false;
        }

        $slot = $this->relationLoaded('slot') ? $this->getRelation('slot') : $this->slot()->first();
        if (! $slot) {
            return false;
        }

        $isPast = $slot->date < today()->toDateString()
            || ($slot->date === today()->toDateString() && $slot->start_time <= now()->format('H:i:s'));
        $activeBookings = $slot->appointments()->whereIn('status', ['pending', 'confirmed'])->count();
        $schedule = app(\App\Services\AppointmentScheduleService::class);

        return ($slot->status ?? 'open') === 'open'
            && ! $isPast
            && ! $schedule->isClosed($slot->date, $slot->start_time, $slot->end_time)
            && $activeBookings <= $slot->max_bookings;
    }
}
