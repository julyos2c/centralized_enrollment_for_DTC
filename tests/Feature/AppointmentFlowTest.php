<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AppointmentPayment;
use App\Models\AppointmentSlot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\SeedsRoles;
use Tests\TestCase;

class AppointmentFlowTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        Storage::fake('local');
    }

    // ─────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────

    private function makeSlot(array $attrs = []): AppointmentSlot
    {
        return AppointmentSlot::factory()->create($attrs);
    }

    private function fakeProof(): UploadedFile
    {
        return UploadedFile::fake()->image('proof.jpg', 200, 200);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Student — Booking
    // ─────────────────────────────────────────────────────────────────────

    /** @test */
    public function student_can_book_a_walk_in_appointment()
    {
        $student = $this->makeUserWithRole('student');
        $slot    = $this->makeSlot();

        $response = $this->actingAs($student)->post(route('portal.appointments.store'), [
            'appointment_slot_id' => $slot->id,
            'document_type'       => 'transcript',
            'purpose'             => 'Employment application',
            'payment_method'      => 'walk_in',
        ]);

        $response->assertRedirect(route('portal.appointments.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('appointments', [
            'user_id'             => $student->id,
            'appointment_slot_id' => $slot->id,
            'document_type'       => 'transcript',
            'status'              => 'pending',
        ]);
    }

    /** @test */
    public function student_can_book_with_gcash_and_submit_proof()
    {
        $student = $this->makeUserWithRole('student');
        $slot    = $this->makeSlot();

        $response = $this->actingAs($student)->post(route('portal.appointments.store'), [
            'appointment_slot_id' => $slot->id,
            'document_type'       => 'diploma',
            'purpose'             => 'For employment application',
            'payment_method'      => 'gcash',
            'reference_number'    => '1234567890',
            'proof_of_payment'    => $this->fakeProof(),
        ]);

        $response->assertRedirect(route('portal.appointments.index'));

        $appointment = Appointment::where('user_id', $student->id)->first();
        $this->assertNotNull($appointment);

        $payment = $appointment->payments()->first();
        $this->assertNotNull($payment);
        $this->assertSame('gcash', $payment->payment_method);
        $this->assertSame('1234567890', $payment->reference_number);
        $this->assertSame('pending', $payment->status);
        Storage::disk('local')->assertExists($payment->proof_of_payment);
    }

    /** @test */
    public function gcash_booking_requires_reference_number_and_proof()
    {
        $student = $this->makeUserWithRole('student');
        $slot    = $this->makeSlot();

        $this->actingAs($student)->post(route('portal.appointments.store'), [
            'appointment_slot_id' => $slot->id,
            'document_type'       => 'transcript',
            'payment_method'      => 'gcash',
            // missing reference_number and proof_of_payment
        ])->assertSessionHasErrors(['reference_number', 'proof_of_payment']);

        $this->assertDatabaseMissing('appointments', ['user_id' => $student->id]);
    }

    /** @test */
    public function student_cannot_book_a_full_slot()
    {
        $student = $this->makeUserWithRole('student');
        $slot    = $this->makeSlot(['max_bookings' => 1]);

        // Fill the slot with another student's booking
        Appointment::factory()->create([
            'appointment_slot_id' => $slot->id,
            'status'              => 'pending',
        ]);

        $response = $this->actingAs($student)->post(route('portal.appointments.store'), [
            'appointment_slot_id' => $slot->id,
            'document_type'       => 'transcript',
            'payment_method'      => 'walk_in',
        ]);

        $response->assertSessionHasErrors('appointment_slot_id');
        $this->assertDatabaseMissing('appointments', ['user_id' => $student->id]);
    }

    /** @test */
    public function student_cannot_double_book_the_same_slot()
    {
        $student = $this->makeUserWithRole('student');
        $slot    = $this->makeSlot(['max_bookings' => 5]);

        Appointment::factory()->create([
            'user_id'             => $student->id,
            'appointment_slot_id' => $slot->id,
            'status'              => 'pending',
        ]);

        $response = $this->actingAs($student)->post(route('portal.appointments.store'), [
            'appointment_slot_id' => $slot->id,
            'document_type'       => 'transcript',
            'payment_method'      => 'walk_in',
        ]);

        $response->assertSessionHasErrors('appointment_slot_id');
        $this->assertSame(1, Appointment::where('user_id', $student->id)->count());
    }

    /** @test */
    public function document_type_must_be_a_valid_option()
    {
        $student = $this->makeUserWithRole('student');
        $slot    = $this->makeSlot();

        $this->actingAs($student)->post(route('portal.appointments.store'), [
            'appointment_slot_id' => $slot->id,
            'document_type'       => 'fake_document',
            'payment_method'      => 'walk_in',
        ])->assertSessionHasErrors('document_type');
    }

    // ─────────────────────────────────────────────────────────────────────
    // Student — GCash Post-Booking Submission
    // ─────────────────────────────────────────────────────────────────────

    /** @test */
    public function student_can_submit_gcash_proof_after_booking()
    {
        $student     = $this->makeUserWithRole('student');
        $appointment = Appointment::factory()->create(['user_id' => $student->id]);

        $response = $this->actingAs($student)
            ->post(route('portal.appointments.payment.gcash', $appointment), [
                'reference_number' => '9876543210',
                'proof_of_payment' => $this->fakeProof(),
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $payment = $appointment->payments()->first();
        $this->assertNotNull($payment);
        $this->assertSame('gcash', $payment->payment_method);
        $this->assertSame('9876543210', $payment->reference_number);
        $this->assertSame('pending', $payment->status);
    }

    /** @test */
    public function student_cannot_submit_gcash_while_one_is_pending()
    {
        $student     = $this->makeUserWithRole('student');
        $appointment = Appointment::factory()->create(['user_id' => $student->id]);

        AppointmentPayment::factory()->create([
            'appointment_id' => $appointment->id,
            'status'         => 'pending',
        ]);

        $response = $this->actingAs($student)
            ->post(route('portal.appointments.payment.gcash', $appointment), [
                'reference_number' => '1111111111',
                'proof_of_payment' => $this->fakeProof(),
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertSame(1, $appointment->payments()->count());
    }

    /** @test */
    public function student_cannot_submit_gcash_for_another_students_appointment()
    {
        $student     = $this->makeUserWithRole('student');
        $other       = $this->makeUserWithRole('student');
        $appointment = Appointment::factory()->create(['user_id' => $other->id]);

        $this->actingAs($student)
            ->post(route('portal.appointments.payment.gcash', $appointment), [
                'reference_number' => '9999999999',
                'proof_of_payment' => $this->fakeProof(),
            ])->assertForbidden();
    }

    // ─────────────────────────────────────────────────────────────────────
    // Student — Cancellation
    // ─────────────────────────────────────────────────────────────────────

    /** @test */
    public function student_can_cancel_their_own_pending_appointment()
    {
        $student     = $this->makeUserWithRole('student');
        $appointment = Appointment::factory()->create([
            'user_id' => $student->id,
            'status'  => 'pending',
        ]);

        $this->actingAs($student)
            ->post(route('portal.appointments.cancel', $appointment))
            ->assertRedirect(route('portal.appointments.index'));

        $this->assertSame('cancelled', $appointment->fresh()->status);
    }

    /** @test */
    public function student_cannot_cancel_a_confirmed_appointment()
    {
        $student     = $this->makeUserWithRole('student');
        $appointment = Appointment::factory()->confirmed()->create(['user_id' => $student->id]);

        $this->actingAs($student)
            ->post(route('portal.appointments.cancel', $appointment))
            ->assertSessionHasErrors('error');

        $this->assertSame('confirmed', $appointment->fresh()->status);
    }

    /** @test */
    public function student_cannot_cancel_another_students_appointment()
    {
        $student     = $this->makeUserWithRole('student');
        $other       = $this->makeUserWithRole('student');
        $appointment = Appointment::factory()->create(['user_id' => $other->id]);

        $this->actingAs($student)
            ->post(route('portal.appointments.cancel', $appointment))
            ->assertForbidden();

        $this->assertSame('pending', $appointment->fresh()->status);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Registrar — Confirm & Cancel
    // ─────────────────────────────────────────────────────────────────────

    /** @test */
    public function registrar_can_confirm_a_pending_appointment()
    {
        $registrar   = $this->makeUserWithRole('registrar');
        $appointment = Appointment::factory()->create(['status' => 'pending']);

        $response = $this->actingAs($registrar)
            ->post(route('registrar.appointments.confirm', $appointment));

        $response->assertRedirect(route('registrar.appointments.index'));
        $this->assertSame('confirmed', $appointment->fresh()->status);
    }

    /** @test */
    public function registrar_can_cancel_an_appointment_with_a_reason()
    {
        $registrar   = $this->makeUserWithRole('registrar');
        $appointment = Appointment::factory()->create(['status' => 'pending']);

        $response = $this->actingAs($registrar)
            ->post(route('registrar.appointments.cancel', $appointment), [
                'remarks' => 'Slot is no longer available.',
            ]);

        $response->assertRedirect(route('registrar.appointments.index'));
        $appointment->refresh();
        $this->assertSame('cancelled', $appointment->status);
        $this->assertSame('Slot is no longer available.', $appointment->remarks);
    }

    /** @test */
    public function cancelling_an_appointment_requires_a_reason()
    {
        $registrar   = $this->makeUserWithRole('registrar');
        $appointment = Appointment::factory()->create(['status' => 'pending']);

        $this->actingAs($registrar)
            ->post(route('registrar.appointments.cancel', $appointment), ['remarks' => ''])
            ->assertSessionHasErrors('remarks');

        $this->assertSame('pending', $appointment->fresh()->status);
    }

    /** @test */
    public function a_student_cannot_access_registrar_appointment_actions()
    {
        $student     = $this->makeUserWithRole('student');
        $appointment = Appointment::factory()->create();

        $this->actingAs($student)
            ->post(route('registrar.appointments.confirm', $appointment))
            ->assertForbidden();

        $this->actingAs($student)
            ->post(route('registrar.appointments.cancel', $appointment), ['remarks' => 'hack'])
            ->assertForbidden();
    }

    // ─────────────────────────────────────────────────────────────────────
    // Registrar — GCash Payment Verification
    // ─────────────────────────────────────────────────────────────────────

    /** @test */
    public function registrar_can_verify_a_pending_gcash_payment()
    {
        $registrar   = $this->makeUserWithRole('registrar');
        $payment     = AppointmentPayment::factory()->create(['status' => 'pending']);

        $response = $this->actingAs($registrar)
            ->post(route('registrar.appointments.payments.verify', $payment));

        $response->assertRedirect(route('registrar.appointments.index'));

        $payment->refresh();
        $this->assertSame('verified', $payment->status);
        $this->assertSame($registrar->id, $payment->verified_by);
        $this->assertNotNull($payment->verified_at);
    }

    /** @test */
    public function registrar_cannot_verify_pending_gcash_for_a_cancelled_appointment()
    {
        $registrar = $this->makeUserWithRole('registrar');
        $appointment = Appointment::factory()->create(['status' => 'cancelled']);
        $payment = AppointmentPayment::factory()->create([
            'appointment_id' => $appointment->id,
            'payment_method' => 'gcash',
            'status' => 'pending',
        ]);

        $this->actingAs($registrar)
            ->post(route('registrar.appointments.payments.verify', $payment))
            ->assertStatus(422);

        $this->assertSame('pending', $payment->fresh()->status);
        $this->assertSame('cancelled', $appointment->fresh()->status);
        $this->assertSame(1, AppointmentPayment::where('appointment_id', $appointment->id)->count());
    }

    /** @test */
    public function registrar_cannot_verify_a_second_payment_for_an_appointment()
    {
        $registrar = $this->makeUserWithRole('registrar');
        $appointment = Appointment::factory()->create(['status' => 'pending']);
        AppointmentPayment::factory()->verified()->create(['appointment_id' => $appointment->id]);
        $pending = AppointmentPayment::factory()->create([
            'appointment_id' => $appointment->id,
            'payment_method' => 'gcash',
            'status' => 'pending',
        ]);

        $this->actingAs($registrar)
            ->post(route('registrar.appointments.payments.verify', $pending))
            ->assertStatus(422);

        $this->assertSame('pending', $pending->fresh()->status);
        $this->assertSame(1, AppointmentPayment::where('appointment_id', $appointment->id)->where('status', 'verified')->count());
    }

    /** @test */
    public function registrar_can_reject_a_pending_gcash_payment_with_a_reason()
    {
        $registrar = $this->makeUserWithRole('registrar');
        $payment   = AppointmentPayment::factory()->create(['status' => 'pending']);

        $response = $this->actingAs($registrar)
            ->post(route('registrar.appointments.payments.reject', $payment), [
                'rejection_reason' => 'Reference number not found in GCash records.',
            ]);

        $response->assertRedirect(route('registrar.appointments.index'));

        $payment->refresh();
        $this->assertSame('rejected', $payment->status);
        $this->assertSame('Reference number not found in GCash records.', $payment->rejection_reason);
        $this->assertSame($registrar->id, $payment->verified_by);
    }

    /** @test */
    public function rejecting_a_gcash_payment_requires_a_reason()
    {
        $registrar = $this->makeUserWithRole('registrar');
        $payment   = AppointmentPayment::factory()->create(['status' => 'pending']);

        $this->actingAs($registrar)
            ->post(route('registrar.appointments.payments.reject', $payment), [
                'rejection_reason' => '',
            ])->assertSessionHasErrors('rejection_reason');

        $this->assertSame('pending', $payment->fresh()->status);
    }

    /** @test */
    public function cannot_verify_an_already_verified_payment()
    {
        $registrar = $this->makeUserWithRole('registrar');
        $payment   = AppointmentPayment::factory()->verified()->create();

        $this->actingAs($registrar)
            ->post(route('registrar.appointments.payments.verify', $payment))
            ->assertStatus(422);
    }

    /** @test */
    public function cannot_reject_an_already_rejected_payment()
    {
        $registrar = $this->makeUserWithRole('registrar');
        $payment   = AppointmentPayment::factory()->rejected()->create();

        $this->actingAs($registrar)
            ->post(route('registrar.appointments.payments.reject', $payment), [
                'rejection_reason' => 'Again.',
            ])->assertStatus(422);
    }

    /** @test */
    public function a_student_cannot_verify_or_reject_payments()
    {
        $student = $this->makeUserWithRole('student');
        $payment = AppointmentPayment::factory()->create(['status' => 'pending']);

        $this->actingAs($student)
            ->post(route('registrar.appointments.payments.verify', $payment))
            ->assertForbidden();

        $this->actingAs($student)
            ->post(route('registrar.appointments.payments.reject', $payment), [
                'rejection_reason' => 'Hack.',
            ])->assertForbidden();

        $this->assertSame('pending', $payment->fresh()->status);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Registrar — Index view
    // ─────────────────────────────────────────────────────────────────────

    /** @test */
    public function registrar_index_shows_pending_appointments_by_default()
    {
        $registrar   = $this->makeUserWithRole('registrar');
        $pending     = Appointment::factory()->create(['status' => 'pending']);
        $confirmed   = Appointment::factory()->confirmed()->create();

        $response = $this->actingAs($registrar)
            ->get(route('registrar.appointments.index'));

        $response->assertOk();
        $response->assertSee($pending->user->name);
        $response->assertDontSee($confirmed->user->name);
    }

    /** @test */
    public function registrar_index_shows_pending_gcash_payments_requiring_verification()
    {
        $registrar   = $this->makeUserWithRole('registrar');
        $payment     = AppointmentPayment::factory()->create(['status' => 'pending']);

        $response = $this->actingAs($registrar)
            ->get(route('registrar.appointments.index'));

        $response->assertOk();
        $response->assertSee($payment->reference_number);
    }

    /** @test */
    public function registrar_index_can_filter_by_confirmed_status()
    {
        $registrar  = $this->makeUserWithRole('registrar');
        $pending    = Appointment::factory()->create(['status' => 'pending']);
        $confirmed  = Appointment::factory()->confirmed()->create();

        $response = $this->actingAs($registrar)
            ->get(route('registrar.appointments.index', ['status' => 'confirmed']));

        $response->assertOk();
        $response->assertSee($confirmed->user->name);
        $response->assertDontSee($pending->user->name);
    }
}
