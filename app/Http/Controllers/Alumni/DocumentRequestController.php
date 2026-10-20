<?php

namespace App\Http\Controllers\Alumni;

use App\Http\Controllers\Controller;
use App\Models\DocumentRequest;
use App\Models\AppointmentSlot;
use App\Models\Appointment;
use App\Services\NotificationService;
use App\Services\AppointmentScheduleService;
use App\Models\User;
use App\Models\Setting;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class DocumentRequestController extends Controller
{
    public function index()
    {
        $requests = DocumentRequest::with('appointment.slot')->where('user_id', Auth::id())
            ->latest()
            ->get();

        $schedule = app(AppointmentScheduleService::class);
        $pickupSlots = AppointmentSlot::where('date', '>=', today())
            ->where('status', 'open')
            ->orderBy('date')
            ->orderBy('start_time')
            ->get()
            ->filter(fn ($slot) => $slot->isAvailable()
                && ! $schedule->isClosed($slot->date, $slot->start_time, $slot->end_time)
                && ($slot->date !== today()->toDateString() || $slot->start_time > now()->format('H:i:s')))
            ->values();

        $totalRequests  = $requests->count();
        $readyRequests  = $requests->filter(fn ($item) => $item->status === 'ready' && $item->is_paid && $item->payment_status === 'verified')->count();
        $releasedDocs   = $requests->where('status', 'released')->count();
        $totalFees      = $requests->sum('fee');

        return view('alumni.requests.index', compact(
            'requests', 'totalRequests', 'readyRequests',
            'releasedDocs', 'totalFees', 'pickupSlots'
        ));
    }

    public function bookPickup(Request $request, DocumentRequest $documentRequest)
    {
        abort_if($documentRequest->user_id !== Auth::id(), 403);

        $validated = $request->validate([
            'appointment_slot_id' => ['required', 'integer', 'exists:appointment_slots,id'],
        ]);

        $appointment = DB::transaction(function () use ($documentRequest, $validated) {
            $lockedRequest = DocumentRequest::whereKey($documentRequest->id)->lockForUpdate()->firstOrFail();
            if ($lockedRequest->status !== 'ready' || ! $lockedRequest->is_paid || $lockedRequest->payment_status !== 'verified') {
                throw ValidationException::withMessages(['appointment_slot_id' => 'Pickup can be booked after the document is ready and payment is verified.']);
            }

            if ($lockedRequest->appointment && $lockedRequest->appointment->status !== 'cancelled') {
                throw ValidationException::withMessages(['appointment_slot_id' => 'This request already has a pickup appointment.']);
            }

            $slot = AppointmentSlot::whereKey($validated['appointment_slot_id'])->lockForUpdate()->firstOrFail();
            $schedule = app(AppointmentScheduleService::class);
            if (! $slot->isAvailable() || $slot->status !== 'open' || $schedule->isClosed($slot->date, $slot->start_time, $slot->end_time)
                || $slot->date < today()->toDateString()
                || ($slot->date === today()->toDateString() && $slot->start_time <= now()->format('H:i:s'))) {
                throw ValidationException::withMessages(['appointment_slot_id' => 'That pickup time is no longer available. Please choose another slot.']);
            }

            $appointment = Appointment::create([
                'user_id' => $lockedRequest->user_id,
                'appointment_slot_id' => $slot->id,
                'document_type' => $lockedRequest->document_type,
                'purpose' => $lockedRequest->purpose,
                'status' => 'pending',
            ]);
            $lockedRequest->update(['appointment_id' => $appointment->id]);

            return $appointment->load('slot');
        });

        foreach (User::role(['registrar', 'admin'])->get() as $staff) {
            NotificationService::sendSafely(
                $staff,
                'Pickup Appointment Requested',
                Auth::user()->name . ' booked a pickup for ' . $documentRequest->document_label . ' on '
                    . \Carbon\Carbon::parse($appointment->slot->date)->format('M d, Y') . ' at '
                    . \Carbon\Carbon::parse($appointment->slot->start_time)->format('h:i A') . '.',
                'info',
                route('registrar.appointments.index')
            );
        }

        return redirect()->route('portal.documents.index')->with('success', 'Pickup time requested. The Registrar will confirm it.');
    }

    public function create()
    {
        $slots = AppointmentSlot::where('date', '>=', today())
            ->orderBy('date')
            ->orderBy('start_time')
            ->get()
            ->filter(fn($slot) => $slot->isAvailable() && !app(AppointmentScheduleService::class)->isClosed($slot->date, $slot->start_time, $slot->end_time) && ($slot->date !== today()->toDateString() || $slot->start_time > now()->format('H:i:s')))
            ->values();

        $documentFees = [
            'tor' => (float) Setting::get('document_fee_tor', 50),
            'diploma' => (float) Setting::get('document_fee_diploma', 100),
            'certification' => (float) Setting::get('document_fee_certification', 30),
            'true_copy' => (float) Setting::get('document_fee_true_copy', 50),
        ];

        $gcashQrPath = Setting::get('gcash_qr_path');
        $gcashQrReady = $gcashQrPath && Storage::disk('local')->exists($gcashQrPath);

        return view('alumni.requests.create', compact('slots', 'documentFees', 'gcashQrReady'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'document_type'       => ['required', 'in:tor,diploma,certification,true_copy'],
            'copies'              => ['required', 'integer', 'min:1', 'max:10'],
            'payment_method'      => ['required', 'in:walk_in,gcash_qr'],
            'purpose'             => ['nullable', 'string', 'max:500'],
            'appointment_slot_id' => ['nullable', 'exists:appointment_slots,id'],
            'reference_number'    => ['required_if:payment_method,gcash_qr', 'nullable', 'string', 'max:100'],
            'proof_of_payment'    => ['required_if:payment_method,gcash_qr', 'nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'mimetypes:image/jpeg,image/png,application/pdf', 'max:5120'],
        ]);

        if ($validated['payment_method'] === 'gcash_qr') {
            $qrPath = Setting::get('gcash_qr_path');
            if (! $qrPath || ! Storage::disk('local')->exists($qrPath)) {
                throw ValidationException::withMessages([
                    'payment_method' => 'Online payment is not available right now. Please choose walk-in payment.',
                ]);
            }
        }

        // Read the authoritative current fee; each request keeps this amount thereafter.
        $baseFee = (float) Setting::get('document_fee_' . $validated['document_type'], match ($validated['document_type']) {
            'tor' => 50,
            'diploma' => 100,
            'certification' => 30,
            'true_copy' => 50,
        });

        $fee = round($baseFee * $validated['copies'], 2);

        $proofPath = $validated['payment_method'] === 'gcash_qr'
            ? $request->file('proof_of_payment')->store('document-request-proofs', 'local')
            : null;

        try {
            $docRequest = DB::transaction(function () use ($validated, $fee, $proofPath, $request) {
                $appointmentId = null;
                if (! empty($validated['appointment_slot_id'])) {
                    $slot = AppointmentSlot::whereKey($validated['appointment_slot_id'])->lockForUpdate()->firstOrFail();
                    if (! $slot->isAvailable() || app(AppointmentScheduleService::class)->isClosed($slot->date, $slot->start_time, $slot->end_time) || $slot->date < today()->toDateString() || ($slot->date === today()->toDateString() && $slot->start_time <= now()->format('H:i:s'))) {
                        throw ValidationException::withMessages([
                            'appointment_slot_id' => 'That pickup time is no longer available. Please choose another slot.',
                        ]);
                    }

                    $appointment = Appointment::create([
                        'user_id' => Auth::id(),
                        'appointment_slot_id' => $slot->id,
                        'document_type' => $validated['document_type'],
                        'purpose' => $validated['purpose'] ?? null,
                        'status' => 'pending',
                    ]);
                    $appointmentId = $appointment->id;
                }

                $documentRequest = DocumentRequest::create([
                    'user_id' => Auth::id(),
                    'appointment_id' => $appointmentId,
                    'document_type' => $validated['document_type'],
                    'copies' => $validated['copies'],
                    'purpose' => $validated['purpose'] ?? null,
                    'status' => 'submitted',
                    'fee' => $fee,
                    'payment_method' => $validated['payment_method'],
                    'payment_status' => $validated['payment_method'] === 'gcash_qr' ? 'pending_verification' : 'unpaid',
                    'reference_number' => $validated['payment_method'] === 'gcash_qr' ? $validated['reference_number'] : null,
                    'proof_of_payment' => $proofPath,
                    'payment_submitted_at' => $proofPath ? now() : null,
                ]);

                AuditLog::create([
                    'user_id' => Auth::id(),
                    'action' => 'document_request.created',
                    'description' => "Document request #{$documentRequest->id} created; request status {$documentRequest->status}, payment status {$documentRequest->payment_status}.",
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'metadata' => [
                        'document_request_id' => $documentRequest->id,
                        'request_status' => $documentRequest->status,
                        'payment_method' => $documentRequest->payment_method,
                        'payment_status' => $documentRequest->payment_status,
                        'amount_due' => $fee,
                    ],
                ]);

                return $documentRequest;
            });
        } catch (\Throwable $exception) {
            if ($proofPath) {
                Storage::disk('local')->delete($proofPath);
            }
            throw $exception;
        }

        // ── Notify Registrars ──────────────────────────────────────────
        $registrars = User::role(['registrar', 'admin'])->get();
        foreach ($registrars as $registrar) {
            NotificationService::sendSafely(
                $registrar,
                'New Document Request',
                Auth::user()->name . ' requested ' .
                $docRequest->document_label . ' — ' .
                $validated['copies'] . ' cop' .
                ($validated['copies'] > 1 ? 'ies' : 'y') . '.',
                'info',
                route('registrar.documents.index')
            );
        }

        // ── Notify Admins ──────────────────────────────────────────────
        if ($docRequest->payment_status === 'pending_verification') {
            foreach (User::role(['cashier', 'admin'])->get() as $cashier) {
                NotificationService::sendSafely(
                    $cashier,
                    'Document Payment Needs Verification',
                    Auth::user()->name . ' submitted GCash proof for document request #' . $docRequest->id . '.',
                    'info',
                    route('cashier.document-payments.show', $docRequest)
                );
            }
        }

        NotificationService::emailDocumentRequestSafely(
            Auth::user(),
            $docRequest,
            $docRequest->payment_status === 'pending_verification'
                ? 'We received your request and payment proof. Your payment is awaiting Cashier verification.'
                : 'We received your document request. Please pay the fee at the Cashier; your request will continue after payment is recorded.'
        );

        return redirect()->route('portal.documents.index')
            ->with('success', $docRequest->payment_status === 'pending_verification'
                ? 'Document request submitted. Your GCash payment is awaiting Cashier verification.'
                : 'Document request submitted. Pay at the Cashier’s Office; your request remains unpaid until staff record it.');
    }

    public function submitPayment(Request $request, DocumentRequest $documentRequest)
    {
        abort_unless($documentRequest->user_id === Auth::id(), 403);
        abort_if($documentRequest->status === 'cancelled' || $documentRequest->is_paid || $documentRequest->payment_status === 'verified', 403);

        if ($documentRequest->payment_method !== 'gcash_qr' || $documentRequest->payment_status !== 'rejected') {
            return back()->with('error', 'Payment details can only be resubmitted after a GCash claim was rejected.');
        }

        $validated = $request->validate([
            'reference_number' => ['required', 'string', 'max:100'],
            'proof_of_payment' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'mimetypes:image/jpeg,image/png,application/pdf', 'max:5120'],
        ]);

        $path = $request->file('proof_of_payment')->store('document-request-proofs', 'local');
        $previousProofPath = $documentRequest->proof_of_payment;
        try {
            DB::transaction(function () use ($documentRequest, $validated, $path, $request) {
                $locked = DocumentRequest::whereKey($documentRequest->id)->lockForUpdate()->firstOrFail();
                if ($locked->status === 'cancelled' || $locked->is_paid || $locked->payment_status !== 'rejected') {
                    throw ValidationException::withMessages(['payment' => 'This payment can no longer be changed.']);
                }

                $locked->update([
                    'reference_number' => $validated['reference_number'],
                    'proof_of_payment' => $path,
                    'payment_status' => 'pending_verification',
                    'payment_submitted_at' => now(),
                    'payment_rejection_reason' => null,
                    'rejected_by' => null,
                    'rejected_at' => null,
                ]);

                AuditLog::create([
                    'user_id' => Auth::id(),
                    'action' => 'document_payment.resubmitted',
                    'description' => "GCash proof resubmitted for document request #{$locked->id}.",
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'metadata' => ['document_request_id' => $locked->id, 'amount_due' => $locked->fee],
                ]);
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }

        if ($previousProofPath && $previousProofPath !== $path) {
            Storage::disk('local')->delete($previousProofPath);
        }

        foreach (User::role(['cashier', 'admin'])->get() as $cashier) {
            NotificationService::sendSafely($cashier, 'Document Payment Needs Verification', Auth::user()->name . ' resubmitted GCash proof for document request #' . $documentRequest->id . '.', 'info', route('cashier.document-payments.show', $documentRequest));
        }

        NotificationService::emailDocumentRequestSafely(
            Auth::user(),
            $documentRequest->fresh(),
            'We received your updated GCash proof. Your payment is awaiting Cashier verification.'
        );

        return redirect()->route('portal.documents.index')->with('success', 'Payment proof resubmitted and awaiting Cashier verification.');
    }

    public function viewProof(DocumentRequest $documentRequest)
    {
        abort_unless($documentRequest->user_id === Auth::id(), 403);

        return $this->privateProofResponse($documentRequest);
    }

    public function showGcashQr()
    {
        $path = Setting::get('gcash_qr_path');
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return response()->file(Storage::disk('local')->path($path), [
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function privateProofResponse(DocumentRequest $documentRequest)
    {
        $path = $documentRequest->proof_of_payment;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return response()->file(Storage::disk('local')->path($path), [
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
