<?php

namespace Database\Factories;

use App\Models\Appointment;
use Illuminate\Database\Eloquent\Factories\Factory;

class AppointmentPaymentFactory extends Factory
{
    protected $model = \App\Models\AppointmentPayment::class;

    public function definition()
    {
        return [
            'appointment_id'   => Appointment::factory(),
            'payment_method'   => 'gcash',
            'reference_number' => (string) fake()->numerify('##########'),
            'proof_of_payment' => 'appointment-proofs/fake-proof.jpg',
            'status'           => 'pending',
            'amount'           => 0,
            'rejection_reason' => null,
            'verified_by'      => null,
            'verified_at'      => null,
        ];
    }

    public function verified()
    {
        return $this->state(fn () => [
            'status'      => 'verified',
            'verified_at' => now(),
        ]);
    }

    public function rejected()
    {
        return $this->state(fn () => [
            'status'           => 'rejected',
            'rejection_reason' => 'Reference number does not match.',
            'verified_at'      => now(),
        ]);
    }
}
