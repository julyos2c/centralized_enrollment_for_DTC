<?php

namespace Database\Factories;

use App\Models\Enrollment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PaymentFactory extends Factory
{
    protected $model = \App\Models\Payment::class;

    public function definition()
    {
        return [
            'enrollment_id'   => Enrollment::factory(),
            'payment_method'  => 'walk_in',
            'status'          => 'verified',
            'amount'          => 500.00,
            'receipt_no'      => 'DTC-' . strtoupper(Str::random(8)),
            'paid_at'         => now(),
        ];
    }

    public function gcashPending()
    {
        return $this->state(fn () => [
            'payment_method'    => 'gcash',
            'reference_number'  => (string) fake()->numerify('##########'),
            'proof_of_payment'  => 'payment-proofs/fake-proof.jpg',
            'status'            => 'pending',
            'processed_by'      => null,
            'verified_by'       => null,
            'paid_at'           => null,
        ]);
    }

    public function rejected()
    {
        return $this->state(fn () => [
            'payment_method' => 'gcash',
            'status'         => 'rejected',
        ]);
    }
}
