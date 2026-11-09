<?php

namespace Tests\Feature;

use App\Mail\AdmissionUpdate;
use App\Models\Application;
use App\Models\EntranceExamBooking;
use App\Models\EntranceExamSession;
use App\Models\Program;
use App\Models\Setting;
use App\Services\BookingException;
use App\Services\EntranceExamBookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\SeedsRoles;
use Tests\TestCase;

class EntranceExamTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        Mail::fake();
    }

    public function test_exam_is_disabled_by_default_and_portal_hides_booking(): void
    {
        $application = $this->makeApplication();
        $session = $this->makeSession();
        $this->enterPortal($application);

        $this->get(route('public.admission.portal'))->assertOk()->assertDontSee('Entrance examination');
        $this->post(route('public.admission.exam.book'), ['session_id' => $session->id])
            ->assertSessionHas('error', 'Entrance examination booking is not open.');
        $this->assertSame(0, EntranceExamBooking::count());
    }

    public function test_applicant_books_a_session_and_receives_confirmation(): void
    {
        Setting::set('admission_exam_mode', 'optional');
        $application = $this->makeApplication();
        $session = $this->makeSession();
        $this->enterPortal($application);

        $this->get(route('public.admission.portal'))->assertSee('Book selected session')->assertSee($session->venue);
        $this->post(route('public.admission.exam.book'), ['session_id' => $session->id])->assertRedirect();

        $booking = EntranceExamBooking::firstOrFail();
        $this->assertSame('confirmed', $booking->status);
        $this->assertSame($application->id, $booking->active_application_id);
        Mail::assertSent(AdmissionUpdate::class, fn ($mail) => $mail->eventTitle === 'Entrance exam schedule confirmed');

        // A second booking is refused (double booking).
        $other = $this->makeSession(['exam_date' => today()->addDays(9)]);
        $this->post(route('public.admission.exam.book'), ['session_id' => $other->id])
            ->assertSessionHas('error', 'You already have an entrance exam booking.');
        $this->assertSame(1, EntranceExamBooking::count());
    }

    public function test_capacity_prevents_overbooking(): void
    {
        Setting::set('admission_exam_mode', 'optional');
        $session = $this->makeSession(['capacity' => 2]);
        $service = app(EntranceExamBookingService::class);

        $service->book($this->makeApplication(), $session->id);
        $service->book($this->makeApplication(), $session->id);

        $this->expectException(BookingException::class);
        $this->expectExceptionMessage('already full');
        $service->book($this->makeApplication(), $session->id);
    }

    public function test_unique_active_key_blocks_a_second_active_booking_even_without_checks(): void
    {
        $application = $this->makeApplication();
        $first = $this->makeSession();
        $second = $this->makeSession(['exam_date' => today()->addDays(10)]);
        EntranceExamBooking::create(['application_id' => $application->id, 'entrance_exam_session_id' => $first->id, 'status' => 'confirmed', 'active_application_id' => $application->id]);

        $this->expectException(BookingException::class);
        app(EntranceExamBookingService::class)->book($application, $second->id);
    }

    public function test_documents_must_be_approved_before_booking_when_required(): void
    {
        Setting::set('admission_exam_mode', 'required');
        \App\Models\AdmissionRequirement::where('code', 'form_138')->update(['is_required' => true]);
        $application = $this->makeApplication();

        $this->assertSame(
            'All required documents must be approved before you can book the entrance exam.',
            app(EntranceExamBookingService::class)->bookingBlocker($application)
        );

        Setting::set('admission_exam_requires_documents', '0');
        $this->assertNull(app(EntranceExamBookingService::class)->bookingBlocker($application));
    }

    public function test_approval_workflow_and_staff_actions(): void
    {
        Setting::set('admission_exam_mode', 'optional');
        Setting::set('admission_exam_booking_requires_approval', '1');
        $registrar = $this->makeUserWithRole('registrar');
        $application = $this->makeApplication();
        $session = $this->makeSession();
        $this->enterPortal($application);

        $this->post(route('public.admission.exam.book'), ['session_id' => $session->id]);
        $booking = EntranceExamBooking::firstOrFail();
        $this->assertSame('pending', $booking->status);

        $this->actingAs($registrar)->get(route('registrar.exams.show', $session))->assertOk()->assertSee($application->reference_no);
        $this->actingAs($registrar)->post(route('registrar.exams.bookings.approve', $booking))->assertRedirect();
        $this->assertSame('confirmed', $booking->fresh()->status);

        // Attendance cannot be recorded before the session starts.
        $this->actingAs($registrar)->post(route('registrar.exams.bookings.attendance', $booking), ['attendance' => 'attended'])
            ->assertSessionHas('error');

        $session->update(['exam_date' => today(), 'start_time' => now()->subHour()->format('H:i'), 'end_time' => '23:59']);
        $this->actingAs($registrar)->post(route('registrar.exams.bookings.attendance', $booking), ['attendance' => 'attended']);
        $this->assertSame('attended', $booking->fresh()->status);

        $this->actingAs($registrar)->post(route('registrar.exams.bookings.result', $booking), ['result' => 'passed', 'score' => '87.5']);
        $this->assertNull($booking->fresh()->result_released_at);

        // Unreleased results are not shown to the applicant.
        $this->post(route('logout'));
        $this->enterPortal($application);
        $this->get(route('public.admission.portal'))->assertDontSee('Score: 87.5');

        $this->actingAs($registrar)->post(route('registrar.exams.release-all', $session))->assertRedirect();
        $this->assertNotNull($booking->fresh()->result_released_at);
        Mail::assertSent(AdmissionUpdate::class, fn ($mail) => $mail->eventTitle === 'Entrance examination result available');
    }

    public function test_required_exam_gates_registrar_approval(): void
    {
        Setting::set('admission_exam_mode', 'required');
        $registrar = $this->makeUserWithRole('registrar');
        $application = $this->makeApplication(['status' => 'under_review']);

        $this->actingAs($registrar)->post(route('registrar.applications.approve', $application))
            ->assertSessionHas('error', 'The entrance exam is required: record a passing result before approving.');

        $session = $this->makeSession(['exam_date' => today()->subDay()]);
        EntranceExamBooking::create(['application_id' => $application->id, 'entrance_exam_session_id' => $session->id, 'status' => 'attended', 'active_application_id' => $application->id, 'result' => 'passed']);

        $this->actingAs($registrar)->post(route('registrar.applications.approve', $application));
        $this->assertSame('approved', $application->fresh()->status);
    }

    public function test_applicant_reschedule_and_cancel_respect_cutoff(): void
    {
        Setting::set('admission_exam_mode', 'optional');
        Setting::set('admission_exam_change_cutoff_hours', '24');
        $application = $this->makeApplication();
        $soon = $this->makeSession(['exam_date' => today()->addDay(), 'start_time' => now()->addHours(2)->format('H:i')]);
        $later = $this->makeSession(['exam_date' => today()->addDays(6)]);
        $service = app(EntranceExamBookingService::class);

        $booking = $service->book($application, $later->id);
        $this->enterPortal($application);
        $this->post(route('public.admission.exam.reschedule', $booking), ['session_id' => $soon->id])->assertRedirect();
        $moved = $application->activeExamBooking()->firstOrFail();
        $this->assertSame($soon->id, $moved->entrance_exam_session_id);
        $this->assertSame('rescheduled', $booking->fresh()->status);

        // Within the cutoff window the applicant can no longer cancel online.
        $soon->update(['exam_date' => today(), 'start_time' => now()->addHours(3)->format('H:i'), 'end_time' => '23:59']);
        $this->post(route('public.admission.exam.cancel', $moved))->assertSessionHas('error');
        $this->assertSame('confirmed', $moved->fresh()->status);
    }

    public function test_cancelling_a_session_frees_seats_and_notifies_applicants(): void
    {
        Setting::set('admission_exam_mode', 'optional');
        $registrar = $this->makeUserWithRole('registrar');
        $session = $this->makeSession();
        $application = $this->makeApplication();
        $booking = app(EntranceExamBookingService::class)->book($application, $session->id);

        $this->actingAs($registrar)->post(route('registrar.exams.status', $session), ['status' => 'cancelled', 'reason' => 'Typhoon'])->assertRedirect();

        $this->assertSame('cancelled', $booking->fresh()->status);
        $this->assertNull($booking->fresh()->active_application_id);
        Mail::assertSent(AdmissionUpdate::class, fn ($mail) => str_contains($mail->eventMessage, 'Typhoon'));
    }

    public function test_next_step_reflects_released_exam_result(): void
    {
        Setting::set('admission_exam_mode', 'required');
        $application = $this->makeApplication();
        $session = $this->makeSession(['exam_date' => today()->subDay()]);
        $booking = EntranceExamBooking::create(['application_id' => $application->id, 'entrance_exam_session_id' => $session->id, 'status' => 'attended', 'active_application_id' => $application->id, 'result' => 'passed', 'score' => 86]);
        $progress = app(\App\Services\AdmissionProgress::class);

        $this->assertSame('Wait for your exam result', $progress->build($application->fresh())['nextStep']['title']);

        $booking->update(['result_released_at' => now()]);
        $this->assertSame('Wait for the admission decision', $progress->build($application->fresh())['nextStep']['title']);
    }

    public function test_reminder_command_sends_once(): void
    {
        $application = $this->makeApplication();
        $session = $this->makeSession(['exam_date' => today()->addDay()]);
        EntranceExamBooking::create(['application_id' => $application->id, 'entrance_exam_session_id' => $session->id, 'status' => 'confirmed', 'active_application_id' => $application->id]);

        $this->artisan('admissions:send-exam-reminders')->assertSuccessful();
        $this->artisan('admissions:send-exam-reminders')->assertSuccessful();

        Mail::assertSent(AdmissionUpdate::class, 1);
    }

    public function test_session_management_and_access_control(): void
    {
        $registrar = $this->makeUserWithRole('registrar');
        $this->actingAs($registrar)->post(route('registrar.exams.store'), [
            'title' => 'CEE Batch 1', 'exam_date' => today()->addWeek()->toDateString(),
            'start_time' => '08:00', 'end_time' => '07:00', 'venue' => 'Gym', 'capacity' => 30,
        ])->assertSessionHasErrors('end_time');

        $this->actingAs($registrar)->post(route('registrar.exams.store'), [
            'title' => 'CEE Batch 1', 'exam_date' => today()->addWeek()->toDateString(),
            'start_time' => '08:00', 'end_time' => '11:00', 'venue' => 'Gym', 'capacity' => 30,
        ])->assertRedirect();
        $session = EntranceExamSession::firstOrFail();
        $this->actingAs($registrar)->get(route('registrar.exams.index'))->assertOk()->assertSee('CEE Batch 1');

        app(EntranceExamBookingService::class)->book($this->makeApplication(), $session->id);
        $this->actingAs($registrar)->put(route('registrar.exams.update', $session), [
            'title' => 'CEE Batch 1', 'exam_date' => $session->exam_date->toDateString(),
            'start_time' => '08:00', 'end_time' => '11:00', 'venue' => 'Gym', 'capacity' => 0,
        ])->assertSessionHasErrors('capacity');

        $this->actingAs($registrar)->put(route('registrar.exams.settings'), ['exam_mode' => 'required', 'change_cutoff_hours' => 12, 'booking_requires_approval' => '1'])->assertRedirect();
        $this->assertSame('required', Setting::get('admission_exam_mode'));
        $this->assertSame('1', Setting::get('admission_exam_booking_requires_approval'));
        $this->assertSame('0', Setting::get('admission_exam_requires_documents'));

        foreach (['cashier', 'student', 'alumni'] as $role) {
            $this->actingAs($this->makeUserWithRole($role))->get(route('registrar.exams.index'))->assertForbidden();
        }
    }

    public function test_admissions_dashboard_and_exports(): void
    {
        $registrar = $this->makeUserWithRole('registrar');
        $application = $this->makeApplication(['school_year' => '2026-2027', 'semester' => '1st', 'last_name' => '=HYPERLINK("x")']);
        $this->makeApplication(['school_year' => '2025-2026', 'status' => 'approved']);

        $this->actingAs($registrar)->get(route('registrar.admissions.dashboard', ['school_year' => '2026-2027']))
            ->assertOk()->assertSee('Admissions Overview')->assertViewHas('stats', fn ($stats) => $stats['total'] === 1 && $stats['pending'] === 1);

        $csv = $this->actingAs($registrar)->get(route('registrar.admissions.export.csv'))->assertOk()->streamedContent();
        $this->assertStringContainsString($application->reference_no, $csv);
        // Formula-like applicant text is neutralised (names are title-cased on save).
        $this->assertStringContainsStringIgnoringCase("'=HYPERLINK", $csv);

        $this->actingAs($registrar)->get(route('registrar.admissions.export.pdf'))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->actingAs($this->makeUserWithRole('cashier'))->get(route('registrar.admissions.dashboard'))->assertForbidden();
    }

    private function makeApplication(array $attributes = []): Application
    {
        return Application::create(array_merge([
            'reference_no' => 'APP-EXAM-' . strtoupper(uniqid()),
            'program_id' => Program::factory()->create()->id,
            'academic_status' => 'new_student',
            'first_name' => 'Exam', 'last_name' => 'Taker', 'gender' => 'male',
            'birthdate' => '2005-01-01',
            'email' => 'exam-' . uniqid() . '@example.test',
            'status' => 'submitted',
        ], $attributes));
    }

    private function makeSession(array $attributes = []): EntranceExamSession
    {
        return EntranceExamSession::create(array_merge([
            'title' => 'College Entrance Exam',
            'exam_date' => today()->addDays(7),
            'start_time' => '08:00',
            'end_time' => '11:00',
            'venue' => 'Room 201',
            'capacity' => 20,
            'status' => 'open',
        ], $attributes));
    }

    private function enterPortal(Application $application): void
    {
        $this->get(URL::temporarySignedRoute('public.admission.enter', now()->addHour(), ['application' => $application->id]));
    }
}
