<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Program;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\SeedsRoles;
use Tests\TestCase;

class ApplicationEducationLevelsTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    public function test_levels_are_validated_against_applicant_type_including_direct_posts(): void
    {
        $this->seedRoles();
        Mail::fake();
        config(['services.application_submission.per_ip_per_hour' => 100]);
        $program = Program::factory()->create();
        $cases = [
            ['new_student', 'College', false], ['new_student', 'Junior High School', false],
            ['new_student', 'Senior High School', true], ['new_student', 'Other', true],
            ['transferee', 'Senior High School', false], ['transferee', 'College', true],
            ['shiftee', 'Other', false], ['shiftee', 'College', true],
            ['returnee', 'Junior High School', false], ['returnee', 'College', true],
            ['cross_enrollee', 'Senior High School', false], ['cross_enrollee', 'College', true],
            ['new_student', null, true], ['transferee', null, true],
        ];
        foreach ($cases as $index => [$type, $level, $allowed]) {
            $this->get(route('public.application.create'))->assertOk();
            $email = 'education-level-'.$index.'@example.test';
            $response = $this->from(route('public.application.create'))->post(route('public.application.store'), [
                'submission_token' => session('public_application_submission_token'), 'academic_status' => $type,
                'program_id' => $program->id, 'first_name' => 'Test', 'last_name' => 'Applicant',
                'gender' => 'female', 'birthdate' => '2005-01-01', 'email' => $email,
                'ack_enrollment' => 'on', 'education_level' => $level,
            ]);
            if ($allowed) {
                $response->assertRedirect(route('public.application.success'));
                $this->assertDatabaseHas('applications', ['email' => $email, 'education_level' => $level]);
            } else {
                $response->assertSessionHasErrors('education_level');
                $this->assertFalse(Application::where('email', $email)->exists());
            }
        }
    }
}
