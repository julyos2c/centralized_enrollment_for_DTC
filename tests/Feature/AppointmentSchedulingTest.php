<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AppointmentSlot;
use App\Models\OfficeClosure;
use App\Models\Setting;
use App\Services\AppointmentScheduleService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SeedsRoles;
use Tests\TestCase;

class AppointmentSchedulingTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        Carbon::setTestNow(Carbon::parse('2026-10-19 07:00:00', 'Asia/Manila'));
        Setting::set('appointment_schedule', json_encode([
            'weekdays' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
            'opens_at' => '08:00', 'closes_at' => '10:00', 'break_start' => null, 'break_end' => null,
            'duration' => 60, 'capacity' => 3, 'window' => 7,
        ]));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_generation_is_weekday_only_and_idempotent(): void
    {
        $service = app(AppointmentScheduleService::class);
        $this->assertSame(10, $service->generate());
        $this->assertSame(0, $service->generate());
        $this->assertSame(10, AppointmentSlot::where('source', 'automatic')->count());
        $this->assertSame(3, AppointmentSlot::first()->max_bookings);
        $this->assertSame('2026-10-19', AppointmentSlot::orderBy('date')->first()->date);
    }

    public function test_confirmed_office_closure_excludes_generated_slots_but_class_suspensions_are_not_consulted(): void
    {
        OfficeClosure::create(['start_date' => '2026-10-19', 'end_date' => '2026-10-19', 'title' => 'Local holiday', 'type' => 'local_holiday', 'is_confirmed' => true, 'is_active' => true]);
        app(AppointmentScheduleService::class)->generate();
        $this->assertSame(0, AppointmentSlot::whereDate('date', '2026-10-19')->count());
        $this->assertSame(8, AppointmentSlot::where('source', 'automatic')->count());
    }

    public function test_manual_slots_survive_generation_and_booked_slots_cannot_be_deleted(): void
    {
        $slot = AppointmentSlot::create(['date' => '2026-10-19', 'start_time' => '07:00:00', 'end_time' => '07:30:00', 'max_bookings' => 2, 'source' => 'manual', 'status' => 'open']);
        app(AppointmentScheduleService::class)->generate();
        $this->assertDatabaseHas('appointment_slots', ['id' => $slot->id, 'source' => 'manual']);

        $registrar = $this->makeUserWithRole('registrar');
        Appointment::factory()->create(['appointment_slot_id' => $slot->id]);
        $this->actingAs($registrar)->delete(route('registrar.appointments.slots.delete', $slot))->assertSessionHasErrors('slot');
        $this->assertDatabaseHas('appointment_slots', ['id' => $slot->id]);
    }

    public function test_class_suspension_data_does_not_control_appointments_and_break_time_is_not_generated(): void
    {
        Setting::set('class_suspension', '1');
        Setting::set('appointment_schedule', json_encode([
            'weekdays' => ['monday'], 'opens_at' => '08:00', 'closes_at' => '10:00',
            'break_start' => '08:45', 'break_end' => '09:15', 'duration' => 30, 'capacity' => 2, 'window' => 1,
        ]));
        app(AppointmentScheduleService::class)->generate();
        $this->assertSame(['08:00:00', '09:15:00'], AppointmentSlot::orderBy('start_time')->pluck('start_time')->all());
    }

    public function test_booking_endpoint_rejects_slot_inside_confirmed_office_closure(): void
    {
        $student = $this->makeUserWithRole('student');
        $slot = AppointmentSlot::factory()->create(['date' => '2026-10-19', 'start_time' => '08:00:00', 'end_time' => '09:00:00', 'max_bookings' => 5]);
        OfficeClosure::create(['start_date' => '2026-10-19', 'end_date' => '2026-10-19', 'title' => 'Office closure', 'type' => 'office_closure', 'is_confirmed' => true, 'is_active' => true]);

        $this->actingAs($student)->post(route('portal.appointments.store'), [
            'appointment_slot_id' => $slot->id,
            'document_type' => 'transcript',
            'purpose' => 'Employment',
            'payment_method' => 'walk_in',
        ])->assertSessionHasErrors('appointment_slot_id');
        $this->assertDatabaseMissing('appointments', ['appointment_slot_id' => $slot->id, 'user_id' => $student->id]);
    }

    public function test_registrar_can_open_schedule_and_slot_management_pages(): void
    {
        $registrar = $this->makeUserWithRole('registrar');
        $this->actingAs($registrar)->get(route('registrar.appointments.schedule'))->assertOk()->assertSee('Recurring schedule');
        $this->actingAs($registrar)->get(route('registrar.appointments.slots'))->assertOk()->assertSee('Manage Appointment Slots');
    }

    public function test_overlapping_slots_are_rejected_but_adjacent_slots_are_allowed(): void
    {
        $staff = $this->makeUserWithRole('registrar');
        AppointmentSlot::factory()->create(['date' => '2026-10-20', 'start_time' => '08:00:00', 'end_time' => '09:00:00']);
        $this->actingAs($staff)->post(route('registrar.appointments.slots.store'), ['date' => '2026-10-20', 'start_time' => '08:30', 'end_time' => '09:30', 'max_bookings' => 3])->assertSessionHasErrors('start_time');
        $this->actingAs($staff)->post(route('registrar.appointments.slots.store'), ['date' => '2026-10-20', 'start_time' => '09:00', 'end_time' => '10:00', 'max_bookings' => 3])->assertSessionHasNoErrors();
    }
}
