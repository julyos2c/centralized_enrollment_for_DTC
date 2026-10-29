<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\DocumentRequest;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\SeedsRoles;
use Tests\TestCase;

class AppointmentEditingSafetyTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    private function booking(string $status = 'unpaid'): array
    {
        $user = $this->makeUserWithRole('student');
        $appointment = Appointment::factory()->create(['user_id' => $user->id, 'status' => 'pending', 'document_type' => 'transcript']);
        $document = DocumentRequest::create([
            'user_id' => $user->id, 'appointment_id' => $appointment->id, 'document_type' => 'tor',
            'copies' => 2, 'fee' => 80, 'status' => 'submitted', 'is_paid' => $status === 'verified',
            'payment_method' => 'gcash_qr', 'payment_status' => $status,
        ]);
        $this->actingAs($user);
        return [$appointment, $document];
    }

    private function payload(Appointment $appointment, string $type = 'transcript'): array
    {
        return ['appointment_slot_id' => $appointment->appointment_slot_id, 'document_type' => $type, 'purpose' => 'Updated purpose'];
    }

    public function test_purpose_changes_preserve_original_fee_for_every_payment_state(): void
    {
        Setting::set('document_fee_tor', 999);
        foreach (['unpaid', 'pending_verification', 'verified', 'rejected'] as $status) {
            [$appointment, $document] = $this->booking($status);
            $this->put(route('portal.appointments.update', $appointment), $this->payload($appointment))->assertSessionHasNoErrors();
            $this->assertSame('80.00', $document->fresh()->fee);
            $this->assertSame('Updated purpose', $document->fresh()->purpose);
        }
    }

    public function test_document_type_change_reprices_only_unpaid_or_rejected_requests_and_keeps_copies(): void
    {
        Setting::set('document_fee_diploma', 120);
        foreach (['unpaid', 'rejected', 'pending_verification', 'verified'] as $status) {
            [$appointment, $document] = $this->booking($status);
            $response = $this->put(route('portal.appointments.update', $appointment), $this->payload($appointment, 'diploma'));
            if (in_array($status, ['unpaid', 'rejected'])) {
                $response->assertSessionHasNoErrors();
                $this->assertSame('240.00', $document->fresh()->fee);
            } else {
                $response->assertSessionHasErrors('document_type');
                $this->assertSame('80.00', $document->fresh()->fee);
                $this->assertSame('transcript', $appointment->fresh()->document_type);
            }
        }
    }

    public function test_rescheduling_a_paid_booking_preserves_its_fee_and_payment(): void
    {
        [$appointment, $document] = $this->booking('verified');
        Setting::set('document_fee_tor', 999);
        $slot = \App\Models\AppointmentSlot::factory()->create(['date' => now()->addDays(10)->toDateString(), 'status' => 'open']);
        $payload = $this->payload($appointment);
        $payload['appointment_slot_id'] = $slot->id;
        $this->put(route('portal.appointments.update', $appointment), $payload)->assertSessionHasNoErrors();
        $this->assertSame($slot->id, $appointment->fresh()->appointment_slot_id);
        $this->assertSame('80.00', $document->fresh()->fee);
        $this->assertSame('verified', $document->fresh()->payment_status);
    }

    public function test_edit_dialog_shows_document_choices_only_when_editable(): void
    {
        [$appointment] = $this->booking('unpaid');
        $this->get(route('portal.appointments.index'))
            ->assertOk()
            ->assertSee('dr-edit-modal')
            ->assertSee('name="document_type"', false)
            ->assertSee('value="diploma"', false)
            ->assertSee('No other slots available');

        $appointment->documentRequests()->update(['payment_status' => 'pending_verification']);
        $this->get(route('portal.appointments.index'))
            ->assertOk()
            ->assertSee('To change these documents, contact the Registrar.')
            ->assertSee('Transcript of Records')
            ->assertDontSee('value="diploma"', false);
    }

    public function test_edit_rechecks_database_status_even_when_route_model_is_stale(): void
    {
        foreach (['confirmed', 'cancelled'] as $status) {
            [$appointment, $document] = $this->booking();
            Appointment::whereKey($appointment->id)->update(['status' => $status]);
            Route::bind('appointment', fn () => $appointment);
            $this->put(route('portal.appointments.update', $appointment), $this->payload($appointment))->assertSessionHasErrors('appointment');
            $this->assertSame($status, $appointment->fresh()->status);
            $this->assertNotSame('Updated purpose', $document->fresh()->purpose);
        }
    }

    public function test_passed_schedule_guidance_preserves_booking_documents_and_payment(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-10-28 09:00:00'));
        try {
            [$appointment, $document] = $this->booking('verified');
            $appointment->slot->update(['date' => '2026-10-28', 'start_time' => '08:00:00', 'end_time' => '08:30:00']);
            $this->assertTrue($appointment->fresh()->scheduleHasPassed());
            $this->get(route('portal.appointments.index'))->assertOk()
                ->assertSee('Schedule passed — choose a new time')
                ->assertSee('Edit booking');
            $this->assertSame('pending', $appointment->fresh()->status);
            $this->assertSame('verified', $document->fresh()->payment_status);
            $this->assertSame('80.00', $document->fresh()->fee);

            $appointment->update(['status' => 'confirmed']);
            $this->get(route('portal.appointments.index'))->assertOk()
                ->assertSee('Scheduled time passed — contact the Registrar')
                ->assertSee('dr-registrar-contact', false)
                ->assertSee('Need to reschedule or cancel?')
                ->assertSee('Contact Registrar')
                ->assertDontSee('Contact the Registrar to change or cancel this confirmed booking.')
                ->assertDontSee('data-target="#editModal'.$appointment->id.'"', false);

            $appointment->update(['status' => 'cancelled']);
            $this->assertFalse($appointment->fresh()->scheduleHasPassed());
        } finally {
            $this->travelBack();
        }
    }

    public function test_schedule_passes_at_end_time_not_start_time(): void
    {
        [$appointment] = $this->booking();
        $appointment->slot->update(['date' => '2026-10-28', 'start_time' => '08:00:00', 'end_time' => '08:30:00']);
        try {
            $this->travelTo(\Carbon\Carbon::parse('2026-10-28 08:15:00'));
            $this->assertFalse($appointment->fresh()->scheduleHasPassed());
            $this->travelTo(\Carbon\Carbon::parse('2026-10-28 08:30:00'));
            $this->assertTrue($appointment->fresh()->scheduleHasPassed());
        } finally {
            $this->travelBack();
        }
    }

    public function test_payment_action_is_only_shown_when_payment_needs_attention(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-10-28 09:00:00'));
        try {
            [$overdue] = $this->booking('unpaid');
            $overdue->update(['status' => 'confirmed']);
            $overdue->slot->update(['date' => '2026-10-27', 'start_time' => '08:00:00', 'end_time' => '08:30:00']);
            $this->get(route('portal.appointments.index'))->assertOk()
                ->assertSee('Review payment')
                ->assertDontSee('>Payment details</a>', false);

            [$cancelled, $cancelledDocument] = $this->booking('unpaid');
            $cancelled->update(['status' => 'cancelled']);
            $this->get(route('portal.appointments.index'))->assertOk()
                ->assertSee('Review payment');

            $cancelledDocument->update(['fee' => 0]);
            $this->get(route('portal.appointments.index'))->assertOk()
                ->assertSee('No Payment Required')
                ->assertDontSee('>Review payment</a>', false)
                ->assertDontSee('>Payment details</a>', false);

            [$paidCancelled] = $this->booking('verified');
            $paidCancelled->update(['status' => 'cancelled']);
            $this->get(route('portal.appointments.index'))->assertOk()
                ->assertDontSee('>Review payment</a>', false)
                ->assertDontSee('>Payment details</a>', false);
        } finally {
            $this->travelBack();
        }
    }

    public function test_registrar_contact_uses_configured_details_and_falls_back_without_email(): void
    {
        [$appointment] = $this->booking();
        $this->get(route('portal.appointments.index'))->assertOk()
            ->assertDontSee('data-booking-reference', false);

        $appointment->update(['status' => 'confirmed']);
        $this->get(route('portal.appointments.index'))->assertOk()
            ->assertSee('Contact Registrar')
            ->assertSee('Need to change or cancel?')
            ->assertSee('dr-registrar-contact', false)
            ->assertDontSee('Appointment confirmed')
            ->assertSee('Visit the Registrar’s Office to arrange another pickup time.')
            ->assertDontSee('data-contact-email', false);

        Setting::set('registrar_email', 'registrar@example.test');
        Setting::set('registrar_office', '<script>alert(1)</script>');
        $this->get(route('portal.appointments.index'))->assertOk()
            ->assertSee('registrar@example.test')
            ->assertSee('data-booking-reference="'.$appointment->id.'"', false)
            ->assertSee('data-contact-email', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_cancellation_explains_payment_follow_up_and_preserves_payment_records(): void
    {
        foreach (['verified', 'pending_verification'] as $status) {
            [$appointment, $document] = $this->booking($status);
            $response = $this->post(route('portal.appointments.cancel', $appointment))->assertSessionHasNoErrors();
            $message = $response->getSession()->get('success');
            $this->assertStringContainsString('Cashier', $message);
            $this->assertSame($status, $document->fresh()->payment_status);
            $this->assertSame('cancelled', $document->fresh()->status);
        }
    }
}
