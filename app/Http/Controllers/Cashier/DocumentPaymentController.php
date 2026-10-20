<?php

namespace App\Http\Controllers\Cashier;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\DocumentRequest;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class DocumentPaymentController extends Controller
{
    public function index(Request $request)
    {
        $filter = $request->query('status', 'all');
        $allowed = ['all', 'unpaid', 'pending_verification', 'rejected', 'verified'];
        abort_unless(in_array($filter, $allowed, true), 404);

        $search = trim((string) $request->query('search', ''));
        $query = DocumentRequest::with('user')
            ->where('status', '!=', 'cancelled')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('id', ctype_digit($search) ? (int) $search : -1)
                        ->orWhere('reference_number', 'like', '%' . $search . '%')
                        ->orWhereHas('user', function ($userQuery) use ($search) {
                            $userQuery->where('name', 'like', '%' . $search . '%')
                                ->orWhere('email', 'like', '%' . $search . '%');
                        });
                });
            })
            ->latest();
        if ($filter !== 'all') {
            $query->where('payment_status', $filter);
        }

        $requests = $query->paginate(15)->withQueryString();
        $counts = DocumentRequest::query()
            ->where('status', '!=', 'cancelled')
            ->selectRaw('payment_status, COUNT(*) as total')
            ->groupBy('payment_status')
            ->pluck('total', 'payment_status');

        return view('cashier.document-payments.index', compact('requests', 'counts', 'filter'));
    }

    public function show(DocumentRequest $documentRequest)
    {
        $documentRequest->load(['user', 'verifier', 'rejecter']);
        return view('cashier.document-payments.show', compact('documentRequest'));
    }

    public function viewProof(DocumentRequest $documentRequest)
    {
        $path = $documentRequest->proof_of_payment;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return response()->file(Storage::disk('local')->path($path), [
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function verifyWalkIn(Request $request, DocumentRequest $documentRequest)
    {
        $validated = $request->validate([
            'amount_received' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'receipt_no' => ['required', 'string', 'max:100'],
        ]);

        $this->verifyPayment($request, $documentRequest, 'walk_in', $validated);
        $documentRequest->refresh();

        $message = $documentRequest->status === 'ready'
            ? 'Your payment was recorded and your document is ready for pickup at the Registrar’s Office.'
            : 'The Cashier recorded your walk-in payment for document request #' . $documentRequest->id . '.';
        NotificationService::sendSafely(
            $documentRequest->user,
            'Document Fee Recorded',
            $message,
            'success',
            $this->requesterDestination($documentRequest)
        );
        NotificationService::emailDocumentRequestSafely($documentRequest->user, $documentRequest, $message);

        return redirect()->route('cashier.document-payments.show', $documentRequest)
            ->with('success', 'Walk-in payment recorded and verified.');
    }

    public function verifyGcash(Request $request, DocumentRequest $documentRequest)
    {
        $validated = $request->validate([
            'amount_received' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'receipt_no' => ['nullable', 'string', 'max:100'],
        ]);

        $this->verifyPayment($request, $documentRequest, 'gcash_qr', $validated);
        $documentRequest->refresh();

        $message = $documentRequest->status === 'ready'
            ? 'Your GCash payment is verified and your document is ready for pickup at the Registrar’s Office.'
            : 'The Cashier verified your GCash payment for document request #' . $documentRequest->id . '.';
        NotificationService::sendSafely(
            $documentRequest->user,
            'Document GCash Payment Verified',
            $message,
            'success',
            $this->requesterDestination($documentRequest)
        );
        NotificationService::emailDocumentRequestSafely($documentRequest->user, $documentRequest, $message);

        return redirect()->route('cashier.document-payments.show', $documentRequest)
            ->with('success', 'GCash payment verified against the received funds.');
    }

    private function verifyPayment(Request $request, DocumentRequest $documentRequest, string $method, array $validated): void
    {
        DB::transaction(function () use ($request, $documentRequest, $method, $validated) {
            $locked = DocumentRequest::whereKey($documentRequest->id)->lockForUpdate()->firstOrFail();
            $expectedStatus = $method === 'gcash_qr' ? 'pending_verification' : 'unpaid';

            if ($locked->status === 'cancelled' || $locked->payment_method !== $method || $locked->payment_status !== $expectedStatus || $locked->is_paid) {
                throw ValidationException::withMessages([
                    'payment' => 'This document payment is no longer eligible for verification.',
                ]);
            }

            if ($method === 'gcash_qr' && (! $locked->proof_of_payment || ! Storage::disk('local')->exists($locked->proof_of_payment))) {
                throw ValidationException::withMessages(['payment' => 'The submitted payment proof is unavailable.']);
            }

            $received = round((float) $validated['amount_received'], 2);
            if ($received !== round((float) $locked->fee, 2)) {
                throw ValidationException::withMessages([
                    'amount_received' => 'The amount received must exactly match the fee due (' . number_format((float) $locked->fee, 2) . ').',
                ]);
            }

            $locked->update([
                'payment_status' => 'verified',
                'is_paid' => true,
                'amount_received' => $received,
                'receipt_no' => $validated['receipt_no'] ?? null,
                'verified_by' => Auth::id(),
                'verified_at' => now(),
                'payment_rejection_reason' => null,
                'rejected_by' => null,
                'rejected_at' => null,
            ]);

            $this->audit($request, $locked, 'document_payment.verified', "Verified {$method} payment of PHP {$received} for document request #{$locked->id}.");
            NotificationService::enqueueDocumentPaymentVerified($locked);
        });
    }

    public function reject(Request $request, DocumentRequest $documentRequest)
    {
        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($request, $documentRequest, $validated) {
            $locked = DocumentRequest::whereKey($documentRequest->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === 'cancelled' || $locked->payment_method !== 'gcash_qr' || $locked->payment_status !== 'pending_verification' || $locked->is_paid) {
                throw ValidationException::withMessages(['payment' => 'Only a pending GCash claim can be rejected.']);
            }

            $locked->update([
                'payment_status' => 'rejected',
                'payment_rejection_reason' => $validated['rejection_reason'],
                'rejected_by' => Auth::id(),
                'rejected_at' => now(),
            ]);

            $this->audit(
                $request,
                $locked,
                'document_payment.rejected',
                "Rejected GCash payment for document request #{$locked->id}.",
                ['rejection_reason' => $validated['rejection_reason']]
            );
        });

        $documentRequest->refresh();
        $requestPage = $documentRequest->user->hasRole('alumni') ? 'My Requests' : 'My Appointments';
        $message = 'The Cashier could not verify payment for document request #' . $documentRequest->id . '. Reason: ' . $validated['rejection_reason'] . ' You may submit corrected payment details from ' . $requestPage . '.';
        NotificationService::sendSafely(
            $documentRequest->user,
            'Document GCash Payment Needs Attention',
            $message,
            'danger',
            $this->requesterDestination($documentRequest)
        );
        NotificationService::emailDocumentRequestSafely($documentRequest->user, $documentRequest, $message);

        return redirect()->route('cashier.document-payments.show', $documentRequest)
            ->with('success', 'GCash claim rejected. The requester has been notified.');
    }

    private function audit(Request $request, DocumentRequest $documentRequest, string $action, string $description, array $extraMetadata = []): void
    {
        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => $action,
            'description' => $description,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'metadata' => array_merge([
                'document_request_id' => $documentRequest->id,
                'payment_status' => $documentRequest->payment_status,
                'amount_due' => $documentRequest->fee,
                'amount_received' => $documentRequest->amount_received,
                'receipt_no' => $documentRequest->receipt_no,
            ], $extraMetadata),
        ]);
    }

    private function requesterDestination(DocumentRequest $documentRequest): string
    {
        return $documentRequest->user->hasRole('alumni')
            ? route('portal.documents.index')
            : route('portal.appointments.index');
    }
}
