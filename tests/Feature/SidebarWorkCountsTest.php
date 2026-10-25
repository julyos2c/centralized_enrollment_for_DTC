<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Appointment;
use App\Models\DocumentRequest;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SeedsRoles;
use Tests\TestCase;

class SidebarWorkCountsTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    private function application(string $status): Application
    {
        return Application::create(['reference_no' => 'APP-TEST-'.uniqid(), 'program_id' => \App\Models\Program::factory()->create()->id,
            'academic_status' => 'new_student', 'first_name' => 'Test', 'last_name' => 'Applicant', 'gender' => 'female',
            'birthdate' => '2005-01-01', 'email' => uniqid().'@example.test', 'status' => $status]);
    }

    private function document($user, string $status, string $payment): DocumentRequest
    {
        return DocumentRequest::create(['user_id' => $user->id, 'document_type' => 'tor', 'copies' => 1, 'fee' => 50, 'status' => $status, 'payment_status' => $payment, 'payment_method' => 'walk_in', 'is_paid' => $payment === 'verified']);
    }

    public function test_queue_counts_are_role_scoped_and_do_not_duplicate_payment_attempts(): void
    {
        $student = $this->makeUserWithRole('student');
        $this->application('submitted');
        $this->application('approved');
        Enrollment::factory()->pending()->create();
        $unpaid = Enrollment::factory()->create(['status' => 'approved', 'is_paid' => false]);
        Payment::factory()->rejected()->create(['enrollment_id' => $unpaid->id, 'created_at' => now()->subDay()]);
        Payment::factory()->create(['enrollment_id' => $unpaid->id, 'status' => 'pending']);
        Enrollment::factory()->paid()->create();
        Enrollment::factory()->create(['status' => 'rejected', 'is_paid' => false]);
        Appointment::factory()->create(['status' => 'pending']);
        Appointment::factory()->create(['status' => 'confirmed']);
        $this->document($student, 'submitted', 'unpaid');
        $this->document($student, 'ready', 'pending_verification');
        $this->document($student, 'processing', 'verified');
        $this->document($student, 'cancelled', 'unpaid');
        $this->document($student, 'released', 'verified');
        $registrar = ['registrar.applications.index' => 1, 'registrar.enrollments.index' => 1, 'registrar.appointments.index' => 1, 'registrar.documents.index' => 1];
        $cashier = ['cashier.payments.index' => 1, 'cashier.document-payments.index' => 2];
        foreach (['admin' => array_merge($registrar, $cashier), 'registrar' => $registrar, 'cashier' => $cashier, 'student' => [], 'alumni' => [], 'new_applicant' => []] as $role => $expected) {
            $response = $this->actingAs($this->makeUserWithRole($role))->getJson(route('notifications.poll'))->assertOk();
            $this->assertEquals($expected, $response->json('work_counts'));
        }
    }

    public function test_badges_render_only_on_authorized_menus_and_hide_zero(): void
    {
        $registrar = $this->makeUserWithRole('registrar');
        $response = $this->actingAs($registrar)->get(route('notifications.index'))->assertOk();
        $response->assertSee('data-work-count="registrar.applications.index"', false)->assertDontSee('data-work-count="cashier.payments.index"', false);
        $this->assertMatchesRegularExpression('/data-work-count="registrar.applications.index"[^>]*hidden/', $response->getContent());
        app()->forgetInstance(\JeroenNoten\LaravelAdminLte\AdminLte::class);
        $this->actingAs($this->makeUserWithRole('student'))->get(route('notifications.index'))->assertOk()->assertDontSee('data-work-count=', false);
    }

    public function test_workflow_transitions_update_counts_independently_of_reading_notifications(): void
    {
        $staff = $this->makeUserWithRole('admin');
        $application = $this->application('submitted');
        $enrollment = Enrollment::factory()->pending()->create();
        $appointment = Appointment::factory()->create(['status' => 'pending']);
        $document = $this->document($enrollment->user, 'submitted', 'unpaid');
        UserNotification::create(['user_id' => $staff->id, 'title' => 'New work', 'message' => 'Review', 'type' => 'info']);
        $this->actingAs($staff)->postJson(route('notifications.markAllRead'))->assertOk();
        // Route-name keys contain dots, so inspect the returned map directly.
        $counts = $this->getJson(route('notifications.poll'))->json('work_counts');
        $this->assertSame(1, $counts['registrar.applications.index']);
        $application->update(['status' => 'approved']);
        $enrollment->update(['status' => 'approved']);
        $appointment->update(['status' => 'cancelled']);
        $document->update(['status' => 'processing']);
        $counts = $this->getJson(route('notifications.poll'))->json('work_counts');
        $this->assertSame(0, $counts['registrar.applications.index']);
        $this->assertSame(0, $counts['registrar.enrollments.index']);
        $this->assertSame(0, $counts['registrar.appointments.index']);
        $this->assertSame(0, $counts['registrar.documents.index']);
        $this->assertSame(1, $counts['cashier.payments.index']);
        $enrollment->update(['is_paid' => true]);
        $document->update(['payment_status' => 'verified', 'is_paid' => true]);
        $counts = $this->getJson(route('notifications.poll'))->json('work_counts');
        $this->assertSame(0, $counts['cashier.payments.index']);
        $this->assertSame(0, $counts['cashier.document-payments.index']);
    }
}
