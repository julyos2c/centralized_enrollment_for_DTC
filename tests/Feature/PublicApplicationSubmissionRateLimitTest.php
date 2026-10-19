<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Program;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PublicApplicationSubmissionRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_submissions_are_allowed_up_to_the_ip_limit_and_fresh_forms_do_not_reset_it(): void
    {
        config(['services.application_submission.per_ip_per_hour' => 2]);
        Mail::fake();
        $program = Program::factory()->create();

        foreach ([1, 2] as $number) {
            $this->get(route('public.application.create'))->assertOk();
            $payload = [
                'submission_token' => $this->app['session.store']->get('public_application_submission_token'),
                'academic_status' => 'new_student',
                'program_id' => $program->id,
                'first_name' => 'Applicant',
                'last_name' => 'Number ' . $number,
                'gender' => 'female',
                'birthdate' => '2005-01-01',
                'email' => 'applicant' . $number . '@example.test',
                'ack_enrollment' => 'on',
            ];

            $this->post(route('public.application.store'), $payload)
                ->assertRedirect(route('public.application.success'));
        }

        $this->assertSame(2, Application::count());
        $this->get(route('public.application.create'))->assertOk();
        $payload['submission_token'] = $this->app['session.store']->get('public_application_submission_token');
        $payload['email'] = 'applicant3@example.test';

        $this->post(route('public.application.store'), $payload)->assertStatus(429);
        $this->assertSame(2, Application::count());
    }

    public function test_submission_token_replay_remains_rejected_below_the_ip_limit(): void
    {
        config(['services.application_submission.per_ip_per_hour' => 5]);
        Mail::fake();
        $program = Program::factory()->create();
        $this->get(route('public.application.create'))->assertOk();
        $payload = [
            'submission_token' => $this->app['session.store']->get('public_application_submission_token'),
            'academic_status' => 'new_student',
            'program_id' => $program->id,
            'first_name' => 'Token',
            'last_name' => 'Replay',
            'gender' => 'female',
            'birthdate' => '2005-01-01',
            'email' => 'token-replay@example.test',
            'ack_enrollment' => 'on',
        ];

        $this->post(route('public.application.store'), $payload)
            ->assertRedirect(route('public.application.success'));
        $this->post(route('public.application.store'), $payload)
            ->assertSessionHasErrors('application');

        $this->assertSame(1, Application::count());
    }
}
