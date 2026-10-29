<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\AppointmentPayment;
use App\Models\AppointmentSlot;
use App\Models\AuditLog;
use App\Models\DocumentRequest;
use App\Models\Setting;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Services\AppointmentScheduleService;
use App\Support\PrivateFilePreview;

class AppointmentController extends Controller
{
    public function index()
    {
        $appointments = Appointment::with(['slot', 'latestPayment', 'documentRequest', 'documentRequests'])
            ->where('user_id', Auth::id())
            ->latest()
            ->get();

        $slots = AppointmentSlot::where('date', '>=', today())
            ->orderBy('date')
            ->orderBy('start_time')
            ->get()
            ->filter(fn($slot) => $slot->isAvailable() && !app(AppointmentScheduleService::class)->isClosed($slot->date, $slot->start_time, $slot->end_time) && ($slot->date !== today()->toDateString() || $slot->start_time > now()->format('H:i:s')))
            ->values();

        $gcashQrPath = Setting::get('gcash_qr_path');
        $gcashQrReady = $gcashQrPath && Storage::disk('local')->exists($gcashQrPath);
        $appointmentProcessingFee = (float) Setting::get('appointment_processing_fee', 0);

        return view('student.appointments.index', compact('appointments', 'slots', 'gcashQrReady', 'appointmentProcessingFee'));
    }

    public function create()
    {
        $slots = AppointmentSlot::where('date', '>=', today())
            ->orderBy('date')
            ->orderBy('start_time')
            ->get()
            ->filter(fn($slot) => $slot->isAvailable() && !app(AppointmentScheduleService::class)->isClosed($slot->date, $slot->start_time, $slot->end_time) && ($slot->date !== today()->toDateString() || $slot->start_time > now()->format('H:i:s')))
            ->values();

        $gcashNumber  = Setting::get('gcash_number');
        $gcashName    = Setting::get('gcash_name');
        $gcashQrReady = Setting::get('gcash_qr_path') && Storage::disk('local')->exists(Setting::get('gcash_qr_path'));
        $documentFees = [
            'transcript' => (float) Setting::get('document_fee_tor', 50),
            'diploma' => (float) Setting::get('document_fee_diploma', 100),
            'certification' => (float) Setting::get('document_fee_certification', 30),
            'tor' => (float) Setting::get('document_fee_true_copy', 50),
        ];

        return view('student.appointments.create', compact(
            'slots', 'gcashNumber', 'gcashName', 'gcashQrReady', 'documentFees'
        ));
    }

    public function store(Request $request)
    {
        if ($request->exists('documents') && $request->exists('document_type')) {
            throw ValidationException::withMessages([
                'documents' => 'Submit either a single document or a combined document list, not both.',
            ]);
        }

        $validated = $request->validate([
            'appointment_slot_id' => ['required', 'exists:appointment_slots,id'],
            'document_type'       => ['nullable', 'required_without:documents', 'in:transcript,diploma,certification,tor'],
            'documents'           => ['required_without:document_type', 'array', 'min:1', 'max:4'],
            'documents.*.document_type' => ['required', 'in:transcript,diploma,certification,tor'],
            'documents.*.copies'  => ['required', 'integer', 'min:1', 'max:10'],
            'purpose'             => ['nullable', 'string', 'max:500'],
            'payment_method'      => ['nullable', 'in:walk_in,gcash'],
            'reference_number'    => ['nullable', 'string', 'max:100'],
            'proof_of_payment'    => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'mimetypes:image/jpeg,image/png,application/pdf', 'max:5120'],
        ], [], [
            'documents.*.copies' => 'number of copies',
            'documents.*.document_type' => 'document type',
        ]);


        $documents = $validated['documents'] ?? [[
            'document_type' => $validated['document_type'],
            'copies' => 1,
        ]];
        $documents = array_values($documents);
        $appointmentType = $documents[0]['document_type'];
        $documents = array_map(function ($document) {
            $document['document_type'] = match ($document['document_type']) {
                'transcript' => 'tor',
                'tor' => 'true_copy',
                default => $document['document_type'],
            };
            return $document;
        }, $documents);
        $quote = app(\App\Services\DocumentRequestPricing::class)->quote($documents);
        if ($quote['total'] == 0) {
            $validated['payment_method'] = 'walk_in';
        } else {
            $request->validate(['payment_method' => ['required', 'in:walk_in,gcash']]);
            $validated['payment_method'] = $request->input('payment_method');
            if ($validated['payment_method'] === 'gcash') {
                $request->validate([
                    'reference_number' => ['required', 'string', 'max:100'],
                    'proof_of_payment' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'mimetypes:image/jpeg,image/png,application/pdf', 'max:5120'],
                ]);
            }
        }
        if ($validated['payment_method'] === 'gcash') {
            $qrPath = Setting::get('gcash_qr_path');
            if (! $qrPath || ! Storage::disk('local')->exists($qrPath)) {
                throw ValidationException::withMessages(['payment_method' => 'GCash payment is unavailable. Please pay at the Cashier.']);
            }
        }

        $proofPath = $validated['payment_method'] === 'gcash'
            ? $request->file('proof_of_payment')->store('document-request-proofs', 'local')
            : null;
        if ($validated['payment_method'] === 'gcash'
            && (! $proofPath || ! Storage::disk('local')->exists($proofPath))) {
            if ($proofPath) {
                Storage::disk('local')->delete($proofPath);
            }
            throw ValidationException::withMessages([
                'proof_of_payment' => 'The proof could not be saved. Please try uploading it again.',
            ]);
        }

        try {
            $appointment = app(\App\Services\DocumentAppointmentBookingService::class)->book(
                Auth::user(),
                (int) $validated['appointment_slot_id'],
                $documents,
                $appointmentType,
                $validated['purpose'] ?? null,
                $validated['payment_method'] === 'gcash' ? 'gcash' : 'walk_in',
                $validated['payment_method'] === 'gcash' ? $validated['reference_number'] : null,
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
        $slot = $appointment->slot;
        $documentLabels = $appointment->documentRequests->map(fn ($item) => $item->document_label . ' × ' . $item->copies)->implode(', ');
        $totalFee = $appointment->documentRequests->sum('fee');

        if ($proofPath) {
            foreach (User::role(['cashier', 'admin'])->get() as $cashier) {
                NotificationService::sendSafely(
                    $cashier,
                    'Document Payment Needs Verification',
                    Auth::user()->name . ' submitted GCash proof for appointment #' . $appointment->id . ' covering ' . $documentLabels . ' (PHP ' . number_format($totalFee, 2) . ').',
                    'warning',
                    route('cashier.document-payments.index', ['status' => 'pending_verification'])
                );
            }
        }

        // Notify registrars
        $notifyUsers = User::role(['registrar', 'admin'])->get();
        foreach ($notifyUsers as $staff) {
            NotificationService::send(
                $staff,
                'New Appointment Request',
                Auth::user()->name . ' has booked an appointment for ' . $documentLabels . ' on ' .
                \Carbon\Carbon::parse($slot->date)->format('M d, Y') .
                ' at ' . \Carbon\Carbon::parse($slot->start_time)->format('h:i A') . '.',
                'info',
                route('registrar.appointments.index')
            );
        }

        NotificationService::sendUserUpdateSafely(
            Auth::user(),
            'appointment-booked:'.$appointment->id,
            'Appointment Request Received',
            'We received your appointment request for '.$documentLabels.' on '
                .\Carbon\Carbon::parse($slot->date)->format('M d, Y').' at '
                .\Carbon\Carbon::parse($slot->start_time)->format('h:i A').'. It is awaiting Registrar confirmation.',
            'info',
            route('portal.appointments.index'),
            'View appointment'
        );

        return redirect()->route('portal.appointments.index')
            ->with('success', 'Appointment requested. Await Registrar confirmation.');
    }

    public function edit(Appointment $appointment)
    {
        abort_if($appointment->user_id !== Auth::id(), 403);

        if ($appointment->status !== 'pending') {
            return redirect()->route('portal.appointments.index')
                ->with('error', 'Only pending appointments can be edited.');
        }

        $slots = AppointmentSlot::where('date', '>=', today())
            ->orderBy('date')
            ->orderBy('start_time')
            ->get()
            ->filter(fn($slot) => $slot->id === $appointment->appointment_slot_id || ($slot->isAvailable() && !app(AppointmentScheduleService::class)->isClosed($slot->date, $slot->start_time, $slot->end_time) && ($slot->date !== today()->toDateString() || $slot->start_time > now()->format('H:i:s'))))
            ->values();

        return view('student.appointments.edit', compact('appointment', 'slots'));
    }

    public function update(Request $request, Appointment $appointment)
    {
        abort_if($appointment->user_id !== Auth::id(), 403);

        if ($appointment->status !== 'pending') {
            return redirect()->route('portal.appointments.index')
                ->with('error', 'Only pending appointments can be edited.');
        }

        $validated = $request->validate([
            'appointment_slot_id' => ['required', 'exists:appointment_slots,id'],
            'document_type'       => ['required', 'in:transcript,diploma,certification,tor'],
            'purpose'             => ['required', 'string', 'max:500'],
        ]);

        $original = $appointment->getOriginal();
        $changes = [];
        if ($original['document_type'] !== $validated['document_type']) $changes[] = 'document type changed to ' . ucfirst($validated['document_type']);
        if ($original['appointment_slot_id'] != $validated['appointment_slot_id']) {
            $newSlot = AppointmentSlot::findOrFail($validated['appointment_slot_id']);
            $changes[] = 'schedule changed to ' . \Carbon\Carbon::parse($newSlot->date)->format('M d, Y') . ' at ' . \Carbon\Carbon::parse($newSlot->start_time)->format('h:i A');
        }
        if ($original['purpose'] !== $validated['purpose']) $changes[] = 'purpose updated';

        DB::transaction(function () use ($validated, $appointment) {
            $appointment = Appointment::whereKey($appointment->id)->lockForUpdate()->firstOrFail();
            if ($appointment->status !== 'pending') {
                throw ValidationException::withMessages(['appointment' => 'This booking has changed. Only pending appointments can be edited.']);
            }
            $linkedRequests = DocumentRequest::where('appointment_id', $appointment->id)->lockForUpdate()->get();
            $linkedRequest = $linkedRequests->first();
            $requestType = match ($validated['document_type']) {
                'transcript' => 'tor',
                'tor' => 'true_copy',
                default => $validated['document_type'],
            };
            $typeChanged = $linkedRequest && $linkedRequest->document_type !== $requestType;
            if ($linkedRequests->count() > 1 && $appointment->document_type !== $validated['document_type']) {
                throw ValidationException::withMessages(['document_type' => 'Change document types through a new request when an appointment contains multiple documents.']);
            }
            if ($typeChanged && ($linkedRequest->is_paid || in_array($linkedRequest->payment_status, ['pending_verification', 'verified'], true))) {
                throw ValidationException::withMessages(['document_type' => 'The document type cannot be changed after payment has been submitted. Contact the Registrar for help.']);
            }
            if ($validated['appointment_slot_id'] != $appointment->appointment_slot_id) {
                $slot = AppointmentSlot::whereKey($validated['appointment_slot_id'])->lockForUpdate()->firstOrFail();
                if (! $slot->isAvailable() || app(AppointmentScheduleService::class)->isClosed($slot->date, $slot->start_time, $slot->end_time) || $slot->date < today()->toDateString() || ($slot->date === today()->toDateString() && $slot->start_time <= now()->format('H:i:s'))) {
                    throw ValidationException::withMessages(['appointment_slot_id' => 'This slot is no longer available. Please select another.']);
                }
                if (Appointment::where('user_id', Auth::id())->where('appointment_slot_id', $slot->id)->whereIn('status', ['pending', 'confirmed'])->where('id', '<>', $appointment->id)->exists()) {
                    throw ValidationException::withMessages(['appointment_slot_id' => 'You already have a booking for this slot.']);
                }
            }

            $appointment->update($validated);
            foreach ($linkedRequests as $item) {
                $updates = ['purpose' => $validated['purpose']];
                if ($item->id === $linkedRequest?->id && $typeChanged) {
                    $updates['document_type'] = $requestType;
                    $newFee = app(\App\Services\DocumentRequestPricing::class)->quote([['document_type' => $requestType, 'copies' => $item->copies]])['total'];
                    if (((float) $item->fee === 0.0) !== ($newFee == 0)) {
                        throw ValidationException::withMessages(['document_type' => 'Submit a new request when changing between a free and a payable document.']);
                    }
                    $updates['fee'] = $newFee;
                }
                $item->update($updates);
            }
            if ($typeChanged && $linkedRequests->isNotEmpty()) {
                $bundlePayment = AppointmentPayment::where('appointment_id', $appointment->id)->where('is_document_bundle', true)->first();
                if ($bundlePayment && $bundlePayment->status !== 'verified') {
                    $bundlePayment->update(['amount' => $linkedRequests->sum(fn ($item) => (float) $item->fresh()->fee)]);
                }
            }
        });

        // Notify registrars of the update
        $slot = AppointmentSlot::find($appointment->appointment_slot_id);
        $summary = count($changes)
            ? implode('; ', $changes)
            : 'details updated';

        $notifyUsers = User::role(['registrar', 'admin'])->get();
        foreach ($notifyUsers as $staff) {
            NotificationService::send(
                $staff,
                'Appointment Updated',
                Auth::user()->name . ' has updated their appointment booking (' . $summary . ').',
                'warning',
                route('registrar.appointments.index')
            );
        }

        return redirect()->route('portal.appointments.index')
            ->with('success', 'Appointment updated. The Registrar has been notified.');
    }

    public function cancel(Appointment $appointment)
    {
        abort_if($appointment->user_id !== Auth::id(), 403);
        $result = app(\App\Services\AppointmentCancellationService::class)
            ->cancel($appointment, Auth::user(), request());

        return match ($result['result']) {
            'already_cancelled' => back()->with('error', 'Appointment is already cancelled.'),
            'invalid_status' => back()->withErrors(['error' => 'Only pending appointments can be cancelled here. Contact the Registrar to change a confirmed booking.']),
            'verified_legacy_payment' => back()->withErrors(['error' => 'This appointment has a verified payment. Contact the Registrar to resolve it before cancellation.']),
            'resolved_request' => back()->withErrors(['error' => 'This booking can no longer be cancelled because one of the linked documents is already in progress or has been released.']),
            default => redirect()->route('portal.appointments.index')->with('success', $result['user_message']),
        };
    }

    public function submitGcash(Request $request, Appointment $appointment)
    {
        abort_if($appointment->user_id !== Auth::id(), 403);
        if ($appointment->documentRequests()->exists()) {
            return redirect()->route('portal.payments.appointment', $appointment)->with('error', 'Use the document payment page for this booking.');
        }

        if ($appointment->status === 'cancelled') {
            return back()->with('error', 'Cannot submit payment for a cancelled appointment.');
        }

        $validated = $request->validate([
            'reference_number' => ['required', 'string', 'max:50'],
            'proof_of_payment' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);
        $fee = (float) Setting::get('appointment_processing_fee', 0);
        if ($fee <= 0) {
            return back()->with('error', 'No payment is required for this appointment.');
        }

        $result = DB::transaction(function () use ($appointment, $request, $validated, $fee) {
            $lockedAppointment = Appointment::whereKey($appointment->id)->lockForUpdate()->firstOrFail();
            if ($lockedAppointment->status === 'cancelled') {
                return 'cancelled';
            }

            $payments = AppointmentPayment::where('appointment_id', $lockedAppointment->id)
                ->lockForUpdate()
                ->get();

            if ($payments->contains('status', 'pending')) {
                return 'pending';
            }
            if ($payments->contains('status', 'verified')) {
                return 'verified';
            }

            $path = $request->file('proof_of_payment')->store('appointment-proofs', 'local');
            $payment = AppointmentPayment::create([
                'appointment_id'   => $lockedAppointment->id,
                'payment_method'   => 'gcash',
                'reference_number' => $validated['reference_number'],
                'proof_of_payment' => $path,
                'amount'           => $fee,
                'status'           => 'pending',
            ]);

            return $payment;
        });

        if (is_string($result)) {
            $message = [
                'cancelled' => 'Cannot submit payment for a cancelled appointment.',
                'pending' => 'You already have a payment pending verification.',
                'verified' => 'This appointment already has a verified payment.',
            ][$result];

            return back()->with('error', $message);
        }

        NotificationService::sendUserUpdateSafely(
            Auth::user(),
            'appointment-payment-proof-submitted:'.$result->id,
            'GCash Proof Received',
            'We received your appointment payment proof. It is awaiting verification.',
            'info',
            route('portal.payments.appointment', $appointment),
            'View payment status'
        );

        return redirect()->route('portal.payments.appointment', $appointment)->with('success', 'GCash proof submitted.');
    }

    public function submitBundleGcash(Request $request, Appointment $appointment)
    {
        abort_unless($appointment->user_id === Auth::id(), 403);
        $validated = $request->validate([
            'reference_number' => ['required', 'string', 'max:100'],
            'proof_of_payment' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'mimetypes:image/jpeg,image/png,application/pdf', 'max:5120'],
        ]);
        $qrPath = Setting::get('gcash_qr_path');
        if (! $qrPath || ! Storage::disk('local')->exists($qrPath)) {
            throw ValidationException::withMessages(['payment' => 'GCash payment is unavailable. Please pay at the Cashier.']);
        }
        $proofPath = $request->file('proof_of_payment')->store('document-request-proofs', 'local');
        if (! $proofPath || ! Storage::disk('local')->exists($proofPath)) {
            if ($proofPath) {
                Storage::disk('local')->delete($proofPath);
            }
            throw ValidationException::withMessages([
                'proof_of_payment' => 'The proof could not be saved. Please try uploading it again.',
            ]);
        }

        try {
            $previousProof = DB::transaction(function () use ($appointment, $validated, $proofPath, $request) {
                $lockedAppointment = Appointment::whereKey($appointment->id)->lockForUpdate()->firstOrFail();
                if ($lockedAppointment->status === 'cancelled') {
                    throw ValidationException::withMessages(['payment' => 'Cannot submit payment for a cancelled appointment.']);
                }
                $payment = AppointmentPayment::where('appointment_id', $lockedAppointment->id)
                    ->where('is_document_bundle', true)
                    ->lockForUpdate()
                    ->latest()
                    ->firstOrFail();
                $firstSubmission = $payment->status === 'pending'
                    && $payment->payment_method === 'gcash'
                    && ! $payment->proof_of_payment
                    && ! $payment->reference_number;
                if ((! $firstSubmission && ($payment->status !== 'rejected' || $payment->payment_method !== 'gcash'))
                    || $lockedAppointment->documentRequests()->where(function ($query) {
                        $query->where('is_paid', true)->orWhere('payment_status', 'verified');
                    })->exists()) {
                    throw ValidationException::withMessages(['payment' => 'This bundled payment is not available for GCash submission.']);
                }
                if ($lockedAppointment->documentRequests()->where('fee', '>', 0)->whereIn('status', ['cancelled', 'released'])->exists()) {
                    throw ValidationException::withMessages(['payment' => 'This bundled payment is no longer available.']);
                }
                $previousProof = $payment->proof_of_payment;
                $payment->update([
                    'status' => 'pending',
                    'reference_number' => $validated['reference_number'],
                    'proof_of_payment' => $proofPath,
                    'rejection_reason' => null,
                    'verified_by' => null,
                    'verified_at' => null,
                ]);
                $lockedAppointment->documentRequests()->where('fee', '>', 0)->update([
                    'payment_method' => 'gcash_qr',
                    'payment_status' => 'pending_verification',
                    'reference_number' => null,
                    'proof_of_payment' => null,
                    'payment_submitted_at' => now(),
                    'payment_rejection_reason' => null,
                    'rejected_by' => null,
                    'rejected_at' => null,
                ]);
                AuditLog::create([
                    'user_id' => Auth::id(),
                    'action' => 'document_payment.bundle_submitted',
                    'description' => "Submitted GCash proof for appointment #{$lockedAppointment->id}.",
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'metadata' => [
                        'appointment_id' => $lockedAppointment->id,
                        'appointment_payment_id' => $payment->id,
                        'reference_number' => $validated['reference_number'],
                        'amount_due' => $payment->amount,
                    ],
                ]);

                return $previousProof;
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($proofPath);
            throw $exception;
        }

        if ($previousProof && $previousProof !== $proofPath) {
            Storage::disk('local')->delete($previousProof);
        }
        $payment = AppointmentPayment::where('appointment_id', $appointment->id)->where('is_document_bundle', true)->latest()->firstOrFail();
        foreach (User::role(['cashier', 'admin'])->get() as $cashier) {
            NotificationService::sendSafely(
                $cashier,
                'Document Payment Needs Verification',
                Auth::user()->name . ' submitted GCash proof for appointment #' . $appointment->id . ' (PHP ' . number_format((float) $payment->amount, 2) . ').',
                'warning',
                route('cashier.document-payments.index', ['status' => 'pending_verification'])
            );
        }

        return redirect()->route('portal.payments.appointment', $appointment)
            ->with('success', 'GCash proof submitted.');
    }

    public function viewProof(AppointmentPayment $payment)
    {
        abort_if($payment->appointment->user_id !== Auth::id(), 403);

        if (! $payment->proof_of_payment || ! Storage::disk('local')->exists($payment->proof_of_payment)) {
            abort(404, 'Proof not found.');
        }

        return PrivateFilePreview::respond(
            request(),
            Storage::disk('local')->path($payment->proof_of_payment),
            'Payment proof',
            route('portal.payments.appointment', $payment->appointment),
            basename($payment->proof_of_payment)
        );
    }

    public function submitDocumentGcash(Request $request, DocumentRequest $documentRequest)
    {
        abort_if($documentRequest->user_id !== Auth::id(), 403);
        if ($documentRequest->usesBundledPayment()) {
            throw ValidationException::withMessages(['payment' => 'This payment is managed through its appointment. Review or submit payment for the whole booking.']);
        }
        if ($documentRequest->paymentNotRequired()) {
            return redirect()->route('portal.payments.document', $documentRequest);
        }


        $validated = $request->validate([
            'reference_number' => ['required', 'string', 'max:100'],
            'proof_of_payment' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'mimetypes:image/jpeg,image/png,application/pdf', 'max:5120'],
        ]);
        $qrPath = Setting::get('gcash_qr_path');
        if (! $qrPath || ! Storage::disk('local')->exists($qrPath)) {
            throw ValidationException::withMessages(['payment' => 'GCash payment is unavailable. Please pay at the Cashier.']);
        }

        $proofPath = $request->file('proof_of_payment')->store('document-request-proofs', 'local');
        try {
            $result = DB::transaction(function () use ($documentRequest, $validated, $proofPath, $request) {
                $locked = DocumentRequest::whereKey($documentRequest->id)->lockForUpdate()->firstOrFail();
                if ($locked->paymentNotRequired() || $locked->is_paid || $locked->payment_status === 'verified') {
                    return 'verified';
                }
                if ($locked->payment_status === 'pending_verification') {
                    return 'pending';
                }
                if (! in_array($locked->payment_status, ['unpaid', 'rejected'], true) || in_array($locked->status, ['released', 'cancelled'], true)) {
                    return 'unavailable';
                }

                $locked->update([
                    'payment_method' => 'gcash_qr',
                    'payment_status' => 'pending_verification',
                    'reference_number' => $validated['reference_number'],
                    'proof_of_payment' => $proofPath,
                    'payment_submitted_at' => now(),
                    'payment_rejection_reason' => null,
                    'rejected_by' => null,
                    'rejected_at' => null,
                ]);

                AuditLog::create([
                    'user_id' => Auth::id(),
                    'action' => 'document_payment.submitted',
                    'description' => "Submitted GCash proof for document request #{$locked->id}.",
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'metadata' => [
                        'document_request_id' => $locked->id,
                        'payment_method' => $locked->payment_method,
                        'payment_status' => $locked->payment_status,
                        'reference_number' => $locked->reference_number,
                        'amount_due' => $locked->fee,
                    ],
                ]);

                return 'submitted';
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($proofPath);
            throw $exception;
        }

        if ($result !== 'submitted') {
            Storage::disk('local')->delete($proofPath);
            $message = [
                'verified' => 'This document payment has already been verified.',
                'pending' => 'Your GCash payment is already waiting for Cashier verification.',
                'unavailable' => 'This document request is no longer accepting payment submissions.',
            ][$result];
            return back()->with('error', $message);
        }

        $documentRequest->refresh();
        foreach (User::role(['cashier', 'admin'])->get() as $cashier) {
            NotificationService::sendSafely(
                $cashier,
                'Document Payment Needs Verification',
                Auth::user()->name . ' submitted GCash proof for document request #' . $documentRequest->id . '.',
                'warning',
                route('cashier.document-payments.show', $documentRequest)
            );
        }
        NotificationService::emailDocumentRequestSafely(
            $documentRequest->user,
            $documentRequest,
            'Your GCash payment proof was submitted. The Cashier will verify it against the received funds.'
        );

        return redirect()->route('portal.payments.document', $documentRequest)->with('success', 'GCash proof submitted.');
    }

    public function viewDocumentProof(Request $request, DocumentRequest $documentRequest)
    {
        abort_if($documentRequest->user_id !== Auth::id(), 403);
        abort_unless($documentRequest->proof_of_payment && Storage::disk('local')->exists($documentRequest->proof_of_payment), 404);

        return PrivateFilePreview::respond(
            $request,
            Storage::disk('local')->path($documentRequest->proof_of_payment),
            'Payment proof',
            route('portal.payments.document', $documentRequest),
            basename($documentRequest->proof_of_payment)
        );
    }

    public function showGcashQr()
    {
        $path = Setting::get('gcash_qr_path');
        if (! $path || ! Storage::disk('local')->exists($path)) {
            abort(404);
        }
        if (request()->boolean('download')) {
            $extension = pathinfo($path, PATHINFO_EXTENSION);
            return response()->download(Storage::disk('local')->path($path), 'DTC-GCash-QR.'.$extension, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
        }
        return response()->file(Storage::disk('local')->path($path));
    }
}
