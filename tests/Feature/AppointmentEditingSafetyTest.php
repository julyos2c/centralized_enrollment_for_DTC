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
