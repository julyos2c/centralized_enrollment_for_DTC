<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AppointmentSlot;
use App\Models\DocumentRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\SeedsRoles;
use Tests\TestCase;

class UpcomingAppointmentDashboardTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        config(['app.timezone' => 'Asia/Manila']);
        $this->travelTo(Carbon::parse('2026-10-26 10:00:00', 'Asia/Manila'));
    }

    public function test_student_dashboard_selects_the_nearest_confirmed_appointment_by_slot_time(): void
    {
        $student = $this->makeUserWithRole('student');

        $this->makeAppointment($student->id, '2026-10-27', '14:00:00', 'diploma');
        $this->makeAppointment($student->id, '2026-10-26', '11:00:00', 'tor');

        $response = $this->actingAs($student)->get(route('portal.dashboard'));

        $response->assertOk()
            ->assertSee('Transcript of Records')
            ->assertDontSee('Diploma');
    }

    public function test_appointment_that_started_earlier_today_is_not_upcoming_but_a_later_one_is(): void
    {
        $student = $this->makeUserWithRole('student');

        $pastAppointment = $this->makeAppointment($student->id, '2026-10-26', '09:59:59', 'diploma');
        $this->makeAppointment($student->id, '2026-10-26', '10:00:00', 'tor');
        $this->makeAppointment($student->id, '2026-10-26', '10:00:01', 'certification');

        $this->actingAs($student)->get(route('portal.dashboard'))
            ->assertOk()
            ->assertSee('Certification')
            ->assertDontSee('Diploma')
            ->assertDontSee('Transcript of Records');

        $this->assertDatabaseHas('appointments', [
            'id' => $pastAppointment->id,
            'status' => 'confirmed',
        ]);
    }

    public function test_future_date_appointment_is_upcoming_when_no_appointment_remains_today(): void
    {
        $student = $this->makeUserWithRole('student');
        $this->makeAppointment($student->id, '2026-10-27', '08:00:00', 'diploma');

        $this->actingAs($student)->get(route('portal.dashboard'))
            ->assertOk()
            ->assertSee('Upcoming Appointment')
            ->assertSee('Diploma');
    }

    public function test_appointment_with_a_missing_slot_is_excluded_safely(): void
    {
        $student = $this->makeUserWithRole('student');
        $appointment = $this->makeAppointment($student->id, '2026-10-27', '08:00:00', 'diploma');

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        try {
            AppointmentSlot::whereKey($appointment->appointment_slot_id)->delete();
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        $this->actingAs($student)->get(route('portal.dashboard'))
            ->assertOk()
            ->assertDontSee('Upcoming Appointment')
            ->assertDontSee('Diploma');
    }

    public function test_bundle_shows_all_unique_linked_document_names(): void
    {
        $student = $this->makeUserWithRole('student');
        $appointment = $this->makeAppointment($student->id, '2026-10-27', '08:00:00', 'tor');

        foreach (['tor', 'diploma', 'certification'] as $documentType) {
            DocumentRequest::create([
                'user_id' => $student->id,
                'appointment_id' => $appointment->id,
                'document_type' => $documentType,
            ]);
        }

        $this->actingAs($student)->get(route('portal.dashboard'))
            ->assertOk()
            ->assertSee('Transcript of Records, Diploma, Certification');
    }

    public function test_legacy_single_document_appointment_keeps_a_human_readable_label(): void
    {
        $student = $this->makeUserWithRole('student');
        $this->makeAppointment($student->id, '2026-10-27', '08:00:00', 'transcript');

        $this->actingAs($student)->get(route('portal.dashboard'))
            ->assertOk()
            ->assertSee('Transcript of Records');
    }

    public function test_pending_cancelled_and_completed_appointments_are_not_upcoming(): void
    {
        $student = $this->makeUserWithRole('student');
        $this->makeAppointment($student->id, '2026-10-27', '08:00:00', 'tor', 'pending');
        $this->makeAppointment($student->id, '2026-10-27', '09:00:00', 'diploma', 'cancelled');
        $this->makeAppointment($student->id, '2026-10-27', '10:00:00', 'certification', 'completed');

        $this->actingAs($student)->get(route('portal.dashboard'))
            ->assertOk()
            ->assertDontSee('Upcoming Appointment')
            ->assertDontSee('Transcript of Records')
            ->assertDontSee('Diploma')
            ->assertDontSee('Certification');
    }

    public function test_student_and_alumni_dashboards_only_show_their_own_appointments(): void
    {
        $student = $this->makeUserWithRole('student');
        $alumni = $this->makeUserWithRole('alumni');
        $this->makeAppointment($student->id, '2026-10-27', '08:00:00', 'tor');
        $this->makeAppointment($alumni->id, '2026-10-27', '09:00:00', 'diploma');

        $this->actingAs($student)->get(route('portal.dashboard'))
            ->assertOk()
            ->assertSee('Transcript of Records')
            ->assertDontSee('Diploma');

        $this->actingAs($alumni)->get(route('portal.dashboard'))
            ->assertOk()
            ->assertSee('Diploma')
            ->assertDontSee('Transcript of Records');
    }

    private function makeAppointment(
        int $userId,
        string $date,
        string $startTime,
        string $documentType,
        string $status = 'confirmed'
    ): Appointment {
        $slot = AppointmentSlot::create([
            'date' => $date,
            'start_time' => $startTime,
            'end_time' => '17:00:00',
            'max_bookings' => 5,
        ]);

        return Appointment::factory()->create([
            'user_id' => $userId,
            'appointment_slot_id' => $slot->id,
            'document_type' => $documentType,
            'status' => $status,
        ]);
    }
}
