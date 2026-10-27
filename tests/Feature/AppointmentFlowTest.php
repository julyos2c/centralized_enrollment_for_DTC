<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AppointmentPayment;
use App\Models\AppointmentSlot;
use App\Models\DocumentRequest;
use App\Models\UserNotification;
use App\Models\Setting;
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

    public function test_document_gcash_forms_offer_copy_controls_and_authenticated_qr_downloads(): void
    {
        Storage::disk('local')->put('settings/gcash-qr.png', 'qr');
        Setting::set('gcash_qr_path', 'settings/gcash-qr.png');
        Setting::set('gcash_name', 'DTC test account');
        Setting::set('gcash_number', '09123456789');
        foreach (['student', 'alumni'] as $role) {
            $user = $this->makeUserWithRole($role);
            $prefix = $role === 'alumni' ? 'portal.documents' : 'portal.appointments';
            $this->actingAs($user)->get(route($prefix.'.create'))->assertOk()
                ->assertSee('Copy number')->assertSee('Copy amount')->assertSee('Save QR')
                ->assertSee('data-copy-value="09123456789"', false)
                ->assertSee('data-document-copy-amount', false);
            $this->get(route($prefix.'.gcash.qr', ['download' => 1]))->assertOk()->assertDownload('DTC-GCash-QR.png');
            $this->get(route($prefix.'.gcash.qr'))->assertOk();
        }
    }

    public function test_bundle_gcash_verification_does_not_require_an_optional_receipt_number(): void
    {
        $student = $this->makeUserWithRole('student');
        $cashier = $this->makeUserWithRole('cashier');
        Storage::disk('local')->put('settings/gcash-qr.png', 'qr');
        Setting::set('gcash_qr_path', 'settings/gcash-qr.png');
        $this->actingAs($student)->post(route('portal.appointments.store'), [
            'appointment_slot_id' => $this->makeSlot()->id,
            'documents' => [1 => ['document_type' => 'diploma', 'copies' => 1], 2 => ['document_type' => 'certification', 'copies' => 1]],
            'payment_method' => 'gcash', 'reference_number' => 'OPTIONAL-RECEIPT', 'proof_of_payment' => $this->fakeProof(),
        ])->assertSessionHasNoErrors();
        $appointment = Appointment::where('user_id', $student->id)->firstOrFail();
        $payment = $appointment->payments()->firstOrFail();
        $this->actingAs($cashier)->post(route('cashier.document-payments.bundles.verify', $payment), ['amount_received' => $payment->amount])->assertSessionHasNoErrors();
        $this->assertSame('verified', $payment->fresh()->status);
        $this->assertSame(2, $appointment->documentRequests()->where('is_paid', true)->count());
        $this->assertNull($appointment->documentRequests()->first()->receipt_no);
    }

    public function test_individual_payment_endpoints_cannot_split_a_bundled_payment(): void
    {
        foreach (['student', 'alumni'] as $role) {
            $owner = $this->makeUserWithRole($role);
            $this->actingAs($owner)->post(route('portal.appointments.store'), [
                'appointment_slot_id' => $this->makeSlot()->id,
                'documents' => [['document_type' => 'diploma', 'copies' => 1], ['document_type' => 'certification', 'copies' => 1]],
                'payment_method' => 'walk_in',
            ])->assertSessionHasNoErrors();
            $appointment = Appointment::where('user_id', $owner->id)->firstOrFail();
            $document = $appointment->documentRequests()->firstOrFail();
            $cashier = $this->makeUserWithRole('cashier');
            $this->actingAs($cashier)->post(route('cashier.document-payments.verify-walk-in', $document), ['amount_received' => $document->fee, 'receipt_no' => 'SPLIT-BYPASS'])->assertSessionHasErrors('payment');
            $this->post(route('cashier.document-payments.reject', $document), ['rejection_reason' => 'Test'])->assertSessionHasErrors('payment');
            $this->actingAs($owner)->post(route('portal.appointments.document-payment.gcash', $document), [])->assertSessionHasErrors('payment');
            if ($role === 'alumni') {
                $this->post(route('portal.documents.payment.submit', $document), [])->assertSessionHasErrors('payment');
            }
            $this->assertFalse($document->fresh()->is_paid);
            $this->assertSame('unpaid', $document->fresh()->payment_status);
            $this->assertSame('pending', $appointment->payments()->first()->status);
        }
    }

    public function test_missing_bundle_copy_quantity_has_a_readable_validation_message(): void
    {
        $student = $this->makeUserWithRole('student');
        $response = $this->actingAs($student)->postJson(route('portal.appointments.store'), [
            'appointment_slot_id' => $this->makeSlot()->id,
            'documents' => [1 => ['document_type' => 'diploma']], 'payment_method' => 'walk_in',
        ])->assertUnprocessable();
        $this->assertStringContainsString('number of copies', $response->json('errors.documents.1.copies.0') ?? json_encode($response->json('errors')));
        $this->assertSame(0, Appointment::where('user_id', $student->id)->count());
    }

    public function test_alumni_can_select_later_document_options_without_selecting_the_first_option(): void
    {
        $alumni = $this->makeUserWithRole('alumni');
        $this->actingAs($alumni)->post(route('portal.documents.store'), [
            'appointment_slot_id' => $this->makeSlot()->id,
            'documents' => [1 => ['document_type' => 'diploma', 'copies' => 2], 3 => ['document_type' => 'true_copy', 'copies' => 1]],
            'payment_method' => 'walk_in',
        ])->assertRedirect(route('portal.documents.index'))->assertSessionHasNoErrors();
        $appointment = Appointment::where('user_id', $alumni->id)->firstOrFail();
        $this->assertSame(2, $appointment->documentRequests()->count());
        $this->assertSame('diploma', $appointment->document_type);
    }

    public function test_document_bundle_role_boundaries_and_admin_processing_access(): void
    {
        $student = $this->makeUserWithRole('student');
        $this->actingAs($student)->post(route('portal.appointments.store'), [
            'appointment_slot_id' => $this->makeSlot()->id,
            'documents' => [['document_type' => 'diploma', 'copies' => 1]],
            'payment_method' => 'walk_in',
        ])->assertRedirect(route('portal.appointments.index'));
        $appointment = Appointment::where('user_id', $student->id)->firstOrFail();
        $payment = $appointment->payments()->firstOrFail();
        $document = $appointment->documentRequests()->firstOrFail();
        foreach (['student', 'alumni', 'new_applicant', 'registrar'] as $role) {
            $actor = $this->makeUserWithRole($role);
            $this->actingAs($actor)->post(route('cashier.document-payments.bundles.verify', $payment), ['amount_received' => $payment->amount, 'receipt_no' => 'ROLE-CHECK'])->assertForbidden();
            if (in_array($role, ['student', 'alumni', 'new_applicant'], true)) {
                $this->get(route('portal.payments.appointment', $appointment))->assertForbidden();
            }
        }
        $cashier = $this->makeUserWithRole('cashier');
        $this->actingAs($cashier)->post(route('registrar.documents.status', $document), ['status' => 'processing'])->assertForbidden();
        $admin = $this->makeUserWithRole('admin');
        $this->actingAs($admin)->post(route('cashier.document-payments.bundles.verify', $payment), ['amount_received' => $payment->amount, 'receipt_no' => 'ADMIN-CHECK'])->assertSessionHasNoErrors();
        $this->post(route('registrar.documents.status', $document), ['status' => 'processing'])->assertSessionHasNoErrors();
        $this->assertTrue($document->fresh()->is_paid);
        $this->assertSame('processing', $document->fresh()->status);
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
        $documentRequest = DocumentRequest::where('appointment_id', Appointment::where('user_id', $student->id)->value('id'))->firstOrFail();
        $this->assertSame('tor', $documentRequest->document_type);
        $this->assertSame('50.00', $documentRequest->fee);
        $this->assertSame('walk_in', $documentRequest->payment_method);
        $this->assertSame('unpaid', $documentRequest->payment_status);

    }

    /** @test */
    public function student_can_book_multiple_documents_and_copies_under_one_appointment()
    {
        $student = $this->makeUserWithRole('student');
        $slot = $this->makeSlot();

        $this->actingAs($student)->post(route('portal.appointments.store'), [
            'appointment_slot_id' => $slot->id,
            'documents' => [
                ['document_type' => 'transcript', 'copies' => 1],
                ['document_type' => 'diploma', 'copies' => 1],
                ['document_type' => 'certification', 'copies' => 2],
            ],
            'purpose' => 'Employment',
            'payment_method' => 'walk_in',
        ])->assertRedirect(route('portal.appointments.index'));

        $appointment = Appointment::where('user_id', $student->id)->firstOrFail();
        $requests = DocumentRequest::where('appointment_id', $appointment->id)->orderBy('id')->get();
        $this->assertCount(3, $requests);
        $this->assertSame(['tor', 'diploma', 'certification'], $requests->pluck('document_type')->all());
        $this->assertSame(['50.00', '100.00', '60.00'], $requests->pluck('fee')->all());
        $this->assertSame([1, 1, 2], $requests->pluck('copies')->all());
        $this->assertDatabaseHas('appointment_payments', [
            'appointment_id' => $appointment->id,
            'is_document_bundle' => true,
            'payment_method' => 'walk_in',
            'amount' => '210.00',
        ]);

        $this->actingAs($student)->get(route('portal.appointments.index'))
            ->assertOk()
            ->assertSee('Transcript of Records')
            ->assertSee('Diploma')
            ->assertSee('Certification')
            ->assertSee('Combined total');
    }

    /** @test */
    public function student_cannot_submit_duplicate_document_types_in_a_combined_booking()
    {
        $student = $this->makeUserWithRole('student');
        $slot = $this->makeSlot();

        $this->actingAs($student)->post(route('portal.appointments.store'), [
            'appointment_slot_id' => $slot->id,
            'documents' => [
                ['document_type' => 'diploma', 'copies' => 1],
                ['document_type' => 'diploma', 'copies' => 2],
            ],
            'payment_method' => 'walk_in',
        ])->assertSessionHasErrors('documents');

        $this->assertDatabaseCount('appointments', 0);
        $this->assertDatabaseCount('document_requests', 0);
    }

    /** @test */
    public function student_cannot_mix_legacy_and_combined_document_fields()
    {
        $student = $this->makeUserWithRole('student');
        $slot = $this->makeSlot();

        $this->actingAs($student)->post(route('portal.appointments.store'), [
            'appointment_slot_id' => $slot->id,
            'document_type' => 'diploma',
            'documents' => [['document_type' => 'tor', 'copies' => 1]],
            'payment_method' => 'walk_in',
        ])->assertSessionHasErrors('documents');

        $this->assertDatabaseCount('appointments', 0);
        $this->assertDatabaseCount('document_requests', 0);
    }

    /** @test */
    public function cashier_verifies_one_bundle_payment_for_all_linked_student_documents()
    {
        $student = $this->makeUserWithRole('student');
        $cashier = $this->makeUserWithRole('cashier');
        $slot = $this->makeSlot();
        Storage::disk('local')->put('settings/gcash-qr.png', 'qr');
        Setting::set('gcash_qr_path', 'settings/gcash-qr.png');

        $this->actingAs($student)->post(route('portal.appointments.store'), [
            'appointment_slot_id' => $slot->id,
            'documents' => [
                ['document_type' => 'transcript', 'copies' => 2],
                ['document_type' => 'certification', 'copies' => 1],
            ],
            'payment_method' => 'gcash',
            'reference_number' => 'BUNDLE-REF-1',
            'proof_of_payment' => $this->fakeProof(),
        ])->assertRedirect(route('portal.appointments.index'));

        $appointment = Appointment::where('user_id', $student->id)->firstOrFail();
        $payment = $appointment->payments()->where('is_document_bundle', true)->firstOrFail();
        $this->assertSame('130.00', $payment->amount);
        Storage::disk('local')->assertExists($payment->proof_of_payment);

        $this->actingAs($cashier)->post(route('cashier.document-payments.bundles.verify', $payment), [
            'amount_received' => '130.00',
            'receipt_no' => 'DTC-BUNDLE-001',
        ])->assertRedirect(route('cashier.document-payments.show', $appointment->documentRequests()->first()));

        $this->assertSame('verified', $payment->fresh()->status);
        $this->assertSame('DTC-BUNDLE-001', $payment->fresh()->receipt_no);
        $this->assertSame(2, $appointment->documentRequests()->where('payment_status', 'verified')->count());
        $this->assertSame(2, $appointment->documentRequests()->where('is_paid', true)->count());
    }

    /** @test */
    public function cashier_can_reject_and_student_can_resubmit_a_combined_gcash_payment()
    {
        $student = $this->makeUserWithRole('student');
        $cashier = $this->makeUserWithRole('cashier');
        $slot = $this->makeSlot();
        Storage::disk('local')->put('settings/gcash-qr.png', 'qr');
        Setting::set('gcash_qr_path', 'settings/gcash-qr.png');

        $this->actingAs($student)->post(route('portal.appointments.store'), [
            'appointment_slot_id' => $slot->id,
            'documents' => [
                ['document_type' => 'transcript', 'copies' => 1],
                ['document_type' => 'diploma', 'copies' => 1],
            ],
            'payment_method' => 'gcash',
            'reference_number' => 'BUNDLE-REF-OLD',
            'proof_of_payment' => $this->fakeProof(),
        ])->assertRedirect(route('portal.appointments.index'));

        $appointment = Appointment::where('user_id', $student->id)->firstOrFail();
        $payment = $appointment->payments()->where('is_document_bundle', true)->firstOrFail();
        $oldProof = $payment->proof_of_payment;
        $firstRequest = $appointment->documentRequests()->firstOrFail();

        $this->actingAs($cashier)->post(route('cashier.document-payments.bundles.reject', $payment), [
            'rejection_reason' => 'Reference could not be matched.',
        ])->assertRedirect(route('cashier.document-payments.show', $firstRequest));
        $this->assertSame('rejected', $payment->fresh()->status);
        $this->assertSame(2, $appointment->documentRequests()->where('payment_status', 'rejected')->count());

        $this->actingAs($student)->post(route('portal.appointments.bundle-payment.gcash', $appointment), [
            'reference_number' => 'BUNDLE-REF-NEW',
            'proof_of_payment' => $this->fakeProof(),
        ])->assertRedirect(route('portal.payments.appointment', $appointment));

        $this->assertSame('pending', $payment->fresh()->status);
        $this->assertSame('BUNDLE-REF-NEW', $payment->fresh()->reference_number);
        $this->assertNotSame($oldProof, $payment->fresh()->proof_of_payment);
        Storage::disk('local')->assertMissing($oldProof);
        $this->assertSame(2, $appointment->documentRequests()->where('payment_status', 'pending_verification')->count());
    }

    /** @test */
    public function registrar_cannot_verify_or_reject_a_bundled_gcash_payment()
    {
        $student = $this->makeUserWithRole('student');
        $registrar = $this->makeUserWithRole('registrar');
        $slot = $this->makeSlot();
        Storage::disk('local')->put('settings/gcash-qr.png', 'qr');
        Setting::set('gcash_qr_path', 'settings/gcash-qr.png');

        $this->actingAs($student)->post(route('portal.appointments.store'), [
            'appointment_slot_id' => $slot->id,
            'documents' => [['document_type' => 'diploma', 'copies' => 1]],
            'payment_method' => 'gcash',
            'reference_number' => 'BUNDLE-REG-001',
            'proof_of_payment' => $this->fakeProof(),
        ])->assertRedirect(route('portal.appointments.index'));

        $appointment = Appointment::where('user_id', $student->id)->firstOrFail();
        $payment = $appointment->payments()->where('is_document_bundle', true)->firstOrFail();

        $this->actingAs($registrar)
            ->post(route('registrar.appointments.payments.verify', $payment))
            ->assertStatus(422);
        $this->post(route('registrar.appointments.payments.reject', $payment), [
            'rejection_reason' => 'Should be handled by Cashier.',
        ])->assertStatus(422);

        $this->assertSame('pending', $payment->fresh()->status);
        $this->assertSame('pending_verification', $appointment->documentRequests()->firstOrFail()->payment_status);
    }

    /** @test */
    public function cashier_can_reconcile_a_gcash_bundle_after_the_appointment_is_cancelled()
    {
        $student = $this->makeUserWithRole('student');
        $cashier = $this->makeUserWithRole('cashier');
        $slot = $this->makeSlot();
        Storage::disk('local')->put('settings/gcash-qr.png', 'qr');
        Setting::set('gcash_qr_path', 'settings/gcash-qr.png');

        $this->actingAs($student)->post(route('portal.appointments.store'), [
            'appointment_slot_id' => $slot->id,
            'documents' => [
                ['document_type' => 'transcript', 'copies' => 1],
                ['document_type' => 'diploma', 'copies' => 1],
            ],
            'payment_method' => 'gcash',
            'reference_number' => 'BUNDLE-CANCEL-001',
            'proof_of_payment' => $this->fakeProof(),
        ])->assertRedirect(route('portal.appointments.index'));

        $appointment = Appointment::where('user_id', $student->id)->firstOrFail();
        $payment = $appointment->payments()->where('is_document_bundle', true)->firstOrFail();
        $documentRequest = $appointment->documentRequests()->firstOrFail();

        $this->actingAs($student)
            ->post(route('portal.appointments.cancel', $appointment))
            ->assertRedirect(route('portal.appointments.index'));
        $this->assertSame('cancelled', $appointment->fresh()->status);
        $this->assertSame('cancelled', $documentRequest->fresh()->status);

        $this->actingAs($cashier)
            ->get(route('cashier.document-payments.index', ['status' => 'pending_verification']))
            ->assertOk()
            ->assertSee('Combined appointment payments')
            ->assertSee('#' . $appointment->id)
            ->assertSee('Review bundle');
        $this->get(route('cashier.document-payments.show', $documentRequest))->assertOk();

        $this->post(route('cashier.document-payments.bundles.verify', $payment), [
            'amount_received' => '150.00',
            'receipt_no' => 'DTC-CANCEL-BUNDLE-001',
        ])->assertRedirect(route('cashier.document-payments.show', $documentRequest));

        $this->assertSame('verified', $payment->fresh()->status);
        $this->assertSame('cancelled', $documentRequest->fresh()->status);
        $this->assertSame('verified', $documentRequest->fresh()->payment_status);
        $this->assertTrue($documentRequest->fresh()->is_paid);
        $this->assertSame('DTC-CANCEL-BUNDLE-001', $documentRequest->fresh()->receipt_no);
    }

    /** @test */
    public function confirming_a_new_document_appointment_takes_registrar_to_the_request_for_processing()
    {
        $student = $this->makeUserWithRole('student');
        $registrar = $this->makeUserWithRole('registrar');
        $slot = $this->makeSlot();

        $this->actingAs($student)->post(route('portal.appointments.store'), [
            'appointment_slot_id' => $slot->id,
            'document_type' => 'certification',
            'purpose' => 'Scholarship application',
            'payment_method' => 'walk_in',
        ])->assertRedirect(route('portal.appointments.index'));

        $appointment = Appointment::where('user_id', $student->id)->firstOrFail();
        $documentRequest = DocumentRequest::where('appointment_id', $appointment->id)->firstOrFail();
        $this->assertSame('submitted', $documentRequest->status);
        $this->assertSame('walk_in', $documentRequest->payment_method);
        $this->assertSame('unpaid', $documentRequest->payment_status);

        $this->actingAs($registrar)
            ->post(route('registrar.appointments.confirm', $appointment))
            ->assertRedirect(route('registrar.documents.index'));

        $documentRequest = DocumentRequest::where('appointment_id', $appointment->id)->firstOrFail();
        $this->assertSame('submitted', $documentRequest->status);

        $confirmation = UserNotification::where('user_id', $student->id)
            ->where('title', 'Appointment Confirmed')
            ->latest('id')
            ->firstOrFail();
        $this->assertSame(route('portal.appointments.index'), $confirmation->link);
        $this->assertStringContainsString('The Registrar will process your document requests.', $confirmation->message);

        $this->actingAs($registrar)
            ->get(route('registrar.documents.index'))
            ->assertOk()
            ->assertSee('Certification')
            ->assertSee('Submitted')
            ->assertSee('Update');

        $this->assertSame('submitted', $documentRequest->fresh()->status);
        $this->assertSame('confirmed', $appointment->fresh()->status);
    }

    /** @test */
    public function student_document_status_notification_opens_their_appointments_and_shows_the_update()
    {
        $student = $this->makeUserWithRole('student');
        $registrar = $this->makeUserWithRole('registrar');
        $appointment = Appointment::factory()->create(['user_id' => $student->id]);
        $request = DocumentRequest::create([
            'user_id' => $student->id,
            'appointment_id' => $appointment->id,
            'document_type' => 'tor',
            'copies' => 1,
            'status' => 'submitted',
            'fee' => 50,
            'is_paid' => false,
            'payment_method' => 'walk_in',
            'payment_status' => 'unpaid',
        ]);

        $this->actingAs($registrar)
            ->post(route('registrar.documents.status', $request), ['status' => 'processing'])
            ->assertRedirect(route('registrar.documents.index'));

        $notification = UserNotification::where('user_id', $student->id)
            ->where('title', 'Document Request Update')
            ->latest('id')
            ->firstOrFail();
        $this->assertSame(route('portal.appointments.index'), $notification->link);

        $this->actingAs($student)
            ->get($notification->link)
            ->assertOk()
            ->assertSee('Preparing')
            ->assertSee('Transcript of Records');
    }

    /** @test */
    public function student_can_book_with_gcash_and_submit_proof()
    {
        $student = $this->makeUserWithRole('student');
        $slot    = $this->makeSlot();
        Storage::disk('local')->put('settings/gcash-qr.png', 'qr');
        Setting::set('gcash_qr_path', 'settings/gcash-qr.png');

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

        $payment = $appointment->payments()->where('is_document_bundle', true)->firstOrFail();
        $this->assertSame('100.00', $payment->amount);
        $this->assertSame('pending', $payment->status);
        $documentRequest = DocumentRequest::where('appointment_id', $appointment->id)->firstOrFail();
        $this->assertSame('gcash_qr', $documentRequest->payment_method);
        $this->assertSame('pending_verification', $documentRequest->payment_status);
        $this->assertSame('100.00', $documentRequest->fee);
        $this->assertSame('1234567890', $payment->reference_number);
        Storage::disk('local')->assertExists($payment->proof_of_payment);

        $cashier = $this->makeUserWithRole('cashier');
        $this->actingAs($cashier)->get(route('cashier.document-payments.show', $documentRequest))->assertOk();
        $this->actingAs($cashier)->post(route('cashier.document-payments.bundles.verify', $payment), [
            'amount_received' => '100.00',
            'receipt_no' => 'DTC-OR-GC-001',
        ])->assertRedirect(route('cashier.document-payments.show', $documentRequest));
        $this->assertSame('verified', $documentRequest->fresh()->payment_status);
        $this->assertTrue($documentRequest->fresh()->is_paid);
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
    public function cashier_can_verify_walk_in_payment_for_a_student_document_request()
    {
        $student = $this->makeUserWithRole('student');
        $cashier = $this->makeUserWithRole('cashier');
        $appointment = Appointment::factory()->create(['user_id' => $student->id]);
        $documentRequest = DocumentRequest::create([
            'user_id' => $student->id,
            'appointment_id' => $appointment->id,
            'document_type' => 'certification',
            'copies' => 1,
            'status' => 'submitted',
            'fee' => 30,
            'is_paid' => false,
            'payment_method' => 'walk_in',
            'payment_status' => 'unpaid',
        ]);

        $this->actingAs($cashier)->post(route('cashier.document-payments.verify-walk-in', $documentRequest), [
            'amount_received' => '30.00',
            'receipt_no' => 'DTC-OR-WI-001',
        ])->assertRedirect(route('cashier.document-payments.show', $documentRequest));

        $this->assertSame('verified', $documentRequest->fresh()->payment_status);
        $this->assertTrue($documentRequest->fresh()->is_paid);
        $this->assertSame('DTC-OR-WI-001', $documentRequest->fresh()->receipt_no);
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
    public function cancelling_an_appointment_cancels_only_a_submitted_linked_request_and_preserves_payment_history()
    {
        $student = $this->makeUserWithRole('student');
        $appointment = Appointment::factory()->create(['user_id' => $student->id, 'status' => 'pending']);
        $request = DocumentRequest::create([
            'user_id' => $student->id,
            'appointment_id' => $appointment->id,
            'document_type' => 'tor',
            'status' => 'submitted',
            'is_paid' => true,
            'payment_status' => 'verified',
            'payment_method' => 'walk_in',
            'receipt_no' => 'OR-KEEP-001',
            'amount_received' => 50,
        ]);

        $this->actingAs($student)
            ->post(route('portal.appointments.cancel', $appointment))
            ->assertRedirect(route('portal.appointments.index'));

        $this->assertSame('cancelled', $appointment->fresh()->status);
        $request->refresh();
        $this->assertSame('cancelled', $request->status);
        $this->assertTrue($request->is_paid);
        $this->assertSame('verified', $request->payment_status);
        $this->assertSame('OR-KEEP-001', $request->receipt_no);
        $this->assertSame('50.00', $request->amount_received);

        $registrar = $this->makeUserWithRole('registrar');
        $this->actingAs($registrar)
            ->get(route('registrar.documents.index'))
            ->assertOk()
            ->assertSee('Cancelled')
            ->assertDontSee('Update Request Status');
    }

    /** @test */
    public function student_history_keeps_active_document_requests_visible_when_pickup_appointment_is_cancelled()
    {
        $student = $this->makeUserWithRole('student');
        $slot = $this->makeSlot();
        $appointment = Appointment::factory()->create([
            'user_id' => $student->id,
            'appointment_slot_id' => $slot->id,
            'status' => 'cancelled',
            'remarks' => 'Pickup slot no longer available.',
        ]);
        DocumentRequest::create([
            'user_id' => $student->id,
            'appointment_id' => $appointment->id,
            'document_type' => 'diploma',
            'copies' => 1,
            'status' => 'processing',
            'fee' => 100,
            'payment_method' => 'walk_in',
            'payment_status' => 'unpaid',
        ]);
        DocumentRequest::create([
            'user_id' => $student->id,
            'appointment_id' => $appointment->id,
            'document_type' => 'certification',
            'copies' => 1,
            'status' => 'cancelled',
            'fee' => 30,
            'payment_method' => 'walk_in',
            'payment_status' => 'unpaid',
        ]);

        $this->actingAs($student)->get(route('portal.appointments.index'))
            ->assertOk()
            ->assertSee('The pickup appointment was cancelled, but your document request remains active.')
            ->assertSee('Pickup slot no longer available.')
            ->assertSee('Diploma')
            ->assertSee('Certification')
            ->assertSee('Payment details')
            ->assertSee('data-group="progress"', false)
            ->assertSee('data-groups="progress cancelled"', false);
    }

    /** @test */
    public function student_history_places_cancelled_document_requests_in_cancelled_filter()
    {
        $student = $this->makeUserWithRole('student');
        $slot = $this->makeSlot();
        $appointment = Appointment::factory()->create([
            'user_id' => $student->id,
            'appointment_slot_id' => $slot->id,
            'status' => 'cancelled',
            'remarks' => 'Cancelled by requester.',
        ]);
        DocumentRequest::create([
            'user_id' => $student->id,
            'appointment_id' => $appointment->id,
            'document_type' => 'tor',
            'copies' => 1,
            'status' => 'cancelled',
            'fee' => 50,
            'payment_method' => 'walk_in',
            'payment_status' => 'unpaid',
        ]);

        $this->actingAs($student)->get(route('portal.appointments.index'))
            ->assertOk()
            ->assertSee('data-filter="cancelled"', false)
            ->assertSee('data-group="cancelled"', false)
            ->assertSee('Cancelled by requester.')
            ->assertSee('Transcript of Records');
    }

    /** @test */
    public function registrar_cancellation_preserves_processing_ready_and_released_linked_requests()
    {
        $registrar = $this->makeUserWithRole('registrar');
        foreach (['processing', 'ready', 'released'] as $status) {
            $student = $this->makeUserWithRole('student');
            $appointment = Appointment::factory()->confirmed()->create(['user_id' => $student->id]);
            $request = DocumentRequest::create([
                'user_id' => $student->id,
                'appointment_id' => $appointment->id,
                'document_type' => 'tor',
                'status' => $status,
                'fee' => 50,
            ]);

            $this->actingAs($registrar)
                ->post(route('registrar.appointments.cancel', $appointment), ['remarks' => 'Schedule changed.'])
                ->assertRedirect(route('registrar.appointments.index'));

            $this->assertSame('cancelled', $appointment->fresh()->status);
            $this->assertSame($status, $request->fresh()->status);
        }
    }

    /** @test */
    public function repeated_appointment_cancellation_does_not_create_duplicate_staff_or_cashier_notices()
    {
        $student = $this->makeUserWithRole('student');
        $registrar = $this->makeUserWithRole('registrar');
        $cashier = $this->makeUserWithRole('cashier');
        $appointment = Appointment::factory()->create(['user_id' => $student->id, 'status' => 'pending']);
        DocumentRequest::create([
            'user_id' => $student->id,
            'appointment_id' => $appointment->id,
            'document_type' => 'tor',
            'status' => 'submitted',
            'fee' => 50,
        ]);

        $this->actingAs($student)->post(route('portal.appointments.cancel', $appointment));
        $this->actingAs($student)->post(route('portal.appointments.cancel', $appointment));

        $this->assertSame(1, UserNotification::where('user_id', $registrar->id)->where('title', 'Appointment Cancelled')->count());
        $this->assertSame(1, UserNotification::where('user_id', $cashier->id)->where('title', 'Appointment Cancelled for Document Request')->count());
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
    public function student_cannot_see_or_submit_cancel_for_released_document_requests()
    {
        $student = $this->makeUserWithRole('student');
        $slot = $this->makeSlot();
        $appointment = Appointment::factory()->create([
            'user_id' => $student->id,
            'appointment_slot_id' => $slot->id,
            'status' => 'pending',
        ]);
        DocumentRequest::create([
            'user_id' => $student->id,
            'appointment_id' => $appointment->id,
            'document_type' => 'tor',
            'copies' => 1,
            'status' => 'released',
            'fee' => 50,
            'payment_method' => 'walk_in',
            'payment_status' => 'verified',
        ]);

        $this->actingAs($student)
            ->get(route('portal.appointments.index'))
            ->assertOk()
            ->assertDontSee('Cancel booking');

        $this->actingAs($student)
            ->from(route('portal.appointments.index'))
            ->post(route('portal.appointments.cancel', $appointment))
            ->assertSessionHasErrors('error');

        $this->assertSame('pending', $appointment->fresh()->status);
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

        $response->assertRedirect(route('registrar.documents.index'));
        $this->assertSame('confirmed', $appointment->fresh()->status);
        $this->assertDatabaseHas('document_requests', [
            'appointment_id' => $appointment->id,
            'user_id' => $appointment->user_id,
            'status' => 'submitted',
        ]);
    }

    /** @test */
    public function registrar_index_hides_confirm_action_for_past_slot_bookings()
    {
        $registrar = $this->makeUserWithRole('registrar');
        $slot = AppointmentSlot::factory()->create([
            'date' => now()->subDay()->toDateString(),
            'start_time' => '08:00:00',
            'end_time' => '09:00:00',
            'status' => 'open',
        ]);
        $appointment = Appointment::factory()->create([
            'status' => 'pending',
            'appointment_slot_id' => $slot->id,
        ]);

        $this->actingAs($registrar)
            ->get(route('registrar.appointments.index'))
            ->assertOk()
            ->assertSee('Unavailable')
            ->assertDontSee('rg-booking-confirm');

        $this->assertFalse($appointment->canBeConfirmed());
    }

    /** @test */
    public function confirming_a_document_pickup_tells_the_alumnus_what_to_do_next()
    {
        $registrar = $this->makeUserWithRole('registrar');
        $alumnus = $this->makeUserWithRole('alumni');
        $appointment = Appointment::factory()->create([
            'user_id' => $alumnus->id,
            'status' => 'pending',
        ]);
        $request = DocumentRequest::create([
            'user_id' => $alumnus->id,
            'appointment_id' => $appointment->id,
            'document_type' => 'tor',
            'copies' => 1,
            'status' => 'ready',
            'fee' => 50,
            'is_paid' => true,
            'payment_method' => 'walk_in',
            'payment_status' => 'verified',
        ]);

        $this->actingAs($registrar)
            ->post(route('registrar.appointments.confirm', $appointment))
            ->assertRedirect(route('registrar.documents.index'));

        $notification = UserNotification::where('user_id', $alumnus->id)
            ->where('title', 'Appointment Confirmed')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame(route('portal.documents.index'), $notification->link);
        $this->assertStringContainsString('Bring a valid ID and your payment receipt.', $notification->message);
        $this->assertStringContainsString('mark each request as released', $notification->message);

        $this->actingAs($alumnus)
            ->get(route('portal.documents.index'))
            ->assertOk()
            ->assertSee('Pickup:')
            ->assertSee(\Carbon\Carbon::parse($appointment->slot->date)->format('M j, Y'))
            ->assertSee('Confirmed.');

        $this->assertSame($appointment->id, $request->fresh()->appointment_id);
    }

    /** @test */
    public function pickup_booked_from_a_ready_document_request_remains_linked_after_confirmation()
    {
        $registrar = $this->makeUserWithRole('registrar');
        $alumnus = $this->makeUserWithRole('alumni');
        $slot = $this->makeSlot();
        $request = DocumentRequest::create([
            'user_id' => $alumnus->id,
            'document_type' => 'tor',
            'copies' => 1,
            'status' => 'ready',
            'fee' => 50,
            'is_paid' => true,
            'payment_method' => 'walk_in',
            'payment_status' => 'verified',
        ]);

        $this->actingAs($alumnus)
            ->get(route('portal.documents.index'))
            ->assertOk()
            ->assertSee('Choose a pickup time');

        $this->actingAs($alumnus)
            ->post(route('portal.documents.pickup-appointment', $request), [
                'appointment_slot_id' => $slot->id,
            ])
            ->assertRedirect(route('portal.documents.index'));

        $request->refresh();
        $this->assertNotNull($request->appointment_id);
        $appointment = Appointment::findOrFail($request->appointment_id);
        $this->assertSame($request->id, DocumentRequest::where('appointment_id', $appointment->id)->value('id'));

        $this->actingAs($registrar)
            ->post(route('registrar.appointments.confirm', $appointment))
            ->assertRedirect(route('registrar.documents.index'));

        $this->actingAs($registrar)
            ->get(route('registrar.documents.index'))
            ->assertOk()
            ->assertSee('Pickup')
            ->assertSee('Confirmed')
            ->assertSee(\Carbon\Carbon::parse($slot->date)->format('M d, Y'));

        $this->assertSame('ready', $request->fresh()->status);
        $this->assertSame('confirmed', $appointment->fresh()->status);
    }

    /** @test */
    public function confirming_a_legacy_booking_links_the_single_matching_ready_request()
    {
        $registrar = $this->makeUserWithRole('registrar');
        $alumnus = $this->makeUserWithRole('alumni');
        $appointment = Appointment::factory()->create([
            'user_id' => $alumnus->id,
            'document_type' => 'transcript',
            'status' => 'pending',
        ]);
        $request = DocumentRequest::create([
            'user_id' => $alumnus->id,
            'document_type' => 'tor',
            'copies' => 1,
            'status' => 'ready',
            'fee' => 50,
            'is_paid' => true,
            'payment_method' => 'walk_in',
            'payment_status' => 'verified',
        ]);

        $this->actingAs($registrar)
            ->post(route('registrar.appointments.confirm', $appointment))
            ->assertRedirect(route('registrar.documents.index'));

        $this->assertSame($appointment->id, $request->fresh()->appointment_id);
        $this->assertSame('confirmed', $appointment->fresh()->status);

        $this->actingAs($registrar)
            ->get(route('registrar.documents.index'))
            ->assertOk()
            ->assertSee('Confirmed');
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
