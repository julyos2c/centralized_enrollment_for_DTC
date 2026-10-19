<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class AppointmentSlotFactory extends Factory
{
    protected $model = \App\Models\AppointmentSlot::class;

    public function definition()
    {
        return [
            'date'         => now()->addDays(rand(1, 14))->format('Y-m-d'),
            'start_time'   => '08:00:00',
            'end_time'     => '17:00:00',
            'max_bookings' => 5,
        ];
    }

    public function full()
    {
        return $this->state(fn () => ['max_bookings' => 0]);
    }

    public function past()
    {
        return $this->state(fn () => [
            'date' => now()->subDay()->format('Y-m-d'),
        ]);
    }
}
