<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppointmentSlot extends Model
{
    use HasFactory;
    protected $fillable = ['date', 'start_time', 'end_time', 'max_bookings', 'source', 'status'];

    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }

    public function availableSlots(): int
    {
        return $this->max_bookings - $this->appointments()
            ->whereIn('status', ['pending', 'confirmed'])
            ->count();
    }

    public function isAvailable(): bool
    {
        return ($this->status ?? 'open') === 'open' && $this->availableSlots() > 0;
    }
}
