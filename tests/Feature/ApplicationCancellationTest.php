<?php

namespace Tests\Feature;

use App\Mail\ApplicationCancellationRequested;
use App\Models\Application;
use App\Models\ApplicationCancellationRequest;
use App\Models\AuditLog;
use App\Models\Program;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\SeedsRoles;
use Tests\TestCase;

class ApplicationCancellationTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_applicant_can_request_cancellation_with_confirmation_capability_without_changing_status(): void
    {
        Mail::fake();
        $admin = $this->makeUserWithRole('admin');
        $registrar = $this->makeUserWithRole('registrar');
        $application = $this->makeApplication();
        $token = str_repeat('a', 64);
        $application->update(['confirmation_token_hash' => hash('sha256', $token), 'confirmation_token_expires_at' => now()->addDay()]);

        $this->withSession(['application_confirmation_token' => $token])
            ->post(route('public.application.cancel'), ['reason' => 'I need to withdraw this submission.'])
            ->assertRedirect(route('public.application.success'))
            ->assertSessionHas('success');

        $this->assertSame('submitted', $application->fresh()->status);
        $this->assertDatabaseHas('application_cancellation_requests', [
            'application_id' => $application->id,
            'status' => 'pending',
            'previous_status' => 'submitted',
        ]);
        $this->assertDatabaseHas('user_notifications', ['user_id' => $admin->id, 'title' => 'Application Cancellation Requested']);
        $this->assertDatabaseHas('user_notifications', ['user_id' => $registrar->id, 'title' => 'Application Cancellation Requested']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'application.cancellation_requested']);
        Mail::assertSent(ApplicationCancellationRequested::class);
    }

    public function test_duplicate_request_is_idempotent_and_confirmation_token_cannot_target_another_application(): void
    {
        Mail::fake();
        $this->makeUserWithRole('admin');
        $this->makeUserWithRole('registrar');
        $first = $this->makeApplication();
        $second = $this->makeApplication();
        $token = str_repeat('b', 64);
        $first->update(['confirmation_token_hash' => hash('sha256', $token), 'confirmation_token_expires_at' => now()->addDay()]);

        foreach ([1, 2] as $attempt) {
            $this->withSession(['application_confirmation_token' => $token])
                ->post(route('public.application.cancel'), ['reason' => 'I have submitted by mistake.'])
                ->assertRedirect(route('public.application.success'));
        }

        $this->assertSame(1, ApplicationCancellationRequest::where('application_id', $first->id)->count());
        $this->assertSame(0, ApplicationCancellationRequest::where('application_id', $second->id)->count());
        $this->assertSame(1, AuditLog::where('action', 'application.cancellation_requested')->count());
        $this->assertSame(2, UserNotification::where('title', 'Application Cancellation Requested')->count());
        Mail::assertSent(ApplicationCancellationRequested::class, 1);

        $this->withSession(['application_confirmation_token' => str_repeat('c', 64)])
            ->post(route('public.application.cancel'), ['reason' => 'I have submitted by mistake.'])
            ->assertNotFound();
    }

    public function test_registrar_can_approve_and_admin_can_decline_cancellation_with_history_preserved(): void
    {
        Mail::fake();
        $registrar = $this->makeUserWithRole('registrar');
        $admin = $this->makeUserWithRole('admin');
        $approvedApplication = $this->makeApplication(['status' => 'under_review', 'remarks' => 'Existing review note']);
        $approvedRequest = $this->makeCancellationRequest($approvedApplication);

        $this->actingAs($registrar)
            ->post(route('registrar.applications.cancellation.approve', $approvedRequest))
            ->assertRedirect(route('registrar.applications.show', $approvedApplication));

        $this->assertSame('cancelled', $approvedApplication->fresh()->status);
        $this->assertSame('Existing review note', $approvedApplication->fresh()->remarks);
        $this->assertSame('approved', $approvedRequest->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'application.cancellation_approved', 'user_id' => $registrar->id]);

        $declinedApplication = $this->makeApplication(['status' => 'revision_required']);
        $declinedRequest = $this->makeCancellationRequest($declinedApplication);
        $this->actingAs($admin)
            ->post(route('registrar.applications.cancellation.reject', $declinedRequest), ['remarks' => 'Please complete the requested revisions first.'])
            ->assertRedirect(route('registrar.applications.show', $declinedApplication));

        $this->assertSame('revision_required', $declinedApplication->fresh()->status);
        $this->assertSame('rejected', $declinedRequest->fresh()->status);
        $this->assertSame('Please complete the requested revisions first.', $declinedRequest->fresh()->review_remarks);
        $this->assertDatabaseHas('audit_logs', ['action' => 'application.cancellation_rejected', 'user_id' => $admin->id]);
    }

    public function test_student_cannot_resolve_request_and_ineligible_applications_cannot_request_or_be_cancelled(): void
    {
        $student = $this->makeUserWithRole('student');
        $application = $this->makeApplication(['status' => 'approved']);
        $request = $this->makeCancellationRequest($application);

        $this->actingAs($student)
            ->post(route('registrar.applications.cancellation.approve', $request))
            ->assertForbidden();

        $token = str_repeat('d', 64);
        $application->update(['confirmation_token_hash' => hash('sha256', $token), 'confirmation_token_expires_at' => now()->addDay()]);
        $this->withSession(['application_confirmation_token' => $token])
            ->post(route('public.application.cancel'), ['reason' => 'I no longer need this application.'])
            ->assertSessionHasErrors('cancellation');

        $this->assertSame('approved', $application->fresh()->status);
    }

    public function test_pending_cancellation_prevents_start_review_and_other_registrar_decisions(): void
    {
        $registrar = $this->makeUserWithRole('registrar');
        $application = $this->makeApplication(['status' => 'submitted']);
        $request = $this->makeCancellationRequest($application);

        $this->actingAs($registrar)
            ->post(route('registrar.applications.start-review', $application))
            ->assertSessionHas('error');
        $this->post(route('registrar.applications.approve', $application))
            ->assertRedirect(route('registrar.applications.show', $application));
        $this->post(route('registrar.applications.reject', $application), ['remarks' => 'No longer applicable.'])
            ->assertSessionHas('error');

        $this->assertSame('submitted', $application->fresh()->status);
        $this->assertSame('pending', $request->fresh()->status);
    }

    public function test_cancelled_status_and_pending_review_are_reflected_on_authorized_screens(): void
    {
        $registrar = $this->makeUserWithRole('registrar');
        $application = $this->makeApplication(['status' => 'submitted']);
        $token = str_repeat('e', 64);
        $application->update(['confirmation_token_hash' => hash('sha256', $token), 'confirmation_token_expires_at' => now()->addDay()]);
        $this->withSession(['application_confirmation_token' => $token])->get(route('public.application.success'))
            ->assertOk()->assertSee('Request application cancellation');

        $request = $this->makeCancellationRequest($application);
        $this->actingAs($registrar)->get(route('registrar.applications.show', $application))
            ->assertOk()->assertSee('Cancellation Request')->assertSee('Approve Cancellation');

        $application->update(['status' => 'cancelled']);
        $application->cancellationRequests()->whereKey($request->id)->update(['status' => 'approved', 'reviewed_at' => now()]);
        $this->assertSame('Application cancelled', $application->fresh()->getProgressTimeline()[3]['label']);
        $this->assertStringContainsString('approved your cancellation request', $application->fresh()->getStatusDescription());
        $this->post(route('public.application.status'), [
            'reference_no' => $application->reference_no,
            'email' => $application->email,
        ])->assertOk()->assertSee('Application cancelled')->assertSee('The Registrar approved a cancellation request');
    }

    private function makeApplication(array $attributes = []): Application
    {
        return Application::create(array_merge([
            'reference_no' => 'APP-CANCEL-'.uniqid(),
            'program_id' => Program::factory()->create()->id,
            'academic_status' => 'new_student',
            'first_name' => 'Test',
            'last_name' => 'Applicant',
            'email' => 'applicant@example.test',
            'status' => 'submitted',
        ], $attributes));
    }

    private function makeCancellationRequest(Application $application): ApplicationCancellationRequest
    {
        return ApplicationCancellationRequest::create([
            'application_id' => $application->id,
            'previous_status' => $application->status,
            'status' => 'pending',
            'reason' => 'I need to withdraw this application.',
        ]);
    }
}
