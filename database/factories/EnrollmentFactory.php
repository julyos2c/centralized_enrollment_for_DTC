<?php

namespace Database\Factories;

use App\Models\Program;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class EnrollmentFactory extends Factory
{
    protected $model = \App\Models\Enrollment::class;

    public function definition()
    {
        return [
            'user_id'     => User::factory(),
            'program_id'  => Program::factory(),
            'year_level'  => 1,
            'semester'    => 1,
            'subject_ids' => [1, 2, 3, 4],
            'status'      => 'approved',
            'is_paid'     => false,
        ];
    }

    public function pending()
    {
        return $this->state(fn () => ['status' => 'pending']);
    }

    public function paid()
    {
        return $this->state(fn () => ['status' => 'approved', 'is_paid' => true]);
    }
}
