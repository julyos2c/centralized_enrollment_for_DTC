<?php

namespace Database\Factories;

use App\Models\Program;
use Illuminate\Database\Eloquent\Factories\Factory;

class CourseSubjectFactory extends Factory
{
    protected $model = \App\Models\CourseSubject::class;

    public function definition()
    {
        return [
            'program_id'   => Program::factory(),
            'subject_code' => strtoupper($this->faker->unique()->bothify('IS###')),
            'subject_name' => $this->faker->words(3, true),
            'units'        => 3,
            'year_level'   => 1,
            'semester'     => 1,
        ];
    }

    public function yearSem(int $year, int $semester)
    {
        return $this->state(fn () => ['year_level' => $year, 'semester' => $semester]);
    }
}
