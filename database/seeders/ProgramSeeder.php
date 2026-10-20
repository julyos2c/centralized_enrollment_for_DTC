<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Program;
use App\Models\CourseSubject;
use Database\Seeders\Concerns\DevelopmentOnly;

/**
 * Sample BSIS program/subjects for local development only (not an approved
 * curriculum). Refuses to run in production.
 */
class ProgramSeeder extends Seeder
{
    use DevelopmentOnly;

    public function run(): void
    {
        $this->ensureDevelopmentEnvironment();

        $bsis = Program::firstOrCreate(['code' => 'BSIS'], ['name' => 'BS Information Systems']);

        $subjects = [
            ['subject_code' => 'IS101', 'subject_name' => 'Introduction to Computing', 'year_level' => 1, 'semester' => 1],
            ['subject_code' => 'IS102', 'subject_name' => 'Computer Programming 1',    'year_level' => 1, 'semester' => 1],
            ['subject_code' => 'IS103', 'subject_name' => 'Discrete Mathematics',      'year_level' => 1, 'semester' => 1],
            ['subject_code' => 'GE101', 'subject_name' => 'Purposive Communication',   'year_level' => 1, 'semester' => 1],
        ];

        foreach ($subjects as $s) {
            CourseSubject::firstOrCreate(
                ['program_id' => $bsis->id, 'subject_code' => $s['subject_code']],
                $s
            );
        }
    }
}