<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ProgramFactory extends Factory
{
    protected $model = \App\Models\Program::class;

    public function definition()
    {
        return [
            'name' => 'BS Information Systems',
            'code' => 'BSIS',
        ];
    }
}
