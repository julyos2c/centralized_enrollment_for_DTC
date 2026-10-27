<?php

namespace App\Http\Controllers\Cashier;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Appointment;
use App\Models\AppointmentPayment;
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
            ->where(fn ($q) => $q->where('fee', '>', 0)->orWhere('is_paid', true))
            ->where('status', '!=', 'cancelled')
            ->whereDoesntHave('appointment.payments', fn ($payments) => $payments->where('is_document_bundle', true))
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

        $requests = $query->paginate(\App\Support\TablePagination::perPage(15))->withQueryString();
        $bundlesQuery = AppointmentPayment::with(['appointment.user', 'appointment.slot', 'appointment.documentRequests'])
            ->where('is_document_bundle', true)
            ->whereHas('appointment');
        if ($filter !== 'all') {
            $bundlesQuery->where(function ($query) use ($filter) {
                if ($filter === 'unpaid') $query->where('payment_method', 'walk_in')->where('status', 'pending');
                elseif ($filter === 'pending_verification') $query->where('payment_method', 'gcash')->where('status', 'pending');
                else $query->where('status', $filter);
            });
        }
        if ($search !== '') {
            $bundlesQuery->where(function ($query) use ($search) {
                $query->whereHas('appointment.user', function ($userQuery) use ($search) {
                    $userQuery->where('name', 'like', '%' . $search . '%')
                        ->orWhere('email', 'like', '%' . $search . '%');
                })->orWhere('reference_number', 'like', '%' . $search . '%');
            });
        }
        $bundles = $bundlesQuery->latest()->paginate(\App\Support\TablePagination::perPage(10), ['*'], 'bundles_page')->withQueryString();
        $counts = DocumentRequest::query()->where(fn ($q) => $q->where('fee', '>', 0)->orWhere('is_paid', true))
            ->where('status', '!=', 'cancelled')
            ->whereDoesntHave('appointment.payments', fn ($payments) => $payments->where('is_document_bundle', true))
            ->selectRaw('payment_status, COUNT(*) as total')
            ->groupBy('payment_status')
            ->pluck('total', 'payment_status')
            ->all();
        $bundleCounts = AppointmentPayment::query()
            ->where('is_document_bundle', true)
            ->whereHas('appointment')
            ->selectRaw('payment_method, status, COUNT(*) as total')
            ->groupBy('payment_method', 'status')
            ->get();
        foreach ($bundleCounts as $bundleCount) {
            $status = $bundleCount->status === 'pending'
                ? ($bundleCount->payment_method === 'gcash' ? 'pending_verification' : 'unpaid')
                : $bundleCount->status;
            $counts[$status] = ($counts[$status] ?? 0) + $bundleCount->total;
        }
        $counts = collect($counts);

        return view('cashier.document-payments.index', compact('requests', 'counts', 'filter', 'bundles'));
    }

    public function show(DocumentRequest $documentRequest)
    {
        $documentRequest->load(['user', 'verifier', 'rejecter', 'appointment.payments']);
        $bundlePayment = $documentRequest->appointment?->payments
            ->first(fn ($payment) => $payment->is_document_bundle);
        $documentRequests = $bundlePayment
            ? $documentRequest->appointment->documentRequests()->get()
            : collect([$documentRequest]);
        return view('cashier.document-payments.show', compact('documentRequest', 'bundlePayment', 'documentRequests'));
    }

    public function viewProof(DocumentRequest $documentRequest)
    {
        $bundle = $documentRequest->appointment?->payments()
            ->where('is_document_bundle', true)->latest()->first();
        if ($bundle) {
            return $this->viewBundleProof($bundle);
        }
        $path = $documentRequest->proof_of_payment;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return response()->file(Storage::disk('local')->path($path), [
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function viewBundleProof(AppointmentPayment $payment)
    {
        abort_unless($payment->is_document_bundle, 404);
        abort_unless($payment->proof_of_payment && Storage::disk('local')->exists($payment->proof_of_payment), 404);

        return response()->file(Storage::disk('local')->path($payment->proof_of_payment), [
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function verifyBundle(Request $request, AppointmentPayment $payment)
    {
        $validated = $request->validate([
            'amount_received' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'receipt_no' => [$payment->payment_method === 'walk_in' ? 'required' : 'nullable', 'string', 'max:100'],
        ]);
        $requests = DB::transaction(function () use ($request, $payment, $validated) {
            $appointment = Appointment::whereKey($payment->appointment_id)->lockForUpdate()->firstOrFail();
            $lockedPayment = AppointmentPayment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            $expectedMethod = $lockedPayment->payment_method;
            if (! $lockedPayment->is_document_bundle
                || $lockedPayment->status !== 'pending'
                || ! in_array($expectedMethod, ['walk_in', 'gcash'], true)) {
                throw ValidationException::withMessages(['payment' => 'This bundled payment is no longer eligible for verification.']);
            }
            if ($expectedMethod === 'gcash' && (! $lockedPayment->proof_of_payment || ! Storage::disk('local')->exists($lockedPayment->proof_of_payment))) {
                throw ValidationException::withMessages(['payment' => 'The submitted payment proof is unavailable.']);
            }
            $requests = DocumentRequest::where('appointment_id', $appointment->id)->lockForUpdate()->get();
            $expectedDocumentPaymentStatus = $expectedMethod === 'gcash' ? 'pending_verification' : 'unpaid';
            $appointmentCancelled = $appointment->status === 'cancelled';
            if ($requests->isEmpty() || $requests->contains(fn ($item) =>
                !$item->paymentNotRequired() && (
                    ($appointmentCancelled ? $item->status === 'released' : in_array($item->status, ['cancelled', 'released'], true))
                    || $item->is_paid
                    || $item->payment_status !== $expectedDocumentPaymentStatus
                    || $item->payment_method !== ($expectedMethod === 'gcash' ? 'gcash_qr' : 'walk_in'))
            )) {
                throw ValidationException::withMessages(['payment' => 'The bundled document requests are no longer eligible for payment.']);
            }
            $received = round((float) $validated['amount_received'], 2);
            if ($received !== round((float) $lockedPayment->amount, 2)
                || round($requests->sum(fn ($item) => (float) $item->fee), 2) !== round((float) $lockedPayment->amount, 2)) {
                throw ValidationException::withMessages([
                    'amount_received' => 'The amount received must exactly match the bundled fee (' . number_format((float) $lockedPayment->amount, 2) . ').',
                ]);
            }
            $lockedPayment->update([
                'status' => 'verified',
                'receipt_no' => $validated['receipt_no'] ?? null,
                'verified_by' => Auth::id(),
                'verified_at' => now(),
                'rejection_reason' => null,
            ]);
            foreach ($requests as $documentRequest) {
                if ($documentRequest->paymentNotRequired()) continue;
                $documentRequest->update([
                    'payment_status' => 'verified',
                    'is_paid' => true,
                    'amount_received' => $documentRequest->fee,
                    'receipt_no' => $validated['receipt_no'] ?? null,
                    'verified_by' => Auth::id(),
                    'verified_at' => now(),
                    'payment_rejection_reason' => null,
                    'rejected_by' => null,
                    'rejected_at' => null,
                ]);
                $this->audit($request, $documentRequest, 'document_payment.bundle_verified', "Verified bundled payment for document request #{$documentRequest->id}.", [
                    'appointment_payment_id' => $lockedPayment->id,
                    'bundle_total' => $lockedPayment->amount,
                ]);
                NotificationService::enqueueDocumentPaymentVerified($documentRequest);
            }
            return $requests;
        });

        $first = $requests->first();
        $cancelled = $first->appointment?->status === 'cancelled';
        NotificationService::sendSafely(
            $first->user,
            'Bundled Document Payment Verified',
            $cancelled
                ? 'Your payment of ₱' . number_format((float) $payment->amount, 2) . ' was verified for cancelled appointment #' . $payment->appointment_id . '. Contact the Registrar about next steps.'
                : 'Your payment of ₱' . number_format((float) $payment->amount, 2) . ' was verified for all documents in appointment #' . $payment->appointment_id . '.',
            'success',
            $this->requesterDestination($first)
        );

        return redirect()->route('cashier.document-payments.show', $first)
            ->with('success', 'Bundled payment verified for all linked document requests.');
    }

    public function rejectBundle(Request $request, AppointmentPayment $payment)
    {
        $validated = $request->validate(['rejection_reason' => ['required', 'string', 'max:500']]);
        $requests = DB::transaction(function () use ($request, $payment, $validated) {
            $appointment = Appointment::whereKey($payment->appointment_id)->lockForUpdate()->firstOrFail();
            $lockedPayment = AppointmentPayment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            if (! $lockedPayment->is_document_bundle || $lockedPayment->payment_method !== 'gcash'
                || $lockedPayment->status !== 'pending') {
                throw ValidationException::withMessages(['payment' => 'Only a pending bundled GCash payment can be rejected.']);
            }
            $requests = DocumentRequest::where('appointment_id', $appointment->id)->lockForUpdate()->get();
            if ($requests->isEmpty()) {
                throw ValidationException::withMessages(['payment' => 'This bundled payment has no linked document requests.']);
            }
            $lockedPayment->update(['status' => 'rejected', 'rejection_reason' => $validated['rejection_reason'], 'verified_by' => Auth::id(), 'verified_at' => now()]);
            foreach ($requests as $documentRequest) {
                if ($documentRequest->paymentNotRequired()) continue;
                if ($documentRequest->payment_status !== 'pending_verification' || $documentRequest->is_paid) {
                    throw ValidationException::withMessages(['payment' => 'The bundled payment state no longer matches its document requests.']);
                }
                $documentRequest->update([
                    'payment_status' => 'rejected',
                    'payment_rejection_reason' => $validated['rejection_reason'],
                    'rejected_by' => Auth::id(),
                    'rejected_at' => now(),
                ]);
                $this->audit($request, $documentRequest, 'document_payment.bundle_rejected', "Rejected bundled payment for document request #{$documentRequest->id}.", [
                    'appointment_payment_id' => $lockedPayment->id,
                    'rejection_reason' => $validated['rejection_reason'],
                ]);
            }
            return $requests;
        });
        $first = $requests->first();
        NotificationService::sendSafely(
            $first->user,
            'Bundled GCash Payment Needs Attention',
            'The Cashier could not verify payment for appointment #' . $payment->appointment_id . '. Reason: ' . $validated['rejection_reason'],
            'danger',
            $this->requesterDestination($first)
        );

        return redirect()->route('cashier.document-payments.show', $first)
            ->with('success', 'Bundled GCash payment rejected. The requester has been notified.');
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
        if ($documentRequest->usesBundledPayment()) {
            throw ValidationException::withMessages(['payment' => 'This request is part of a bundled payment. Review or submit payment for the whole booking.']);
        }

        DB::transaction(function () use ($request, $documentRequest, $method, $validated) {
            $locked = DocumentRequest::whereKey($documentRequest->id)->lockForUpdate()->firstOrFail();
            $expectedStatus = $method === 'gcash_qr' ? 'pending_verification' : 'unpaid';

            if ($locked->paymentNotRequired() || $locked->status === 'cancelled' || $locked->payment_method !== $method || $locked->payment_status !== $expectedStatus || $locked->is_paid) {
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
        if ($documentRequest->usesBundledPayment()) {
            throw ValidationException::withMessages(['payment' => 'This request is part of a bundled payment. Review or submit payment for the whole booking.']);
        }

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
        if (!$documentRequest->user->hasAnyRole(['student', 'alumni'])) return route('portal.dashboard');
        return route('portal.payments.document', $documentRequest);
    }
}
