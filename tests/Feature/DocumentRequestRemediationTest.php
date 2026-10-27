<?php

namespace Tests\Feature;

use App\Models\{Appointment, AppointmentPayment, AppointmentSlot, AuditLog, DocumentRequest, Enrollment, Setting};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Mail, Storage};
use Illuminate\Http\UploadedFile;
use Tests\SeedsRoles;
use Tests\TestCase;

class DocumentRequestRemediationTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->travelTo(\Carbon\Carbon::parse('2026-10-29 08:00:00', 'Asia/Manila'));
        Storage::fake('local');
        Mail::fake();
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        parent::tearDown();
    }

    private function slot(): AppointmentSlot
    {
        return AppointmentSlot::factory()->create(['date' => '2026-10-30', 'start_time' => '10:00:00', 'end_time' => '10:30:00', 'max_bookings' => 10]);
    }

    private function document($owner, array $data = []): DocumentRequest
    {
        return DocumentRequest::create(array_merge(['user_id' => $owner->id, 'document_type' => 'tor', 'copies' => 1,
            'fee' => 50, 'status' => 'submitted', 'is_paid' => false, 'payment_method' => 'walk_in', 'payment_status' => 'unpaid'], $data));
    }

    public function test_single_free_alumni_request_needs_no_payment_and_can_be_processed_and_released(): void
    {
        Setting::set('document_fee_tor', '0');
        $owner = $this->makeUserWithRole('alumni');
        $this->actingAs($owner)->post(route('portal.documents.store'), ['document_type' => 'tor', 'copies' => 2])
            ->assertRedirect(route('portal.documents.index'))->assertSessionHasNoErrors();
        $document = DocumentRequest::where('user_id', $owner->id)->firstOrFail();
        $this->assertTrue($document->paymentNotRequired());
        $this->assertFalse($document->is_paid);
        $this->assertNull($document->verified_at);
        $this->assertSame(0, AppointmentPayment::count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'document_request.no_payment_required']);
        $this->get(route('portal.payments.document', $document))->assertOk()->assertSee('No Payment Required')->assertViewHas('canSubmit', false);
        $this->post(route('portal.documents.payment.submit', $document), [])->assertRedirect(route('portal.payments.document', $document));
        $this->get(route('portal.documents.index'))->assertOk()->assertSee('No Payment Required');
        $this->post(route('portal.documents.store'), ['document_type' => 'tor', 'copies' => 2])->assertSessionHasNoErrors();
        $this->assertSame(1, DocumentRequest::where('user_id', $owner->id)->count());
        $registrar = $this->makeUserWithRole('registrar');
        foreach (['processing', 'ready', 'released'] as $status) {
            $this->actingAs($registrar)->post(route('registrar.documents.status', $document), ['status' => $status])->assertSessionHasNoErrors();
        }
        $this->assertSame('released', $document->fresh()->status);
        $this->assertFalse($document->fresh()->is_paid);
    }

    public function test_multiple_free_documents_create_no_financial_record_and_retries_do_not_duplicate(): void
    {
        Setting::set('document_fee_diploma', '0.00');
        Setting::set('document_fee_certification', '0');
        $owner = $this->makeUserWithRole('student');
        $payload = ['appointment_slot_id' => $this->slot()->id, 'documents' => [1 => ['document_type' => 'diploma', 'copies' => 2], 3 => ['document_type' => 'certification', 'copies' => 3]], 'payment_method' => 'gcash'];
        $this->actingAs($owner)->post(route('portal.appointments.store'), $payload)->assertSessionHasNoErrors()->assertRedirect(route('portal.appointments.index'));
        $appointment = Appointment::where('user_id', $owner->id)->firstOrFail();
        $this->assertTrue($appointment->paymentNotRequired());
        $this->assertSame(2, $appointment->documentRequests()->count());
        $this->assertSame(0, AppointmentPayment::count());
        $this->assertSame(2, AuditLog::where('action', 'document_request.no_payment_required')->count());
        $this->get(route('portal.appointments.index'))->assertOk()->assertSee('No Payment Required');
        $this->get(route('portal.payments.appointment', $appointment))->assertRedirect();
        $this->post(route('portal.appointments.store'), $payload)->assertSessionHasErrors('appointment_slot_id');
        $this->assertSame(1, Appointment::count());
        $this->assertSame(2, DocumentRequest::count());
    }

    public function test_mixed_gcash_bundle_verifies_only_the_payable_items_and_keeps_free_items_distinct(): void
    {
        Setting::set('document_fee_diploma', '0');
        Setting::set('document_fee_certification', '20');
        Setting::set('gcash_qr_path', 'qr.png'); Storage::disk('local')->put('qr.png', 'qr');
        $owner = $this->makeUserWithRole('student');
        $this->actingAs($owner)->post(route('portal.appointments.store'), ['appointment_slot_id' => $this->slot()->id,
            'documents' => [['document_type' => 'diploma', 'copies' => 1], ['document_type' => 'certification', 'copies' => 2]],
            'payment_method' => 'gcash', 'reference_number' => 'MIXED', 'proof_of_payment' => UploadedFile::fake()->image('proof.png')])->assertSessionHasNoErrors();
        $appointment = Appointment::where('user_id', $owner->id)->firstOrFail(); $payment = $appointment->payments()->firstOrFail();
        $this->assertSame('40.00', $payment->amount);
        $cashier = $this->makeUserWithRole('cashier');
        $this->actingAs($cashier)->post(route('cashier.document-payments.bundles.reject', $payment), ['rejection_reason' => 'Check receipt'])->assertSessionHasNoErrors();
        $free = $appointment->documentRequests()->where('fee', 0)->firstOrFail();
        $this->assertSame('unpaid', $free->fresh()->payment_status);
        $registrar = $this->makeUserWithRole('registrar');
        foreach (['processing', 'ready', 'released'] as $status) {
            $this->actingAs($registrar)->post(route('registrar.documents.status', $free), ['status' => $status])->assertSessionHasNoErrors();
        }
        $this->actingAs($owner)->post(route('portal.appointments.bundle-payment.gcash', $appointment), ['reference_number' => 'MIXED-RETRY', 'proof_of_payment' => UploadedFile::fake()->image('again.png')])->assertSessionHasNoErrors();
        Setting::set('document_fee_certification', '0');
        $this->actingAs($cashier)->post(route('cashier.document-payments.bundles.verify', $payment), ['amount_received' => 40])->assertSessionHasNoErrors();
        $this->assertSame('verified', $payment->fresh()->status);
        $this->assertTrue($free->fresh()->paymentNotRequired());
        $this->assertFalse($free->fresh()->is_paid);
        $this->assertNull($free->fresh()->receipt_no);
        $this->assertNull($free->fresh()->amount_received);
        $this->assertSame(1, $appointment->documentRequests()->where('is_paid', true)->count());
        $this->post(route('cashier.document-payments.bundles.verify', $payment), ['amount_received' => 40])->assertSessionHasErrors('payment');
    }

    public function test_browser_zero_price_and_waiver_fields_cannot_exempt_a_payable_request(): void
    {
        Setting::set('document_fee_diploma', '100');
        $owner = $this->makeUserWithRole('student');
        $this->actingAs($owner)->post(route('portal.appointments.store'), ['appointment_slot_id' => $this->slot()->id,
            'documents' => [['document_type' => 'diploma', 'copies' => 2, 'fee' => 0]], 'payment_method' => 'walk_in',
            'total' => 0, 'fee' => 0, 'is_paid' => true, 'payment_status' => 'verified', 'payment_exempt' => true])->assertSessionHasNoErrors();
        $document = DocumentRequest::where('user_id', $owner->id)->firstOrFail();
        $this->assertSame('200.00', $document->fee); $this->assertFalse($document->paymentNotRequired());
        $this->assertFalse($document->is_paid);
        $this->assertSame('200.00', $document->appointment->payments()->first()->amount);
    }

    public function test_invalid_configured_prices_are_rejected_without_creating_records(): void
    {
        $owner = $this->makeUserWithRole('student'); $slot = $this->slot();
        foreach (['-1', 'invalid', 'NaN', '0.001'] as $price) {
            Setting::set('document_fee_diploma', $price);
            $this->actingAs($owner)->post(route('portal.appointments.store'), ['appointment_slot_id' => $slot->id,
                'documents' => [['document_type' => 'diploma', 'copies' => 1]], 'payment_method' => 'walk_in'])->assertSessionHasErrors('payment');
        }
        $this->assertSame(0, DocumentRequest::count()); $this->assertSame(0, AppointmentPayment::count());
    }

    public function test_price_changes_do_not_reprice_existing_requests_or_exempt_verified_payments(): void
    {
        $owner = $this->makeUserWithRole('alumni'); $document = $this->document($owner);
        Setting::set('document_fee_tor', '0');
        $this->assertFalse($document->fresh()->paymentNotRequired());
        $cashier = $this->makeUserWithRole('cashier');
        $this->actingAs($cashier)->post(route('cashier.document-payments.verify-walk-in', $document), ['amount_received' => 50, 'receipt_no' => 'EXISTING'])->assertSessionHasNoErrors();
        $this->assertTrue($document->fresh()->is_paid); $this->assertSame('50.00', $document->fresh()->fee);
        $this->assertFalse($document->fresh()->paymentNotRequired());
    }

    public function test_no_cashier_or_requester_endpoint_can_create_a_payment_for_a_free_document(): void
    {
        $owner = $this->makeUserWithRole('student'); $document = $this->document($owner, ['fee' => 0]);
        $cashier = $this->makeUserWithRole('cashier');
        $this->actingAs($cashier)->post(route('cashier.document-payments.verify-walk-in', $document), ['amount_received' => 1, 'receipt_no' => 'FORGED'])->assertSessionHasErrors('payment');
        Setting::set('gcash_qr_path', 'qr.png'); Storage::disk('local')->put('qr.png', 'qr');
        $this->actingAs($owner)->post(route('portal.appointments.document-payment.gcash', $document), ['reference_number' => 'FREE', 'proof_of_payment' => UploadedFile::fake()->image('proof.png')])->assertRedirect();
        $this->assertFalse($document->fresh()->is_paid); $this->assertNull($document->fresh()->proof_of_payment);
        $this->assertSame(0, AppointmentPayment::count());
    }

    public function test_applicant_is_denied_all_document_entry_points_even_for_owned_historical_records(): void
    {
        $owner = $this->makeUserWithRole('new_applicant'); $document = $this->document($owner);
        $appointment = Appointment::factory()->create(['user_id' => $owner->id]);
        $payment = AppointmentPayment::create(['appointment_id' => $appointment->id, 'payment_method' => 'gcash', 'status' => 'pending', 'amount' => 50]);
        $gets = [route('portal.appointments.create'), route('portal.appointments.index'), route('portal.appointments.edit', $appointment),
            route('portal.payments.document', $document), route('portal.payments.appointment', $appointment), route('portal.documents.create'), route('portal.documents.index'),
            route('portal.appointments.payment.proof', $payment), route('portal.appointments.document-payment.proof', $document), route('portal.appointments.gcash.qr')];
        foreach ($gets as $url) $this->actingAs($owner)->getJson($url)->assertForbidden();
        $posts = [route('portal.appointments.store'), route('portal.documents.store'), route('portal.appointments.cancel', $appointment),
            route('portal.appointments.payment.gcash', $appointment), route('portal.appointments.bundle-payment.gcash', $appointment),
            route('portal.appointments.document-payment.gcash', $document), route('portal.documents.payment.submit', $document)];
        foreach ($posts as $url) $this->postJson($url, ['fee' => 0, 'payment_status' => 'verified'])->assertForbidden();
        $this->putJson(route('portal.appointments.update', $appointment), [])->assertForbidden();
        $this->getJson(route('search', ['q' => 'Appointment']))->assertOk()->assertJsonCount(0, 'results');
        $this->get(route('portal.dashboard'))->assertOk()->assertDontSee('href="'.route('portal.appointments.create').'"', false);
        $this->get(route('portal.transactions.show', 'D-'.$document->id))->assertNotFound();
        $this->assertSame('pending', $appointment->fresh()->status); $this->assertSame('50.00', $document->fresh()->fee);
        $registrar = $this->makeUserWithRole('registrar');
        $this->actingAs($registrar)->post(route('registrar.documents.status', $document), ['status' => 'processing'])->assertSessionHasNoErrors();
        $this->assertSame(route('portal.dashboard'), $owner->userNotifications()->where('title', 'Document Request Update')->firstOrFail()->link);
    }

    public function test_role_transitions_are_enforced_on_the_next_request_without_losing_historical_records(): void
    {
        $owner = $this->makeUserWithRole('new_applicant');
        $this->actingAs($owner)->get(route('portal.appointments.create'))->assertForbidden();
        $owner->syncRoles(['student']);
        $this->get(route('portal.appointments.create'))->assertOk();
        $document = $this->document($owner);
        $other = $this->makeUserWithRole('alumni');
        $this->actingAs($other)->get(route('portal.payments.document', $document))->assertForbidden();
        $owner->syncRoles(['new_applicant']);
        $this->actingAs($owner)->get(route('portal.payments.document', $document))->assertForbidden();
        $this->assertNotNull($document->fresh());
    }

    public function test_applicant_enrollment_payments_remain_available(): void
    {
        $owner = $this->makeUserWithRole('new_applicant');
        $enrollment = Enrollment::factory()->create(['user_id' => $owner->id, 'status' => 'approved']);
        $this->actingAs($owner)->get(route('portal.enrollment.payment-info', $enrollment))->assertOk();
    }

    public function test_free_alumni_pickup_still_requires_readiness_and_registrar_confirmation(): void
    {
        $owner = $this->makeUserWithRole('alumni'); $document = $this->document($owner, ['fee' => 0]);
        $slot = $this->slot();
        $this->actingAs($owner)->post(route('portal.documents.pickup-appointment', $document), ['appointment_slot_id' => $slot->id])->assertSessionHasErrors('appointment_slot_id');
        $registrar = $this->makeUserWithRole('registrar');
        foreach (['processing', 'ready'] as $status) {
            $this->actingAs($registrar)->post(route('registrar.documents.status', $document), ['status' => $status])->assertSessionHasNoErrors();
        }
        $this->actingAs($owner)->post(route('portal.documents.pickup-appointment', $document), ['appointment_slot_id' => $slot->id])->assertSessionHasNoErrors();
        $appointment = $document->fresh()->appointment;
        $this->assertNotNull($appointment);
        $this->actingAs($registrar)->post(route('registrar.appointments.confirm', $appointment))->assertSessionHasNoErrors();
        $this->assertSame('confirmed', $appointment->fresh()->status);
        $this->assertSame(0, AppointmentPayment::count());
    }

    public function test_zero_fee_requests_do_not_appear_as_unpaid_cashier_work_or_financial_transactions(): void
    {
        $owner = $this->makeUserWithRole('alumni'); $document = $this->document($owner, ['fee' => 0]);
        $cashier = $this->makeUserWithRole('cashier');
        $this->actingAs($cashier)->get(route('cashier.document-payments.index'))->assertOk()
            ->assertViewHas('requests', fn ($requests) => !$requests->contains('id', $document->id));
        $this->get(route('cashier.document-payments.show', $document))->assertOk()->assertSee('No Payment Required')->assertDontSee('name="amount_received"', false);
        $this->assertNull(app(\App\Services\TransactionHistory::class)->find($owner->id, 'alumni', 'D-'.$document->id));
    }

    public function test_a_document_type_edit_cannot_silently_move_between_free_and_payable_workflows(): void
    {
        Setting::set('document_fee_diploma', '0'); Setting::set('document_fee_certification', '20');
        $owner = $this->makeUserWithRole('student'); $slot = $this->slot();
        $this->actingAs($owner)->post(route('portal.appointments.store'), ['appointment_slot_id' => $slot->id, 'documents' => [['document_type' => 'diploma', 'copies' => 1]]])->assertSessionHasNoErrors();
        $appointment = Appointment::where('user_id', $owner->id)->firstOrFail();
        $this->put(route('portal.appointments.update', $appointment), ['appointment_slot_id' => $slot->id, 'document_type' => 'certification', 'purpose' => 'Employment'])->assertSessionHasErrors('document_type');
        $this->assertTrue($appointment->fresh()->paymentNotRequired()); $this->assertSame(0, AppointmentPayment::count());
    }
}
