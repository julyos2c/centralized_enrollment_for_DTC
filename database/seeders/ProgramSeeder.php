<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Program;
use App\Models\CourseSubject;

class ProgramSeeder extends Seeder
{
    public function run(): void
    {
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