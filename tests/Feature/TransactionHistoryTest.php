<?php

namespace Tests\Feature;

use App\Models\DocumentRequest;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\Appointment;
use App\Models\AppointmentPayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SeedsRoles;
use Tests\TestCase;

class TransactionHistoryTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_staff_history_combines_enrollment_and_document_payment_sources(): void
    {
        $cashier = $this->makeUserWithRole('cashier');
        $enrollment = Enrollment::factory()->create();
        $payment = Payment::factory()->create(['enrollment_id' => $enrollment->id, 'processed_by' => $cashier->id]);
        $documentOwner = $this->makeUserWithRole('alumni');
        $document = DocumentRequest::create([
            'user_id' => $documentOwner->id, 'document_type' => 'tor', 'copies' => 1,
            'status' => 'submitted', 'fee' => 100, 'is_paid' => false,
            'payment_method' => 'gcash_qr', 'payment_status' => 'pending_verification',
            'reference_number' => 'DOC-REF-123', 'payment_submitted_at' => now(),
        ]);
        $appointment = Appointment::factory()->create();
        $appointmentPayment = AppointmentPayment::factory()->create(['appointment_id' => $appointment->id]);

        $this->actingAs($cashier)->get(route('cashier.transactions.index'))
            ->assertOk()->assertSee('E-' . $payment->id)->assertSee('D-' . $document->id)->assertSee('A-' . $appointmentPayment->id);
        $this->get(route('cashier.transactions.show', 'D-' . $document->id))->assertOk()->assertSee('DOC-REF-123');
    }

    public function test_student_history_is_limited_to_owned_enrollment_transactions(): void
    {
        $student = $this->makeUserWithRole('student');
        $other = $this->makeUserWithRole('student');
        $mine = Enrollment::factory()->create(['user_id' => $student->id]);
        $theirs = Enrollment::factory()->create(['user_id' => $other->id]);
        $minePayment = Payment::factory()->create(['enrollment_id' => $mine->id]);
        $otherPayment = Payment::factory()->create(['enrollment_id' => $theirs->id]);

        $this->actingAs($student)->get(route('portal.transactions.index'))
            ->assertOk()->assertSee('E-' . $minePayment->id)->assertDontSee('E-' . $otherPayment->id);
        $this->get(route('portal.transactions.show', 'E-' . $otherPayment->id))->assertNotFound();
    }

    public function test_student_history_includes_own_document_and_appointment_payments_but_never_another_users_records(): void
    {
        $student = $this->makeUserWithRole('student');
        $other = $this->makeUserWithRole('student');
        $document = DocumentRequest::create(['user_id' => $student->id, 'document_type' => 'tor', 'status' => 'submitted', 'fee' => 50, 'is_paid' => false, 'payment_status' => 'pending_verification']);
        $foreignDocument = DocumentRequest::create(['user_id' => $other->id, 'document_type' => 'diploma', 'status' => 'submitted', 'fee' => 100, 'is_paid' => false, 'payment_status' => 'verified']);
        $appointment = Appointment::factory()->create(['user_id' => $student->id]);
        $appointmentPayment = AppointmentPayment::factory()->create(['appointment_id' => $appointment->id, 'status' => 'rejected']);
        $foreignAppointment = Appointment::factory()->create(['user_id' => $other->id]);
        $foreignAppointmentPayment = AppointmentPayment::factory()->create(['appointment_id' => $foreignAppointment->id]);

        $this->actingAs($student)->get(route('portal.transactions.index'))
            ->assertOk()->assertSee('D-' . $document->id)->assertSee('A-' . $appointmentPayment->id)
            ->assertDontSee('D-' . $foreignDocument->id)->assertDontSee('A-' . $foreignAppointmentPayment->id);
        $this->get(route('portal.transactions.show', 'D-' . $foreignDocument->id))->assertNotFound();
        $this->get(route('portal.transactions.show', 'A-' . $foreignAppointmentPayment->id))->assertNotFound();
        $this->get(route('portal.transactions.show', 'A-' . $appointmentPayment->id))->assertOk();
    }

    public function test_student_payment_type_and_status_filters_and_pagination_preserve_scope_and_filters(): void
    {
        $student = $this->makeUserWithRole('student');
        $appointment = Appointment::factory()->create(['user_id' => $student->id]);
        $rejected = AppointmentPayment::factory()->rejected()->create(['appointment_id' => $appointment->id]);
        $pendingAppointment = Appointment::factory()->create(['user_id' => $student->id]);
        $pending = AppointmentPayment::factory()->create(['appointment_id' => $pendingAppointment->id, 'status' => 'pending']);
        $secondPendingAppointment = Appointment::factory()->create(['user_id' => $student->id]);
        $secondPending = AppointmentPayment::factory()->create(['appointment_id' => $secondPendingAppointment->id, 'status' => 'pending']);
        $document = DocumentRequest::create(['user_id' => $student->id, 'document_type' => 'tor', 'status' => 'submitted', 'fee' => 50, 'is_paid' => false, 'payment_status' => 'rejected']);
        $otherAppointment = Appointment::factory()->create();
        $otherPayment = AppointmentPayment::factory()->create(['appointment_id' => $otherAppointment->id, 'status' => 'pending']);

        $this->actingAs($student)->get(route('portal.transactions.index', ['category' => 'appointment', 'status' => 'rejected']))
            ->assertOk()->assertSee('A-' . $rejected->id)->assertDontSee('D-' . $document->id)->assertDontSee('A-' . $pending->id);

        $response = $this->get(route('portal.transactions.index', ['category' => 'appointment', 'status' => 'pending', 'per_page' => 1]))->assertOk();
        $response->assertSee('category=appointment')->assertSee('status=pending')->assertSee('A-' . $secondPending->id)->assertDontSee('A-' . $pending->id)->assertDontSee('A-' . $otherPayment->id);
    }

    public function test_alumni_can_only_open_owned_document_transactions(): void
    {
        $alumni = $this->makeUserWithRole('alumni');
        $other = $this->makeUserWithRole('alumni');
        $own = DocumentRequest::create(['user_id' => $alumni->id, 'document_type' => 'tor', 'status' => 'submitted', 'fee' => 100, 'is_paid' => false, 'payment_status' => 'unpaid']);
        $foreign = DocumentRequest::create(['user_id' => $other->id, 'document_type' => 'diploma', 'status' => 'submitted', 'fee' => 150, 'is_paid' => false, 'payment_status' => 'unpaid']);

        $this->actingAs($alumni)->get(route('portal.transactions.show', 'D-' . $own->id))->assertOk();
        $this->get(route('portal.transactions.show', 'D-' . $foreign->id))->assertNotFound();
    }

    public function test_portal_scope_does_not_expand_when_a_portal_user_also_has_a_staff_role(): void
    {
        $student = $this->makeUserWithRole('student');
        $student->assignRole('cashier');
        $own = Enrollment::factory()->create(['user_id' => $student->id]);
        $other = Enrollment::factory()->create();
        $ownPayment = Payment::factory()->create(['enrollment_id' => $own->id]);
        $otherPayment = Payment::factory()->create(['enrollment_id' => $other->id]);

        $this->actingAs($student)->get(route('portal.transactions.index'))
            ->assertOk()->assertSee('E-' . $ownPayment->id)->assertDontSee('E-' . $otherPayment->id);
        $this->get(route('portal.transactions.show', 'E-' . $otherPayment->id))->assertNotFound();
    }

    public function test_registrar_has_read_only_history_and_portal_requires_authentication(): void
    {
        $this->get(route('portal.transactions.index'))->assertRedirect(route('login'));
        $registrar = $this->makeUserWithRole('registrar');
        $this->actingAs($registrar)->get(route('registrar.transactions.index'))->assertOk();
        $enrollment = Enrollment::factory()->create();
        $payment = Payment::factory()->gcashPending()->create(['enrollment_id' => $enrollment->id]);
        $this->post(route('cashier.payments.verify', $payment))->assertForbidden();
    }
}
