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
    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:200'],
            'status' => ['nullable', 'in:all,submitted,processing,ready,released,cancelled'],
        ]);
        $search = trim($filters['search'] ?? '');
        $status = $filters['status'] ?? 'all';
        $query = DocumentRequest::query();
        if ($search !== '') {
            $documentTypes = collect(['tor' => 'Transcript of Records', 'true_copy' => 'True Copy of Records', 'certification' => 'Certification', 'diploma' => 'Diploma'])
                ->filter(fn ($label) => stripos($label, $search) !== false)->keys()->all();
            $query->where(function ($matches) use ($search, $documentTypes) {
                $matches->whereHas('user', fn ($user) => $user->where('name', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%'))
                    ->orWhere('purpose', 'like', '%'.$search.'%')
                    ->orWhere('document_type', 'like', '%'.$search.'%');
                if ($documentTypes) $matches->orWhereIn('document_type', $documentTypes);
            });
        }
        $statusCounts = (clone $query)->selectRaw('status, COUNT(*) AS aggregate')->groupBy('status')->pluck('aggregate', 'status');
        $allCount = $statusCounts->sum();
        if ($status !== 'all') $query->where('status', $status);
        $requests = $query->with(['user', 'appointment.slot'])
            ->latest()
            ->paginate(\App\Support\TablePagination::perPage(10))->withQueryString();

        return view('registrar.documents.index', compact('requests', 'statusCounts', 'allCount', 'status'));
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

                if ($next === 'released' && !$locked->paymentSatisfied()) {
                    throw ValidationException::withMessages([
                        'status' => 'This document cannot be released until the Cashier has verified its payment.',
                    ]);
                }

                $previous = $locked->status;
                app(\App\Services\DocumentRequestPricing::class)->auditNoPayment($locked, $request);
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
        $tone = $change->status === 'ready' && $change->paymentSatisfied() ? 'success' : 'info';
        $destination = $change->user->hasRole('alumni')
            ? route('portal.documents.index')
            : route('portal.appointments.index');
        if (!$change->user->hasAnyRole(['student', 'alumni'])) $destination = route('portal.dashboard');
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
        if ($documentRequest->paymentNotRequired()) {
            return "Your {$document} is ready for pickup. No Payment Required. Bring a valid ID to the Registrar's Office.";
        }
        if ($documentRequest->paymentSatisfied()) {
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
