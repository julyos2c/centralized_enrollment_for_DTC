<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppointmentSlot extends Model
{
    protected $fillable = ['date', 'start_time', 'end_time', 'max_bookings'];

    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }

    public function availableSlots(): int
    {
        return $this->max_bookings - $this->appointments()
            ->whereNotIn('status', ['cancelled'])
            ->count();
    }

    public function isAvailable(): bool
    {
        return $this->availableSlots() > 0;
    }
}