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
use App\Support\PrivateFilePreview;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class DocumentRequestController extends Controller
{
    public function index()
    {
        $requests = DocumentRequest::with(['appointment.slot', 'appointment.documentRequests', 'appointment.payments'])->where('user_id', Auth::id())
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
        $readyRequests  = $requests->filter(fn ($item) => $item->status === 'ready' && $item->paymentSatisfied())->count();
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
            if ($lockedRequest->status !== 'ready' || !$lockedRequest->paymentSatisfied()) {
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

        $gcashName = Setting::get('gcash_name');
        $gcashNumber = Setting::get('gcash_number');
        return view('alumni.requests.create', compact('slots', 'documentFees', 'gcashQrReady', 'gcashName', 'gcashNumber'));
    }

    public function store(Request $request)
    {
        if ($request->exists('documents') && ($request->exists('document_type') || $request->exists('copies'))) {
            throw ValidationException::withMessages([
                'documents' => 'Submit either a single document or a combined document list, not both.',
            ]);
        }

        $validated = $request->validate([
            'document_type'       => ['nullable', 'required_without:documents', 'in:tor,diploma,certification,true_copy'],
            'copies'              => ['required_with:document_type', 'integer', 'min:1', 'max:10'],
            'documents'           => ['required_without:document_type', 'array', 'min:1', 'max:4'],
            'documents.*.document_type' => ['required', 'in:tor,diploma,certification,true_copy'],
            'documents.*.copies'  => ['required', 'integer', 'min:1', 'max:10'],
            'payment_method'      => ['nullable', 'in:walk_in,gcash_qr'],
            'purpose'             => ['nullable', 'string', 'max:500'],
            'appointment_slot_id' => ['nullable', 'exists:appointment_slots,id'],
            'reference_number'    => ['nullable', 'string', 'max:100'],
            'proof_of_payment'    => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'mimetypes:image/jpeg,image/png,application/pdf', 'max:5120'],
        ], [], [
            'documents.*.copies' => 'number of copies',
            'documents.*.document_type' => 'document type',
        ]);


        $documents = $validated['documents'] ?? [[
            'document_type' => $validated['document_type'],
            'copies' => $validated['copies'],
        ]];
        $documents = array_values($documents);
        $quote = app(\App\Services\DocumentRequestPricing::class)->quote($documents);
        if ($quote['total'] == 0) {
            $validated['payment_method'] = 'walk_in';
        } else {
            $request->validate(['payment_method' => ['required', 'in:walk_in,gcash_qr']]);
            $validated['payment_method'] = $request->input('payment_method');
            if ($validated['payment_method'] === 'gcash_qr') {
                $request->validate([
                    'reference_number' => ['required', 'string', 'max:100'],
                    'proof_of_payment' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'mimetypes:image/jpeg,image/png,application/pdf', 'max:5120'],
                ]);
            }
        }
        if ($validated['payment_method'] === 'gcash_qr') {
            $qrPath = Setting::get('gcash_qr_path');
            if (! $qrPath || ! Storage::disk('local')->exists($qrPath)) {
                throw ValidationException::withMessages([
                    'payment_method' => 'Online payment is not available right now. Please choose walk-in payment.',
                ]);
            }
        }

        if (count($documents) > 1 && empty($validated['appointment_slot_id'])) {
            throw ValidationException::withMessages([
                'appointment_slot_id' => 'Choose one pickup time to book multiple documents together.',
            ]);
        }

        if (! empty($validated['appointment_slot_id'])) {
            $proofPath = $validated['payment_method'] === 'gcash_qr'
                ? $request->file('proof_of_payment')->store('document-request-proofs', 'local')
                : null;

            try {
                $appointment = app(\App\Services\DocumentAppointmentBookingService::class)->book(
                    Auth::user(),
                    (int) $validated['appointment_slot_id'],
                    $documents,
                    $documents[0]['document_type'],
                    $validated['purpose'] ?? null,
                    $validated['payment_method'] === 'gcash_qr' ? 'gcash' : 'walk_in',
                    $validated['payment_method'] === 'gcash_qr' ? $validated['reference_number'] : null,
                    $proofPath,
                    $request
                );
            } catch (\Throwable $exception) {
                if ($proofPath) {
                    Storage::disk('local')->delete($proofPath);
                }
                throw $exception;
            }

            if ($appointment->paymentNotRequired() && $proofPath) { Storage::disk('local')->delete($proofPath); $proofPath = null; }
            $documentSummary = $appointment->documentRequests
                ->map(fn ($item) => $item->document_label . ' × ' . $item->copies)
                ->implode(', ');
            foreach (User::role(['registrar', 'admin'])->get() as $staff) {
                NotificationService::sendSafely(
                    $staff,
                    'New Appointment Request',
                    Auth::user()->name . ' booked an appointment for ' . $documentSummary . ' on '
                        . \Carbon\Carbon::parse($appointment->slot->date)->format('M d, Y') . ' at '
                        . \Carbon\Carbon::parse($appointment->slot->start_time)->format('h:i A') . '.',
                    'info',
                    route('registrar.appointments.index')
                );
            }
            if ($proofPath) {
                foreach (User::role(['cashier', 'admin'])->get() as $cashier) {
                    NotificationService::sendSafely(
                        $cashier,
                        'Document Payment Needs Verification',
                        Auth::user()->name . ' submitted GCash proof for appointment #' . $appointment->id . ' covering ' . $documentSummary . '.',
                        'warning',
                        route('cashier.document-payments.index', ['status' => 'pending_verification'])
                    );
                }
            }

            return redirect()->route('portal.documents.index')
                ->with('success', 'Document requests submitted. The Registrar will confirm pickup when they are ready.');
        }

        $validated['document_type'] = $documents[0]['document_type'];
        $validated['copies'] = $documents[0]['copies'];

        // Read the authoritative current fee; each request keeps this amount thereafter.
        $fee = $quote['documents'][0]['fee'];

        $proofPath = $validated['payment_method'] === 'gcash_qr'
            ? $request->file('proof_of_payment')->store('document-request-proofs', 'local')
            : null;

        try {
            $docRequest = DB::transaction(function () use ($validated, $fee, $proofPath, $request) {
                if ((float) $fee === 0.0) {
                    User::whereKey(Auth::id())->lockForUpdate()->firstOrFail();
                    $existing = DocumentRequest::where('user_id', Auth::id())->whereNull('appointment_id')
                        ->where('document_type', $validated['document_type'])->where('copies', $validated['copies'])
                        ->where('purpose', $validated['purpose'] ?? null)->where('fee', 0)->where('is_paid', false)
                        ->where('status', 'submitted')->where('created_at', '>=', now()->subMinute())->first();
                    if ($existing) {
                        app(\App\Services\DocumentRequestPricing::class)->auditNoPayment($existing, $request);
                        return $existing;
                    }
                }
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

                app(\App\Services\DocumentRequestPricing::class)->auditNoPayment($documentRequest, $request);
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

        $message = $docRequest->paymentNotRequired()
            ? 'Document request submitted. No Payment Required. The Registrar will process your request.'
            : ($docRequest->payment_status === 'pending_verification'
                ? 'Document request submitted. Your GCash payment is awaiting Cashier verification.'
                : 'Document request submitted. Pay at the Cashier Office; your request remains unpaid until staff record it.');
        NotificationService::emailDocumentRequestSafely(Auth::user(), $docRequest, $message);
        return redirect()->route('portal.documents.index')->with('success', $message);
    }

    public function submitPayment(Request $request, DocumentRequest $documentRequest)
    {
        abort_unless($documentRequest->user_id === Auth::id(), 403);
        if ($documentRequest->usesBundledPayment()) {
            throw ValidationException::withMessages(['payment' => 'This payment is managed through its appointment. Review or submit payment for the whole booking.']);
        }
        if ($documentRequest->paymentNotRequired()) {
            return redirect()->route('portal.payments.document', $documentRequest);
        }

        abort_if($documentRequest->paymentNotRequired() || $documentRequest->status === 'cancelled' || $documentRequest->is_paid || $documentRequest->payment_status === 'verified', 403);

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

        return redirect()->route('portal.payments.document', $documentRequest)->with('success', 'Payment proof resubmitted.');
    }

    public function viewProof(Request $request, DocumentRequest $documentRequest)
    {
        abort_unless($documentRequest->user_id === Auth::id(), 403);
        $path = $documentRequest->proof_of_payment;
        abort_unless($path && Storage::disk('local')->exists($path), 404);
        $backUrl = $documentRequest->appointment_id
            ? route('portal.payments.appointment', $documentRequest->appointment)
            : route('portal.payments.document', $documentRequest);

        return PrivateFilePreview::respond(
            $request,
            Storage::disk('local')->path($path),
            'Payment proof',
            $backUrl,
            basename($path)
        );
    }

    public function showGcashQr()
    {
        $path = Setting::get('gcash_qr_path');
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        if (request()->boolean('download')) {
            $extension = pathinfo($path, PATHINFO_EXTENSION);
            return response()->download(Storage::disk('local')->path($path), 'DTC-GCash-QR.'.$extension, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
        }
        return response()->file(Storage::disk('local')->path($path), [
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

}
