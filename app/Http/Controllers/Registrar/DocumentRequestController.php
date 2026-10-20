<?php

namespace App\Http\Controllers\Registrar;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\DocumentRequest;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DocumentRequestController extends Controller
{
    public function index()
    {
        $requests = DocumentRequest::with(['user', 'appointment.slot'])
            ->latest()
            ->paginate(10);

        return view('registrar.documents.index', compact('requests'));
    }

    public function updateStatus(Request $request, DocumentRequest $documentRequest)
    {
        $validated = $request->validate([
            'status'  => ['required', 'in:submitted,processing,ready,released'],
            'remarks' => ['nullable', 'string', 'max:500'],
        ]);

        $messages = [
            'processing' => 'Your document request is now being processed.',
            'released' => 'Your document has been released. Thank you!',
        ];

        $change = DB::transaction(function () use ($request, $documentRequest, $validated) {
                $locked = DocumentRequest::whereKey($documentRequest->id)->lockForUpdate()->firstOrFail();
                $next = $validated['status'];
                $allowed = [
                    'submitted' => 'processing',
                    'processing' => 'ready',
                    'ready' => 'released',
                ];

                if (($allowed[$locked->status] ?? null) !== $next) {
                    throw ValidationException::withMessages([
                        'status' => $locked->status === $next
                            ? 'This request already has that status.'
                            : 'Invalid status change. Requests must move from submitted to processing, then ready, then released.',
                    ]);
                }

                if ($next === 'released' && (! $locked->is_paid || $locked->payment_status !== 'verified')) {
                    throw ValidationException::withMessages([
                        'status' => 'This document cannot be released until the Cashier has verified its payment.',
                    ]);
                }

                $previous = $locked->status;
                $locked->update([
                    'status' => $next,
                    'remarks' => $validated['remarks'] ?? $locked->remarks,
                ]);

                AuditLog::create([
                    'user_id' => auth()->id(),
                    'action' => 'document_request.status_changed',
                    'description' => "Document request #{$locked->id} status changed from {$previous} to {$next}.",
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'metadata' => [
                        'document_request_id' => $locked->id,
                        'previous_status' => $previous,
                        'new_status' => $next,
                        'remarks' => $validated['remarks'] ?? null,
                    ],
                ]);

                return $locked->fresh(['user']);
        });

        $message = $change->status === 'ready'
            ? $this->readyMessage($change)
            : $messages[$change->status];
        $tone = $change->status === 'ready' && $change->is_paid && $change->payment_status === 'verified' ? 'success' : 'info';
        $destination = $change->user->hasRole('alumni')
            ? route('portal.documents.index')
            : route('portal.appointments.index');
        $notificationSaved = NotificationService::sendSafely($change->user, 'Document Request Update', $message, $tone, $destination);
        $emailQueued = NotificationService::emailDocumentRequestSafely($change->user, $change, $message);

        $cashierNotified = true;
        if ($change->status === 'ready') {
            $paymentState = match ($change->payment_status) {
                'verified' => 'payment verified',
                'pending_verification' => 'GCash payment awaiting verification',
                'rejected' => 'GCash payment rejected; corrected proof is needed',
                default => 'payment due at the Cashier',
            };
            $cashierMessage = 'Document request #' . $change->id . ' for ' . $change->document_label
                . ' (' . $change->user->name . ') is ready. ' . ucfirst($paymentState)
                . '; fee due: ₱' . number_format((float) $change->fee, 2) . '.';
            foreach (User::role(['cashier', 'admin'])->get() as $cashier) {
                $saved = NotificationService::sendSafely(
                    $cashier,
                    'Document Ready for Pickup',
                    $cashierMessage,
                    $change->payment_status === 'verified' ? 'success' : 'warning',
                    route('cashier.document-payments.show', $change)
                );
                $cashierNotified = $saved && $cashierNotified;
            }
        }

        return redirect()->route('registrar.documents.index')
            ->with('success', 'Request status updated.' . (! $notificationSaved ? ' Applicant in-app notification could not be saved; the failure was logged.' : '') . (! $emailQueued ? ' Email could not be queued; the failure was logged.' : '') . (! $cashierNotified ? ' Cashier notification could not be saved; the failure was logged.' : ''));
    }

    private function readyMessage(DocumentRequest $documentRequest): string
    {
        $document = $documentRequest->document_label;
        if ($documentRequest->payment_status === 'verified' && $documentRequest->is_paid) {
            return "Your {$document} is ready for pickup. Your payment is verified. Visit the Registrar's Office and bring a valid ID.";
        }
        if ($documentRequest->payment_status === 'pending_verification') {
            return "Your {$document} is ready. The Cashier is still verifying your GCash payment; you'll be notified when it is confirmed.";
        }
        if ($documentRequest->payment_status === 'rejected') {
            return "Your {$document} is ready, but the Cashier could not verify your payment. Review the notice in your portal and submit corrected GCash details or pay at the Cashier before pickup.";
        }

        return "Your {$document} is ready. Pay the ₱" . number_format((float) $documentRequest->fee, 2)
            . " document fee at the Cashier before pickup. Bring a valid ID.";
    }
}
