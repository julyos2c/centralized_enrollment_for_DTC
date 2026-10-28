<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\DocumentRequest;
use App\Models\DocumentPaymentNotificationOutbox;
use App\Models\Appointment;
use App\Models\AppointmentSlot;
use App\Models\Setting;
use App\Models\UserNotification;
use App\Mail\DocumentRequestUpdate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Event;
use Tests\SeedsRoles;
use Tests\TestCase;

class DocumentRequestPaymentTrackingTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        Storage::fake('local');
    }

    public function test_released_documents_are_read_only_in_ui_and_status_endpoint(): void
    {
        Mail::fake();
        $owner = $this->makeUserWithRole('alumni');
        $document = DocumentRequest::create([
            'user_id' => $owner->id, 'document_type' => 'certification', 'copies' => 1,
            'status' => 'released', 'fee' => 20, 'is_paid' => true,
            'payment_method' => 'walk_in', 'payment_status' => 'verified', 'remarks' => 'Collected',
        ]);
        $this->actingAs($this->makeUserWithRole('registrar'));
        $this->get(route('registrar.documents.index'))->assertOk()
            ->assertSee('Completed')
            ->assertDontSee('data-target="#statusModal'.$document->id.'"', false)
            ->assertDontSee('id="statusModal'.$document->id.'"', false);
        foreach (['submitted', 'processing', 'ready', 'released'] as $status) {
            $this->post(route('registrar.documents.status', $document), ['status' => $status, 'remarks' => 'Changed'])
                ->assertSessionHasErrors('status');
            $this->assertSame('released', $document->fresh()->status);
            $this->assertSame('Collected', $document->fresh()->remarks);
            $this->assertSame('verified', $document->fresh()->payment_status);
        }
        $this->assertSame(0, AuditLog::where('action', 'document_request.status_changed')->count());
        Mail::assertNothingSent();
    }

    public function test_alumni_can_submit_gcash_proof_as_pending_without_marking_request_paid(): void
    {
        $alumni = $this->makeUserWithRole('alumni');
        $cashier = $this->makeUserWithRole('cashier');
        $registrar = $this->makeUserWithRole('registrar');
        Storage::disk('local')->put('gcash/official-qr.png', 'configured-qr');
        Setting::set('gcash_qr_path', 'gcash/official-qr.png');

        $response = $this->actingAs($alumni)->post(route('portal.documents.store'), [
            'document_type' => 'tor',
            'copies' => 2,
            'payment_method' => 'gcash_qr',
            'reference_number' => 'GCASH-REF-103',
            'proof_of_payment' => UploadedFile::fake()->image('proof.png'),
        ]);

        $response->assertRedirect(route('portal.documents.index'))->assertSessionHasNoErrors();
        $documentRequest = DocumentRequest::firstOrFail();
        $this->assertSame('pending_verification', $documentRequest->payment_status);
        $this->assertFalse($documentRequest->is_paid);
        $this->assertSame('100.00', $documentRequest->fee);
        $this->assertSame('GCASH-REF-103', $documentRequest->reference_number);
        Storage::disk('local')->assertExists($documentRequest->proof_of_payment);
        $this->assertStringStartsWith('document-request-proofs/', $documentRequest->proof_of_payment);
        $this->assertDatabaseHas('user_notifications', ['user_id' => $cashier->id, 'title' => 'Document Payment Needs Verification']);
        $this->assertSame(0, $registrar->userNotifications()->where('title', 'Document Payment Verified')->count());
        $this->assertDatabaseCount('document_payment_notification_outbox', 0);
    }

    public function test_alumni_can_submit_multiple_documents_and_quantities_for_one_pickup_appointment(): void
    {
        $alumni = $this->makeUserWithRole('alumni');
        $slot = AppointmentSlot::factory()->create();

        $this->actingAs($alumni)->post(route('portal.documents.store'), [
            'documents' => [
                ['document_type' => 'tor', 'copies' => 1],
                ['document_type' => 'diploma', 'copies' => 1],
                ['document_type' => 'certification', 'copies' => 2],
            ],
            'appointment_slot_id' => $slot->id,
            'payment_method' => 'walk_in',
            'purpose' => 'Employment',
        ])->assertRedirect(route('portal.documents.index'))->assertSessionHasNoErrors();

        $appointment = Appointment::where('user_id', $alumni->id)->firstOrFail();
        $requests = DocumentRequest::where('appointment_id', $appointment->id)->orderBy('id')->get();
        $this->assertCount(3, $requests);
        $this->assertSame(['tor', 'diploma', 'certification'], $requests->pluck('document_type')->all());
        $this->assertSame(['50.00', '100.00', '60.00'], $requests->pluck('fee')->all());
        $this->assertSame([1, 1, 2], $requests->pluck('copies')->all());
        $this->assertSame('210.00', $appointment->payments()->where('is_document_bundle', true)->value('amount'));

        $this->actingAs($alumni)->get(route('portal.documents.index'))
            ->assertOk()
            ->assertSee('Appointment #' . $appointment->id)
            ->assertSee('Transcript of Records')
            ->assertSee('Diploma')
            ->assertSee('Certification')
            ->assertSee('Appointment combined total');
    }

    public function test_student_and_alumni_document_selection_use_the_shared_redesigned_cards(): void
    {
        $student = $this->makeUserWithRole('student');
        $alumni = $this->makeUserWithRole('alumni');

        $this->actingAs($student)->get(route('portal.appointments.create'))
            ->assertOk()
            ->assertSee('css/document-request-flow.css')
            ->assertSee('dr-document-options', false)
            ->assertSee('per copy')
            ->assertSee('data-copy-step="-1"', false)
            ->assertSee('data-document-subtotal', false);

        $this->actingAs($alumni)->get(route('portal.documents.create'))
            ->assertOk()
            ->assertSee('css/document-request-flow.css')
            ->assertSee('dr-document-options', false)
            ->assertSee('per copy')
            ->assertSee('data-copy-step="-1"', false)
            ->assertSee('data-document-subtotal', false);
    }

    public function test_alumni_cannot_mix_legacy_and_combined_document_fields_or_book_a_bundle_without_a_slot(): void
    {
        $alumni = $this->makeUserWithRole('alumni');

        $this->actingAs($alumni)->post(route('portal.documents.store'), [
            'document_type' => 'tor',
            'copies' => 1,
            'documents' => [['document_type' => 'diploma', 'copies' => 1]],
            'payment_method' => 'walk_in',
        ])->assertSessionHasErrors('documents');

        $this->actingAs($alumni)->post(route('portal.documents.store'), [
            'documents' => [
                ['document_type' => 'tor', 'copies' => 1],
                ['document_type' => 'diploma', 'copies' => 1],
            ],
            'payment_method' => 'walk_in',
        ])->assertSessionHasErrors('appointment_slot_id');

        $this->assertDatabaseCount('appointments', 0);
        $this->assertDatabaseCount('document_requests', 0);
    }

    public function test_alumni_cannot_submit_gcash_claim_without_a_configured_official_qr(): void
    {
        $alumni = $this->makeUserWithRole('alumni');

        $this->actingAs($alumni)->from(route('portal.documents.create'))
            ->post(route('portal.documents.store'), [
                'document_type' => 'tor',
                'copies' => 1,
                'payment_method' => 'gcash_qr',
                'reference_number' => 'GCASH-REF-104',
                'proof_of_payment' => UploadedFile::fake()->image('proof.png'),
            ])
            ->assertRedirect(route('portal.documents.create'))
            ->assertSessionHasErrors('payment_method');

        $this->assertDatabaseCount('document_requests', 0);
    }

    public function test_gcash_proof_must_have_an_allowed_detected_content_type(): void
    {
        $alumni = $this->makeUserWithRole('alumni');
        Storage::disk('local')->put('gcash/official-qr.png', 'configured-qr');
        Setting::set('gcash_qr_path', 'gcash/official-qr.png');

        $this->actingAs($alumni)->from(route('portal.documents.create'))
            ->post(route('portal.documents.store'), [
                'document_type' => 'tor',
                'copies' => 1,
                'payment_method' => 'gcash_qr',
                'reference_number' => 'GCASH-REF-105',
                'proof_of_payment' => UploadedFile::fake()->create('proof.txt', 20, 'text/plain'),
            ])
            ->assertRedirect(route('portal.documents.create'))
            ->assertSessionHasErrors('proof_of_payment');

        $this->assertDatabaseCount('document_requests', 0);
    }

    public function test_cashier_can_verify_gcash_only_after_entering_the_exact_received_amount(): void
    {
        $cashier = $this->makeUserWithRole('cashier');
        $registrar = $this->makeUserWithRole('registrar');
        $alumni = $this->makeUserWithRole('alumni');
        $documentRequest = $this->makeDocumentRequest($alumni, [
            'payment_method' => 'gcash_qr',
            'payment_status' => 'pending_verification',
            'reference_number' => 'GCASH-REF-200',
            'proof_of_payment' => 'document-request-proofs/gcash.png',
        ]);
        Storage::disk('local')->put($documentRequest->proof_of_payment, 'private-proof');
        $verifyUrl = route('cashier.document-payments.verify-gcash', $documentRequest);

        $this->actingAs($cashier)->from(route('cashier.document-payments.show', $documentRequest))
            ->post($verifyUrl, ['amount_received' => '55.00', 'receipt_no' => 'DTC-GC-200'])
            ->assertSessionHasErrors('amount_received');
        $this->assertSame('pending_verification', $documentRequest->fresh()->payment_status);
        $this->assertFalse($documentRequest->fresh()->is_paid);

        $this->actingAs($cashier)->post($verifyUrl, ['amount_received' => '50.00', 'receipt_no' => 'DTC-GC-200'])
            ->assertRedirect(route('cashier.document-payments.show', $documentRequest));

        $outboxEvent = DocumentPaymentNotificationOutbox::firstOrFail();
        $this->assertSame('pending', $outboxEvent->status);
        $this->artisan('document-payment-notifications:process')->assertExitCode(0);

        $documentRequest->refresh();
        $this->assertSame('verified', $documentRequest->payment_status);
        $this->assertTrue($documentRequest->is_paid);
        $this->assertSame($cashier->id, $documentRequest->verified_by);

        $notice = $registrar->userNotifications()->where('title', 'Document Payment Verified')->firstOrFail();
        $this->assertSame(route('registrar.documents.index'), $notice->link);
        $this->assertStringContainsString($alumni->name, $notice->message);
        $this->assertStringContainsString('Transcript of Records', $notice->message);
        $this->assertStringContainsString('PHP 50.00', $notice->message);
        $this->assertStringContainsString('GCash', $notice->message);
        $this->assertStringNotContainsString('private-proof', $notice->message);
        $this->assertStringNotContainsString('GCASH-REF-200', $notice->message);
        $this->actingAs($registrar)->get($notice->link)->assertOk();
    }

    public function test_alumni_and_cashier_payment_screens_render_with_private_qr_and_request_details(): void
    {
        $alumni = $this->makeUserWithRole('alumni');
        $cashier = $this->makeUserWithRole('cashier');
        Storage::disk('local')->put('gcash/official-qr.png', 'configured-qr');
        Setting::set('gcash_qr_path', 'gcash/official-qr.png');
        $documentRequest = $this->makeDocumentRequest($alumni, [
            'payment_method' => 'gcash_qr',
            'payment_status' => 'pending_verification',
            'reference_number' => 'GCASH-REF-300',
            'proof_of_payment' => 'document-request-proofs/proof.png',
        ]);
        Storage::disk('local')->put($documentRequest->proof_of_payment, 'private-proof');

        $this->actingAs($alumni)->get(route('portal.documents.create'))
            ->assertOk()->assertSee('Pay with GCash QR')->assertSee('Official DTC GCash QR code');
        $this->actingAs($alumni)->get(route('portal.documents.index'))
            ->assertOk()->assertSee('Payment under review');
        $this->actingAs($cashier)->get(route('cashier.document-payments.index'))
            ->assertOk()->assertSee('Document Fee Payments');
        $this->actingAs($cashier)->get(route('cashier.document-payments.show', $documentRequest))
            ->assertOk()->assertSee('GCASH-REF-300')->assertSee('css/cashier-payment-pages.css');
    }

    public function test_cashier_can_verify_a_walk_in_payment_with_exact_amount_and_manual_receipt(): void
    {
        $cashier = $this->makeUserWithRole('cashier');
        $registrar = $this->makeUserWithRole('registrar');
        $student = $this->makeUserWithRole('student');
        $documentRequest = $this->makeDocumentRequest($student, ['payment_method' => 'walk_in']);

        $response = $this->actingAs($cashier)->post(route('cashier.document-payments.verify-walk-in', $documentRequest), [
            'amount_received' => '50.00',
            'receipt_no' => 'DTC-OR-2026-1001',
        ]);

        $response->assertRedirect(route('cashier.document-payments.show', $documentRequest));
        $this->artisan('document-payment-notifications:process')->assertExitCode(0);
        $documentRequest->refresh();
        $this->assertSame('verified', $documentRequest->payment_status);
        $this->assertTrue($documentRequest->is_paid);
        $this->assertSame('50.00', $documentRequest->amount_received);
        $this->assertSame('DTC-OR-2026-1001', $documentRequest->receipt_no);
        $this->assertSame($cashier->id, $documentRequest->verified_by);
        $this->assertNotNull($documentRequest->verified_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'document_payment.verified', 'user_id' => $cashier->id]);

        $notice = $registrar->userNotifications()->where('title', 'Document Payment Verified')->firstOrFail();
        $this->assertSame(route('registrar.documents.index'), $notice->link);
        $this->assertStringContainsString($student->name, $notice->message);
        $this->assertStringContainsString('Transcript of Records', $notice->message);
        $this->assertStringContainsString('PHP 50.00', $notice->message);
        $this->assertStringContainsString('Cash / walk-in', $notice->message);
        $this->actingAs($registrar)->get($notice->link)->assertOk();
    }

    public function test_notification_persistence_failure_does_not_undo_verified_payment(): void
    {
        Mail::fake();
        $cashier = $this->makeUserWithRole('cashier');
        $this->makeUserWithRole('registrar');
        $alumni = $this->makeUserWithRole('alumni');
        $documentRequest = $this->makeDocumentRequest($alumni);
        $event = 'eloquent.creating: ' . UserNotification::class;
        Event::listen($event, function () {
            throw new \RuntimeException('Simulated notification persistence failure.');
        });

        try {
            $this->actingAs($cashier)->post(route('cashier.document-payments.verify-walk-in', $documentRequest), [
                'amount_received' => '50.00',
                'receipt_no' => 'DTC-OR-FAILURE',
            ])->assertRedirect(route('cashier.document-payments.show', $documentRequest));
        } finally {
            Event::forget($event);
        }

        $this->assertTrue($documentRequest->fresh()->is_paid);
        $this->assertSame('verified', $documentRequest->fresh()->payment_status);
        $this->assertSame(1, AuditLog::where('action', 'document_payment.verified')->count());
        $outboxEvent = DocumentPaymentNotificationOutbox::firstOrFail();
        $this->assertSame('pending', $outboxEvent->status);
        $this->assertSame(0, $outboxEvent->attempts);

        Event::listen($event, function () {
            throw new \RuntimeException('Simulated notification persistence failure.');
        });
        $this->artisan('document-payment-notifications:process')->assertExitCode(0);
        Event::forget($event);
        $outboxEvent->refresh();
        $this->assertSame('pending', $outboxEvent->status);
        $this->assertSame(1, $outboxEvent->attempts);
        $outboxEvent->update(['available_at' => now()->subSecond()]);

        $this->artisan('document-payment-notifications:process')->assertExitCode(0);
        $this->assertSame('delivered', $outboxEvent->fresh()->status);
        $this->assertSame(1, UserNotification::where('title', 'Document Payment Verified')->count());
    }

    public function test_outbox_marks_event_failed_after_bounded_delivery_attempts(): void
    {
        $cashier = $this->makeUserWithRole('cashier');
        $this->makeUserWithRole('registrar');
        $alumni = $this->makeUserWithRole('alumni');
        $request = $this->makeDocumentRequest($alumni);
        $this->actingAs($cashier)->post(route('cashier.document-payments.verify-walk-in', $request), [
            'amount_received' => '50.00', 'receipt_no' => 'DTC-OR-FAILED',
        ]);

        $event = DocumentPaymentNotificationOutbox::firstOrFail();
        $event->update(['max_attempts' => 1, 'available_at' => now()->subSecond()]);
        $listener = 'eloquent.creating: ' . UserNotification::class;
        Event::listen($listener, function () {
            throw new \RuntimeException('Simulated persistent storage failure.');
        });
        try {
            $this->artisan('document-payment-notifications:process')->assertExitCode(0);
        } finally {
            Event::forget($listener);
        }

        $this->assertSame('failed', $event->fresh()->status);
        $this->assertSame(1, $event->fresh()->attempts);
        $this->assertSame(\RuntimeException::class, $event->fresh()->last_error);
        $this->assertSame('verified', $request->fresh()->payment_status);
    }

    public function test_outbox_retry_after_acknowledgement_failure_creates_only_one_notification(): void
    {
        $cashier = $this->makeUserWithRole('cashier');
        $registrar = $this->makeUserWithRole('registrar');
        $alumni = $this->makeUserWithRole('alumni');
        $request = $this->makeDocumentRequest($alumni);
        $this->actingAs($cashier)->post(route('cashier.document-payments.verify-walk-in', $request), [
            'amount_received' => '50.00', 'receipt_no' => 'DTC-OR-ACK',
        ]);

        $event = DocumentPaymentNotificationOutbox::firstOrFail();
        $listener = 'eloquent.updating: ' . DocumentPaymentNotificationOutbox::class;
        Event::listen($listener, function ($outbox) {
            if ($outbox->status === 'delivered') {
                throw new \RuntimeException('Simulated acknowledgement failure.');
            }
        });
        try {
            $this->artisan('document-payment-notifications:process')->assertExitCode(0);
        } finally {
            Event::forget($listener);
        }

        $this->assertSame('pending', $event->fresh()->status);
        $this->assertSame(1, $event->fresh()->attempts);
        $this->assertSame(0, $registrar->userNotifications()->where('event_key', $event->event_key)->count());

        $event->update(['available_at' => now()->subSecond()]);
        $this->artisan('document-payment-notifications:process')->assertExitCode(0);
        $this->artisan('document-payment-notifications:process')->assertExitCode(0);

        $this->assertSame('delivered', $event->fresh()->status);
        $this->assertSame(1, $registrar->userNotifications()->where('event_key', $event->event_key)->count());
    }

    public function test_cashier_cannot_verify_an_inconsistent_amount_or_verify_twice(): void
    {
        $cashier = $this->makeUserWithRole('cashier');
        $registrar = $this->makeUserWithRole('registrar');
        $alumni = $this->makeUserWithRole('alumni');
        $documentRequest = $this->makeDocumentRequest($alumni);
        $route = route('cashier.document-payments.verify-walk-in', $documentRequest);

        $this->actingAs($cashier)->from(route('cashier.document-payments.show', $documentRequest))
            ->post($route, ['amount_received' => '49.00', 'receipt_no' => 'DTC-OR-1'])
            ->assertSessionHasErrors('amount_received');

        $this->assertFalse($documentRequest->fresh()->is_paid);

        $this->actingAs($cashier)->post($route, ['amount_received' => '50.00', 'receipt_no' => 'DTC-OR-1'])
            ->assertSessionHasNoErrors();

        $this->actingAs($cashier)->from(route('cashier.document-payments.show', $documentRequest))
            ->post($route, ['amount_received' => '50.00', 'receipt_no' => 'DTC-OR-2'])
            ->assertSessionHasErrors('payment');

        $this->assertSame(1, AuditLog::where('action', 'document_payment.verified')->count());
        $this->assertSame('DTC-OR-1', $documentRequest->fresh()->receipt_no);
        $this->assertSame(1, DocumentPaymentNotificationOutbox::where('document_request_id', $documentRequest->id)->count());
        $this->assertSame(0, $registrar->userNotifications()->where('title', 'Document Payment Verified')->count());
        $this->artisan('document-payment-notifications:process')->assertExitCode(0);
        $this->artisan('document-payment-notifications:process')->assertExitCode(0);
        $this->assertSame(1, $registrar->userNotifications()->where('title', 'Document Payment Verified')->count());
    }

    public function test_cashier_can_reject_and_alumni_can_resubmit_gcash_payment(): void
    {
        $cashier = $this->makeUserWithRole('cashier');
        $registrar = $this->makeUserWithRole('registrar');
        $alumni = $this->makeUserWithRole('alumni');
        Storage::disk('local')->put('gcash/official-qr.png', 'configured-qr');
        Setting::set('gcash_qr_path', 'gcash/official-qr.png');
        $documentRequest = $this->makeDocumentRequest($alumni, [
            'payment_method' => 'gcash_qr',
            'payment_status' => 'pending_verification',
            'reference_number' => 'OLD-REF',
            'proof_of_payment' => 'document-request-proofs/old.png',
        ]);
        Storage::disk('local')->put($documentRequest->proof_of_payment, 'old-proof');

        $this->actingAs($cashier)->post(route('cashier.document-payments.reject', $documentRequest), [
            'rejection_reason' => 'The reference does not match the receiving account records.',
        ])->assertSessionHasNoErrors();

        $documentRequest->refresh();
        $this->assertSame('rejected', $documentRequest->payment_status);
        $this->assertFalse($documentRequest->is_paid);
        $this->assertSame('The reference does not match the receiving account records.', $documentRequest->payment_rejection_reason);
        $this->assertSame($cashier->id, $documentRequest->rejected_by);
        $this->assertNotNull($documentRequest->rejected_at);
        $this->assertNull($documentRequest->verified_at);
        $this->assertSame(0, $registrar->userNotifications()->where('title', 'Document Payment Verified')->count());
        $this->assertDatabaseCount('document_payment_notification_outbox', 0);

        $response = $this->actingAs($alumni)->post(route('portal.documents.payment.submit', $documentRequest), [
            'reference_number' => 'NEW-REF',
            'proof_of_payment' => UploadedFile::fake()->image('new-proof.png'),
        ]);

        $response->assertRedirect(route('portal.payments.document', $documentRequest))->assertSessionHasNoErrors();
        $documentRequest->refresh();
        $this->assertSame('pending_verification', $documentRequest->payment_status);
        $this->assertSame('NEW-REF', $documentRequest->reference_number);
        $this->assertNull($documentRequest->payment_rejection_reason);
        $this->assertFalse($documentRequest->is_paid);
        $this->assertSame(1, AuditLog::where('action', 'document_payment.rejected')->count());
        $this->assertSame(1, AuditLog::where('action', 'document_payment.resubmitted')->count());
    }

    public function test_only_owner_and_cashier_can_open_private_payment_proof(): void
    {
        $alumni = $this->makeUserWithRole('alumni');
        $otherAlumni = $this->makeUserWithRole('alumni');
        $registrar = $this->makeUserWithRole('registrar');
        $cashier = $this->makeUserWithRole('cashier');
        $documentRequest = $this->makeDocumentRequest($alumni, [
            'payment_method' => 'gcash_qr',
            'payment_status' => 'pending_verification',
            'proof_of_payment' => 'document-request-proofs/private.png',
        ]);
        Storage::disk('local')->put($documentRequest->proof_of_payment, 'private-proof');
        $proofUrl = route('portal.documents.payment.proof', $documentRequest);

        $this->actingAs($otherAlumni)->get($proofUrl)->assertForbidden();
        $this->actingAs($alumni)->get($proofUrl)->assertOk();
        $this->actingAs($registrar)->get(route('cashier.document-payments.proof', $documentRequest))->assertForbidden();
        $this->actingAs($cashier)->get(route('cashier.document-payments.proof', $documentRequest))->assertOk();
    }

    public function test_release_is_blocked_until_payment_is_verified_and_allowed_afterward(): void
    {
        $registrar = $this->makeUserWithRole('registrar');
        $alumni = $this->makeUserWithRole('alumni');
        $documentRequest = $this->makeDocumentRequest($alumni, ['status' => 'ready']);
        $releaseUrl = route('registrar.documents.status', $documentRequest);

        $this->actingAs($registrar)->from(route('registrar.documents.index'))
            ->post($releaseUrl, ['status' => 'released'])
            ->assertSessionHasErrors('status');

        $this->assertSame('ready', $documentRequest->fresh()->status);

        $documentRequest->update(['is_paid' => true, 'payment_status' => 'verified']);
        $this->actingAs($registrar)->post($releaseUrl, ['status' => 'released'])
            ->assertRedirect(route('registrar.documents.index'));

        $this->assertSame('released', $documentRequest->fresh()->status);
    }

    public function test_non_cashier_cannot_access_document_payment_verification(): void
    {
        $registrar = $this->makeUserWithRole('registrar');
        $alumni = $this->makeUserWithRole('alumni');
        $documentRequest = $this->makeDocumentRequest($alumni);

        $this->actingAs($registrar)
            ->post(route('cashier.document-payments.verify-walk-in', $documentRequest), [
                'amount_received' => '50.00', 'receipt_no' => 'DTC-OR-1',
            ])->assertForbidden();
    }

    public function test_document_request_routes_are_not_exposed_to_student_or_new_applicant_roles(): void
    {
        $student = $this->makeUserWithRole('student');
        $applicant = $this->makeUserWithRole('new_applicant');

        $this->actingAs($student)->get(route('portal.documents.index'))->assertForbidden();
        $this->actingAs($applicant)->get(route('portal.documents.create'))->assertForbidden();
    }

    public function test_request_submission_sends_only_one_admin_alert_and_queues_applicant_email(): void
    {
        Mail::fake();
        $admin = $this->makeUserWithRole('admin');
        $registrar = $this->makeUserWithRole('registrar');
        $alumni = $this->makeUserWithRole('alumni');

        $this->actingAs($alumni)->post(route('portal.documents.store'), [
            'document_type' => 'tor',
            'copies' => 1,
            'payment_method' => 'walk_in',
        ])->assertRedirect(route('portal.documents.index'));

        $this->assertSame(1, $admin->userNotifications()->where('title', 'New Document Request')->count());
        $this->assertSame(1, $registrar->userNotifications()->where('title', 'New Document Request')->count());
        $this->assertSame(1, $admin->userNotifications()->count());
        Mail::assertQueued(DocumentRequestUpdate::class, fn ($mail) => $mail->hasTo($alumni->email));
    }

    public function test_status_flow_is_forward_only_and_audits_only_successful_changes(): void
    {
        $registrar = $this->makeUserWithRole('registrar');
        $alumni = $this->makeUserWithRole('alumni');
        $request = $this->makeDocumentRequest($alumni);
        $url = route('registrar.documents.status', $request);

        $this->actingAs($registrar)->post($url, ['status' => 'processing', 'remarks' => 'Started'])->assertRedirect(route('registrar.documents.index'));
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $registrar->id,
            'action' => 'document_request.status_changed',
            'description' => "Document request #{$request->id} status changed from submitted to processing.",
        ]);
        $log = AuditLog::where('action', 'document_request.status_changed')->firstOrFail();
        $this->assertSame($request->id, $log->metadata['document_request_id']);
        $this->assertSame('submitted', $log->metadata['previous_status']);
        $this->assertSame('processing', $log->metadata['new_status']);
        $this->assertSame('Started', $log->metadata['remarks']);

        $this->actingAs($registrar)->from(route('registrar.documents.index'))->post($url, ['status' => 'processing'])
            ->assertRedirect(route('registrar.documents.index'))->assertSessionHasErrors('status');
        $this->actingAs($registrar)->from(route('registrar.documents.index'))->post($url, ['status' => 'submitted'])
            ->assertRedirect(route('registrar.documents.index'))->assertSessionHasErrors('status');
        $this->assertSame(1, AuditLog::where('action', 'document_request.status_changed')->count());
        $this->assertSame(1, $alumni->userNotifications()->where('title', 'Document Request Update')->count());
    }

    public function test_marking_student_document_ready_notifies_student_and_cashier_with_payment_next_steps(): void
    {
        Mail::fake();
        $registrar = $this->makeUserWithRole('registrar');
        $student = $this->makeUserWithRole('student');
        $cashier = $this->makeUserWithRole('cashier');
        $request = $this->makeDocumentRequest($student, [
            'document_type' => 'certification',
            'status' => 'processing',
            'payment_method' => 'gcash_qr',
            'payment_status' => 'pending_verification',
        ]);

        $this->actingAs($registrar)->post(route('registrar.documents.status', $request), [
            'status' => 'ready',
        ])->assertRedirect(route('registrar.documents.index'))
            ->assertSessionHas('success');

        $studentNotice = UserNotification::where('user_id', $student->id)
            ->where('title', 'Document Request Update')->firstOrFail();
        $this->assertSame(route('portal.appointments.index'), $studentNotice->link);
        $this->assertStringContainsString('Cashier is still verifying your GCash payment', $studentNotice->message);

        $cashierNotice = UserNotification::where('user_id', $cashier->id)
            ->where('title', 'Document Ready for Pickup')->firstOrFail();
        $this->assertSame(route('cashier.document-payments.show', $request), $cashierNotice->link);
        $this->assertStringContainsString('GCash payment awaiting verification', $cashierNotice->message);

        Mail::assertQueued(DocumentRequestUpdate::class, 1);
    }

    public function test_linked_pickup_appointment_waits_for_ready_and_verified_request_and_appears_in_history(): void
    {
        $registrar = $this->makeUserWithRole('registrar');
        $alumni = $this->makeUserWithRole('alumni');
        $slot = AppointmentSlot::create([
            'date' => today()->addDay()->toDateString(),
            'start_time' => '09:00:00',
            'end_time' => '09:30:00',
            'max_bookings' => 1,
        ]);
        $appointment = Appointment::create([
            'user_id' => $alumni->id,
            'appointment_slot_id' => $slot->id,
            'document_type' => 'tor',
            'status' => 'pending',
        ]);
        $request = $this->makeDocumentRequest($alumni, ['appointment_id' => $appointment->id]);

        $this->actingAs($registrar)->post(route('registrar.appointments.confirm', $appointment))
            ->assertRedirect()->assertSessionHas('error');
        $this->assertSame('pending', $appointment->fresh()->status);

        $request->update(['status' => 'ready', 'is_paid' => true, 'payment_status' => 'verified']);
        $this->actingAs($registrar)->post(route('registrar.appointments.confirm', $appointment))
            ->assertRedirect(route('registrar.documents.index'));
        $this->assertSame('confirmed', $appointment->fresh()->status);

        $this->actingAs($alumni)->get(route('portal.documents.index'))
            ->assertOk()
            ->assertSee('Pickup:')
            ->assertSee(today()->addDay()->format('M j, Y'))
            ->assertSee('Confirmed.');
    }

    public function test_confirmed_pickup_is_blocked_if_its_slot_is_unavailable(): void
    {
        $registrar = $this->makeUserWithRole('registrar');
        $alumni = $this->makeUserWithRole('alumni');
        $slot = AppointmentSlot::create([
            'date' => today()->addDay()->toDateString(),
            'start_time' => '09:00:00',
            'end_time' => '09:30:00',
            'max_bookings' => 1,
            'status' => 'closed',
        ]);
        $appointment = Appointment::create([
            'user_id' => $alumni->id,
            'appointment_slot_id' => $slot->id,
            'document_type' => 'tor',
            'status' => 'pending',
        ]);
        $this->makeDocumentRequest($alumni, [
            'appointment_id' => $appointment->id,
            'status' => 'ready',
            'is_paid' => true,
            'payment_status' => 'verified',
        ]);

        $this->actingAs($registrar)->post(route('registrar.appointments.confirm', $appointment))
            ->assertRedirect()->assertSessionHas('error');
        $this->assertSame('pending', $appointment->fresh()->status);
    }

    public function test_email_queue_failure_does_not_report_a_committed_request_as_failed(): void
    {
        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('mail offline'));
        $registrar = $this->makeUserWithRole('registrar');
        $alumni = $this->makeUserWithRole('alumni');
        $request = $this->makeDocumentRequest($alumni);

        $this->actingAs($registrar)->from(route('registrar.documents.index'))
            ->post(route('registrar.documents.status', $request), ['status' => 'processing'])
            ->assertRedirect(route('registrar.documents.index'))
            ->assertSessionHas('success', 'Request status updated. Email could not be queued; the failure was logged.');

        $this->assertSame('processing', $request->fresh()->status);
        $this->assertSame(1, AuditLog::where('action', 'document_request.status_changed')->count());
    }

    public function test_cancelled_request_is_hidden_from_cashier_queue_and_cannot_be_paid(): void
    {
        $cashier = $this->makeUserWithRole('cashier');
        $alumni = $this->makeUserWithRole('alumni');
        $request = $this->makeDocumentRequest($alumni, [
            'status' => 'cancelled',
            'payment_status' => 'unpaid',
        ]);

        $this->actingAs($cashier)
            ->get(route('cashier.document-payments.index'))
            ->assertOk()
            ->assertSee('No document payments match this filter.')
            ->assertDontSee('Review / record');

        $this->actingAs($cashier)
            ->from(route('cashier.document-payments.show', $request))
            ->post(route('cashier.document-payments.verify-walk-in', $request), [
                'amount_received' => '50.00',
                'receipt_no' => 'OR-CANCELLED',
            ])
            ->assertRedirect(route('cashier.document-payments.show', $request))
            ->assertSessionHasErrors('payment');

        $this->assertFalse($request->fresh()->is_paid);
        $this->assertSame('unpaid', $request->fresh()->payment_status);
    }

    public function test_alumni_history_has_a_cancelled_filter_and_preserves_cancelled_request_details(): void
    {
        $alumni = $this->makeUserWithRole('alumni');
        $appointment = Appointment::factory()->create([
            'user_id' => $alumni->id,
            'status' => 'cancelled',
            'remarks' => 'Pickup appointment cancelled.',
        ]);
        $request = $this->makeDocumentRequest($alumni, [
            'appointment_id' => $appointment->id,
            'document_type' => 'diploma',
            'status' => 'cancelled',
            'payment_status' => 'unpaid',
        ]);
        $cancelledPickup = Appointment::factory()->cancelled()->create(['user_id' => $alumni->id]);
        $activeRequest = $this->makeDocumentRequest($alumni, [
            'appointment_id' => $cancelledPickup->id,
            'document_type' => 'certification',
            'status' => 'ready',
            'is_paid' => true,
            'payment_status' => 'verified',
        ]);

        $this->actingAs($alumni)->get(route('portal.documents.index'))
            ->assertOk()
            ->assertSee('data-filter="cancelled"', false)
            ->assertSee('data-group="cancelled"', false)
            ->assertSee('data-group="ready"', false)
            ->assertSee('Diploma')
            ->assertSee('Certification')
            ->assertSee('This document request was cancelled and is kept here for your records.')
            ->assertSee('Pickup appointment cancelled.')
            ->assertSee('Choose a new pickup time')
            ->assertSee('Payment details');

        $this->assertSame('cancelled', $request->fresh()->status);
        $this->assertSame('ready', $activeRequest->fresh()->status);
    }

    private function makeDocumentRequest($user, array $attributes = []): DocumentRequest
    {
        return DocumentRequest::create(array_merge([
            'user_id' => $user->id,
            'document_type' => 'tor',
            'copies' => 1,
            'status' => 'submitted',
            'fee' => 50,
            'is_paid' => false,
            'payment_method' => 'walk_in',
            'payment_status' => 'unpaid',
        ], $attributes));
    }
}
