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

class AppointmentController extends Controller
{
    public function index()
    {
        $appointments = Appointment::with(['slot', 'latestPayment', 'documentRequest'])
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

        return view('student.appointments.index', compact('appointments', 'slots', 'gcashQrReady'));
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
        $validated = $request->validate([
            'appointment_slot_id' => ['required', 'exists:appointment_slots,id'],
            'document_type'       => ['required', 'in:transcript,diploma,certification,tor'],
            'purpose'             => ['nullable', 'string', 'max:500'],
            'payment_method'      => ['required', 'in:walk_in,gcash'],
            'reference_number'    => ['required_if:payment_method,gcash', 'nullable', 'string', 'max:100'],
            'proof_of_payment'    => ['required_if:payment_method,gcash', 'nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'mimetypes:image/jpeg,image/png,application/pdf', 'max:5120'],
        ]);

        if ($validated['payment_method'] === 'gcash') {
            $qrPath = Setting::get('gcash_qr_path');
            if (! $qrPath || ! Storage::disk('local')->exists($qrPath)) {
                throw ValidationException::withMessages(['payment_method' => 'GCash payment is unavailable. Please pay at the Cashier.']);
            }
        }

        $documentType = match ($validated['document_type']) {
            'transcript' => 'tor',
            'tor' => 'true_copy',
            default => $validated['document_type'],
        };
        $fee = (float) Setting::get('document_fee_' . $documentType, match ($documentType) {
            'tor' => 50,
            'diploma' => 100,
            'certification' => 30,
            'true_copy' => 50,
        });
        $proofPath = $validated['payment_method'] === 'gcash'
            ? $request->file('proof_of_payment')->store('document-request-proofs', 'local')
            : null;

        try {
            $appointment = DB::transaction(function () use ($validated, $documentType, $fee, $proofPath, $request) {
                $slot = AppointmentSlot::whereKey($validated['appointment_slot_id'])->lockForUpdate()->firstOrFail();
                $closed = app(AppointmentScheduleService::class)->isClosed($slot->date, $slot->start_time, $slot->end_time);
                if (! $slot->isAvailable() || $closed || $slot->date < today()->toDateString() || ($slot->date === today()->toDateString() && $slot->start_time <= now()->format('H:i:s'))) {
                    throw ValidationException::withMessages(['appointment_slot_id' => 'This slot is no longer available. Please select another.']);
                }
                $existing = Appointment::where('user_id', Auth::id())->where('appointment_slot_id', $slot->id)->whereIn('status', ['pending', 'confirmed'])->exists();
                if ($existing) throw ValidationException::withMessages(['appointment_slot_id' => 'You already have a booking for this slot.']);

                $appointment = Appointment::create([
                    'user_id' => Auth::id(),
                    'appointment_slot_id' => $slot->id,
                    'document_type' => $validated['document_type'],
                    'purpose' => $validated['purpose'],
                    'status' => 'pending',
                ]);

                $documentRequest = DocumentRequest::create([
                    'user_id' => Auth::id(),
                    'appointment_id' => $appointment->id,
                    'document_type' => $documentType,
                    'copies' => 1,
                    'purpose' => $validated['purpose'],
                    'status' => 'submitted',
                    'fee' => $fee,
                    'payment_method' => $validated['payment_method'] === 'gcash' ? 'gcash_qr' : 'walk_in',
                    'payment_status' => $proofPath ? 'pending_verification' : 'unpaid',
                    'reference_number' => $validated['payment_method'] === 'gcash' ? $validated['reference_number'] : null,
                    'proof_of_payment' => $proofPath,
                    'payment_submitted_at' => $proofPath ? now() : null,
                ]);

                AuditLog::create([
                    'user_id' => Auth::id(),
                    'action' => 'document_request.created',
                    'description' => "Document request #{$documentRequest->id} created from an appointment booking.",
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'metadata' => [
                        'document_request_id' => $documentRequest->id,
                        'appointment_id' => $appointment->id,
                        'request_status' => $documentRequest->status,
                        'payment_method' => $documentRequest->payment_method,
                        'payment_status' => $documentRequest->payment_status,
                        'amount_due' => $fee,
                    ],
                ]);

                return $appointment;
            });
        } catch (\Throwable $exception) {
            if ($proofPath) {
                Storage::disk('local')->delete($proofPath);
            }
            throw $exception;
        }
        $slot = $appointment->slot;

        if ($proofPath) {
            foreach (User::role(['cashier', 'admin'])->get() as $cashier) {
                NotificationService::sendSafely(
                    $cashier,
                    'Document Payment Needs Verification',
                    Auth::user()->name . ' submitted GCash proof for a ' . ucfirst($documentType) . ' document request (PHP ' . number_format($fee, 2) . ').',
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
                Auth::user()->name . ' has booked an appointment for ' .
                ucfirst($appointment->document_type) . ' on ' .
                \Carbon\Carbon::parse($slot->date)->format('M d, Y') .
                ' at ' . \Carbon\Carbon::parse($slot->start_time)->format('h:i A') . '.',
                'info',
                route('registrar.appointments.index')
            );
        }

        return redirect()->route('portal.appointments.index')
            ->with('success', 'Appointment booked successfully! Please wait for the Registrar to confirm.');
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

        $appointment->load('documentRequest');
        $linkedRequest = $appointment->documentRequest;
        if ($linkedRequest
            && $linkedRequest->document_type !== match ($validated['document_type']) {
                'transcript' => 'tor',
                'tor' => 'true_copy',
                default => $validated['document_type'],
            }
            && in_array($linkedRequest->payment_status, ['pending_verification', 'verified'], true)) {
            return back()->withErrors(['document_type' => 'The document type cannot be changed after payment has been submitted. Contact the Registrar for help.'])->withInput();
        }

        $original = $appointment->getOriginal();
        $changes = [];
        if ($original['document_type'] !== $validated['document_type']) $changes[] = 'document type changed to ' . ucfirst($validated['document_type']);
        if ($original['appointment_slot_id'] != $validated['appointment_slot_id']) {
            $newSlot = AppointmentSlot::findOrFail($validated['appointment_slot_id']);
            $changes[] = 'schedule changed to ' . \Carbon\Carbon::parse($newSlot->date)->format('M d, Y') . ' at ' . \Carbon\Carbon::parse($newSlot->start_time)->format('h:i A');
        }
        if ($original['purpose'] !== $validated['purpose']) $changes[] = 'purpose updated';

        DB::transaction(function () use ($validated, $appointment, $linkedRequest) {
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
            if ($linkedRequest) {
                $requestType = match ($validated['document_type']) {
                    'transcript' => 'tor',
                    'tor' => 'true_copy',
                    default => $validated['document_type'],
                };
                $fee = (float) Setting::get('document_fee_' . $requestType, match ($requestType) {
                    'tor' => 50, 'diploma' => 100, 'certification' => 30, 'true_copy' => 50,
                });
                $linkedRequest->update([
                    'document_type' => $requestType,
                    'purpose' => $validated['purpose'],
                    'fee' => $fee,
                ]);
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
            ->with('success', 'Appointment updated successfully. The Registrar has been notified.');
    }

    public function cancel(Appointment $appointment)
    {
        abort_if($appointment->user_id !== Auth::id(), 403);
        $result = app(\App\Services\AppointmentCancellationService::class)
            ->cancel($appointment, Auth::user(), request());

        return match ($result['result']) {
            'already_cancelled' => back()->with('error', 'This appointment has already been cancelled.'),
            'invalid_status' => back()->withErrors(['error' => 'Only pending appointments can be cancelled.']),
            'verified_legacy_payment' => back()->withErrors(['error' => 'This appointment has a verified payment. Contact the Registrar to resolve it before cancellation.']),
            default => redirect()->route('portal.appointments.index')->with('success', 'Appointment cancelled.'),
        };
    }

    public function submitGcash(Request $request, Appointment $appointment)
    {
        abort_if($appointment->user_id !== Auth::id(), 403);

        if ($appointment->status === 'cancelled') {
            return back()->with('error', 'Cannot submit payment for a cancelled appointment.');
        }

        $validated = $request->validate([
            'reference_number' => ['required', 'string', 'max:50'],
            'proof_of_payment' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);
        $fee = (float) Setting::get('appointment_processing_fee', 0);

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
            AppointmentPayment::create([
                'appointment_id'   => $lockedAppointment->id,
                'payment_method'   => 'gcash',
                'reference_number' => $validated['reference_number'],
                'proof_of_payment' => $path,
                'amount'           => $fee,
                'status'           => 'pending',
            ]);

            return 'created';
        });

        if ($result !== 'created') {
            $message = [
                'cancelled' => 'Cannot submit payment for a cancelled appointment.',
                'pending' => 'You already have a payment pending verification.',
                'verified' => 'This appointment already has a verified payment.',
            ][$result];

            return back()->with('error', $message);
        }

        return back()->with('success', 'GCash payment proof submitted. The Cashier will verify your payment shortly.');
    }

    public function viewProof(AppointmentPayment $payment)
    {
        abort_if($payment->appointment->user_id !== Auth::id(), 403);

        if (! $payment->proof_of_payment || ! Storage::disk('local')->exists($payment->proof_of_payment)) {
            abort(404, 'Proof not found.');
        }

        return response()->file(Storage::disk('local')->path($payment->proof_of_payment));
    }

    public function submitDocumentGcash(Request $request, DocumentRequest $documentRequest)
    {
        abort_if($documentRequest->user_id !== Auth::id(), 403);

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
                if ($locked->is_paid || $locked->payment_status === 'verified') {
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

        return redirect()->route('portal.appointments.index')->with('success', 'GCash proof submitted. The Cashier will verify it.');
    }

    public function viewDocumentProof(DocumentRequest $documentRequest)
    {
        abort_if($documentRequest->user_id !== Auth::id(), 403);
        abort_unless($documentRequest->proof_of_payment && Storage::disk('local')->exists($documentRequest->proof_of_payment), 404);

        return response()->file(Storage::disk('local')->path($documentRequest->proof_of_payment), [
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function showGcashQr()
    {
        $path = Setting::get('gcash_qr_path');
        if (! $path || ! Storage::disk('local')->exists($path)) {
            abort(404);
        }
        return response()->file(Storage::disk('local')->path($path));
    }
}
