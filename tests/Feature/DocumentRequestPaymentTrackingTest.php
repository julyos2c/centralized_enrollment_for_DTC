<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\DocumentRequest;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

    public function test_alumni_can_submit_gcash_proof_as_pending_without_marking_request_paid(): void
    {
        $alumni = $this->makeUserWithRole('alumni');
        $cashier = $this->makeUserWithRole('cashier');
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

        $documentRequest->refresh();
        $this->assertSame('verified', $documentRequest->payment_status);
        $this->assertTrue($documentRequest->is_paid);
        $this->assertSame($cashier->id, $documentRequest->verified_by);
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
            ->assertOk()->assertSee('Pay online using the official GCash QR');
        $this->actingAs($alumni)->get(route('portal.documents.index'))
            ->assertOk()->assertSee('Payment under review');
        $this->actingAs($cashier)->get(route('cashier.document-payments.index'))
            ->assertOk()->assertSee('Document Fee Payments');
        $this->actingAs($cashier)->get(route('cashier.document-payments.show', $documentRequest))
            ->assertOk()->assertSee('GCASH-REF-300');
    }

    public function test_cashier_can_verify_a_walk_in_payment_with_exact_amount_and_manual_receipt(): void
    {
        $cashier = $this->makeUserWithRole('cashier');
        $alumni = $this->makeUserWithRole('alumni');
        $documentRequest = $this->makeDocumentRequest($alumni, ['payment_method' => 'walk_in']);

        $response = $this->actingAs($cashier)->post(route('cashier.document-payments.verify-walk-in', $documentRequest), [
            'amount_received' => '50.00',
            'receipt_no' => 'DTC-OR-2026-1001',
        ]);

        $response->assertRedirect(route('cashier.document-payments.show', $documentRequest));
        $documentRequest->refresh();
        $this->assertSame('verified', $documentRequest->payment_status);
        $this->assertTrue($documentRequest->is_paid);
        $this->assertSame('50.00', $documentRequest->amount_received);
        $this->assertSame('DTC-OR-2026-1001', $documentRequest->receipt_no);
        $this->assertSame($cashier->id, $documentRequest->verified_by);
        $this->assertNotNull($documentRequest->verified_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'document_payment.verified', 'user_id' => $cashier->id]);
    }

    public function test_cashier_cannot_verify_an_inconsistent_amount_or_verify_twice(): void
    {
        $cashier = $this->makeUserWithRole('cashier');
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
    }

    public function test_cashier_can_reject_and_alumni_can_resubmit_gcash_payment(): void
    {
        $cashier = $this->makeUserWithRole('cashier');
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

        $response = $this->actingAs($alumni)->post(route('portal.documents.payment.submit', $documentRequest), [
            'reference_number' => 'NEW-REF',
            'proof_of_payment' => UploadedFile::fake()->image('new-proof.png'),
        ]);

        $response->assertRedirect(route('portal.documents.index'))->assertSessionHasNoErrors();
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
