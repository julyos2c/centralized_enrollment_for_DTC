<?php

namespace Tests\Feature;

use App\Mail\ApplicationRevisionRequired;
use App\Models\Application;
use App\Models\Program;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\SeedsRoles;
use Tests\TestCase;

class RegistrarApplicationDecisionNotificationTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_rejection_is_persisted_and_in_app_alerts_are_sent_when_email_fails(): void
    {
        $registrar = $this->makeUserWithRole('registrar');
        $admin = $this->makeUserWithRole('admin');
        $applicant = $this->makeUserWithRole('student');
        $application = $this->makeApplication(['user_id' => $applicant->id]);
        Mail::shouldReceive('to')->once()->with($application->email)->andThrow(new \RuntimeException('SMTP unavailable'));

        $this->actingAs($registrar)
            ->post(route('registrar.applications.reject', $application), ['remarks' => 'Missing records.'])
            ->assertRedirect(route('registrar.applications.index'))
            ->assertSessionHas('warning');

        $this->assertSame('rejected', $application->fresh()->status);
        $this->assertSame('Missing records.', $application->fresh()->remarks);
        $this->assertDatabaseHas('user_notifications', ['user_id' => $admin->id, 'title' => 'Application Rejected']);
        $this->assertDatabaseHas('user_notifications', ['user_id' => $applicant->id, 'title' => 'Application Rejected']);
    }

    public function test_revision_is_persisted_and_notices_are_sent_when_email_fails(): void
    {
        $registrar = $this->makeUserWithRole('registrar');
        $admin = $this->makeUserWithRole('admin');
        $applicant = $this->makeUserWithRole('student');
        $application = $this->makeApplication(['user_id' => $applicant->id]);
        Mail::shouldReceive('to')->once()->with($application->email)->andThrow(new \RuntimeException('SMTP unavailable'));

        $this->actingAs($registrar)
            ->post(route('registrar.applications.revision', $application), ['remarks' => 'Upload a clearer file.'])
            ->assertRedirect(route('registrar.applications.index'))
            ->assertSessionHas('warning');

        $this->assertSame('revision_required', $application->fresh()->status);
        $this->assertSame('Upload a clearer file.', $application->fresh()->remarks);
        $this->assertDatabaseHas('user_notifications', ['user_id' => $admin->id, 'title' => 'Application Requires Revision']);
        $this->assertDatabaseHas('user_notifications', ['user_id' => $applicant->id, 'title' => 'Action Required: Application Revision']);
    }

    public function test_invalid_email_and_missing_portal_account_do_not_block_rejection(): void
    {
        $registrar = $this->makeUserWithRole('registrar');
        $admin = $this->makeUserWithRole('admin');
        $application = $this->makeApplication(['email' => '']);
        Mail::shouldReceive('to')->never();

        $this->actingAs($registrar)
            ->post(route('registrar.applications.reject', $application), ['remarks' => 'Incomplete.'])
            ->assertRedirect(route('registrar.applications.index'))
            ->assertSessionHas('warning');

        $this->assertSame('rejected', $application->fresh()->status);
        $this->assertDatabaseHas('user_notifications', ['user_id' => $admin->id, 'title' => 'Application Rejected']);
        $this->assertSame(1, UserNotification::where('user_id', $admin->id)->count());
    }

    public function test_successful_revision_email_is_preserved_and_retry_does_not_duplicate_notifications(): void
    {
        Mail::fake();
        $registrar = $this->makeUserWithRole('registrar');
        $applicant = $this->makeUserWithRole('student');
        $application = $this->makeApplication(['user_id' => $applicant->id]);

        $this->actingAs($registrar)
            ->post(route('registrar.applications.revision', $application), ['remarks' => 'Correct the name.'])
            ->assertSessionHas('success');

        Mail::assertSent(ApplicationRevisionRequired::class);
        $this->assertSame(1, UserNotification::where('user_id', $applicant->id)->count());
        $this->actingAs($registrar)
            ->post(route('registrar.applications.revision', $application), ['remarks' => 'Correct the name.'])
            ->assertRedirect(route('registrar.applications.show', $application));
        $this->assertSame(1, UserNotification::where('user_id', $applicant->id)->count());
    }

    public function test_applicant_without_portal_account_receives_email_and_admin_notice(): void
    {
        Mail::fake();
        $registrar = $this->makeUserWithRole('registrar');
        $admin = $this->makeUserWithRole('admin');
        $application = $this->makeApplication();

        $this->actingAs($registrar)
            ->post(route('registrar.applications.reject', $application), ['remarks' => 'Ineligible.'])
            ->assertSessionHas('success');

        Mail::assertSent(\App\Mail\ApplicationRejected::class);
        $this->assertDatabaseHas('user_notifications', ['user_id' => $admin->id, 'title' => 'Application Rejected']);
        $this->assertSame(1, UserNotification::where('title', 'Application Rejected')->count());
    }

    private function makeApplication(array $attributes = []): Application
    {
        $program = Program::factory()->create();

        return Application::create(array_merge([
            'reference_no' => 'APP-NOTIFY-' . uniqid(),
            'program_id' => $program->id,
            'academic_status' => 'new_student',
            'first_name' => 'Test',
            'last_name' => 'Applicant',
            'gender' => 'female',
            'birthdate' => '2005-01-01',
            'email' => 'applicant-' . uniqid() . '@example.test',
            'status' => 'under_review',
        ], $attributes));
    }
}
