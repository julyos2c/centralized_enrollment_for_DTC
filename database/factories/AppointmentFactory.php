<?php

namespace Database\Factories;

use App\Models\AppointmentSlot;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AppointmentFactory extends Factory
{
    protected $model = \App\Models\Appointment::class;

    public function definition()
    {
        return [
            'user_id'             => User::factory(),
            'appointment_slot_id' => AppointmentSlot::factory(),
            'document_type'       => fake()->randomElement(['transcript', 'diploma', 'certification', 'tor']),
            'purpose'             => fake()->optional()->sentence(),
            'status'              => 'pending',
            'remarks'             => null,
        ];
    }

    public function confirmed()
    {
        return $this->state(fn () => ['status' => 'confirmed']);
    }

    public function cancelled()
    {
        return $this->state(fn () => ['status' => 'cancelled', 'remarks' => 'Slot unavailable.']);
    }
}
