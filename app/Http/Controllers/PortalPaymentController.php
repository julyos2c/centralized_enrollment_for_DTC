<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\AppointmentPayment;
use App\Models\AuditLog;
use App\Models\DocumentRequest;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PortalPaymentController extends Controller
{
    public function document(Request $request, DocumentRequest $documentRequest)
    {
        abort_unless($documentRequest->user_id === $request->user()->id, 403);
        $bundle = $documentRequest->appointment?->payments()
            ->where('is_document_bundle', true)->latest()->first();
        if ($bundle) {
            return redirect()->route('portal.payments.appointment', $documentRequest->appointment);
        }
        $alumni = $request->user()->hasRole('alumni');
        $paid = $documentRequest->is_paid || $documentRequest->payment_status === 'verified';
        $status = $documentRequest->paymentNotRequired() ? 'not_required' : ($paid ? 'verified' : $documentRequest->payment_status);
        $closed = in_array($documentRequest->status, ['cancelled', 'released'], true);
        $canSubmit = !$closed && !$paid && $documentRequest->payment_method === 'gcash_qr'
            && in_array($status, ['unpaid', 'rejected'], true)
            && (!$alumni || ($status === 'rejected' && $documentRequest->payment_method === 'gcash_qr'));
        return $this->page([
            'paymentTitle' => 'Document payment', 'description' => $documentRequest->document_label,
            'fee' => $documentRequest->fee, 'status' => $status, 'closed' => $closed, 'canSubmit' => $canSubmit,
            'paymentMethod' => $documentRequest->payment_method,
            'canSwitchToGcash' => false,
            'remarks' => $documentRequest->payment_rejection_reason, 'receipt' => $documentRequest->receipt_no,
            'reference' => $documentRequest->reference_number,
            'backUrl' => route($alumni ? 'portal.documents.index' : 'portal.appointments.index'),
            'submitUrl' => route($alumni ? 'portal.documents.payment.submit' : 'portal.appointments.document-payment.gcash', $documentRequest),
            'proofUrl' => $documentRequest->proof_of_payment ? route($alumni ? 'portal.documents.payment.proof' : 'portal.appointments.document-payment.proof', $documentRequest) : null,
            'qrUrl' => route($alumni ? 'portal.documents.gcash.qr' : 'portal.appointments.gcash.qr'), 'referenceMax' => 100,
        ]);
    }

    public function switchBundleToGcash(Request $request, Appointment $appointment)
    {
        abort_unless($appointment->user_id === $request->user()->id, 403);

        $qrPath = Setting::get('gcash_qr_path');
        if (! $qrPath || ! Storage::disk('local')->exists($qrPath)) {
            return back()->with('error', 'GCash payment is unavailable. Please pay at the Cashier.');
        }

        $result = DB::transaction(function () use ($request, $appointment) {
            $lockedAppointment = Appointment::whereKey($appointment->id)->lockForUpdate()->firstOrFail();
            if ($lockedAppointment->status === 'cancelled') {
                return 'closed';
            }

            $payment = AppointmentPayment::where('appointment_id', $lockedAppointment->id)
                ->where('is_document_bundle', true)
                ->lockForUpdate()
                ->latest()
                ->first();

            if (! $payment
                || $payment->payment_method !== 'walk_in'
                || $payment->status !== 'pending'
                || $payment->proof_of_payment
                || $payment->reference_number
                || (float) $payment->amount <= 0) {
                return 'unavailable';
            }

            $documents = $lockedAppointment->documentRequests()->where('fee', '>', 0)->lockForUpdate()->get();
            if ($documents->isEmpty() || $documents->contains(fn ($document) =>
                $document->is_paid
                || $document->payment_status !== 'unpaid'
                || in_array($document->status, ['cancelled', 'released'], true)
            )) {
                return 'unavailable';
            }

            $payment->update(['payment_method' => 'gcash']);
            $lockedAppointment->documentRequests()->where('fee', '>', 0)->update(['payment_method' => 'gcash_qr']);

            AuditLog::create([
                'user_id' => $request->user()->id,
                'action' => 'document_payment.bundle_method_changed',
                'description' => "Changed bundled payment method to GCash for appointment #{$lockedAppointment->id}.",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'metadata' => [
                    'appointment_id' => $lockedAppointment->id,
                    'appointment_payment_id' => $payment->id,
                    'payment_method' => 'gcash',
                    'amount_due' => $payment->amount,
                ],
            ]);

            return 'updated';
        });

        if ($result !== 'updated') {
            $message = $result === 'closed'
                ? 'Cannot change payment for a cancelled appointment.'
                : 'This bundled payment can no longer be changed.';

            return back()->with('error', $message);
        }

        return redirect()->route('portal.payments.appointment', $appointment);
    }

    public function backToRequests(Request $request, Appointment $appointment)
    {
        abort_unless($appointment->user_id === $request->user()->id, 403);

        DB::transaction(function () use ($request, $appointment) {
            $lockedAppointment = Appointment::whereKey($appointment->id)->lockForUpdate()->firstOrFail();
            if ($lockedAppointment->status === 'cancelled') return;

            $payment = AppointmentPayment::where('appointment_id', $lockedAppointment->id)
                ->where('is_document_bundle', true)
                ->lockForUpdate()
                ->latest()
                ->first();

            if (! $payment
                || $payment->payment_method !== 'gcash'
                || $payment->status !== 'pending'
                || $payment->proof_of_payment
                || $payment->reference_number
                || (float) $payment->amount <= 0) {
                return;
            }

            $documents = $lockedAppointment->documentRequests()->where('fee', '>', 0)->lockForUpdate()->get();
            if ($documents->isEmpty() || $documents->contains(fn ($document) =>
                $document->is_paid
                || $document->payment_status !== 'unpaid'
                || $document->payment_method !== 'gcash_qr'
                || in_array($document->status, ['cancelled', 'released'], true)
            )) {
                return;
            }

            $payment->update(['payment_method' => 'walk_in']);
            $lockedAppointment->documentRequests()->where('fee', '>', 0)->update(['payment_method' => 'walk_in']);

            AuditLog::create([
                'user_id' => $request->user()->id,
                'action' => 'document_payment.bundle_method_changed',
                'description' => "Changed bundled payment method to walk-in for appointment #{$lockedAppointment->id}.",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'metadata' => [
                    'appointment_id' => $lockedAppointment->id,
                    'appointment_payment_id' => $payment->id,
                    'payment_method' => 'walk_in',
                    'amount_due' => $payment->amount,
                ],
            ]);
        });

        return redirect()->route($request->user()->hasRole('alumni') ? 'portal.documents.index' : 'portal.appointments.index');
    }

    public function appointment(Request $request, Appointment $appointment)
    {
        abort_unless($appointment->user_id === $request->user()->id, 403);
        $payment = $appointment->latestPayment;
        if ($payment && $payment->is_document_bundle) {
            $documents = $appointment->documentRequests;
            $status = $appointment->paymentNotRequired() && (float) $payment->amount === 0.0 && $payment->status !== 'verified' ? 'not_required' : match ($payment->status) {
                'verified' => 'verified',
                'rejected' => 'rejected',
                default => $payment->payment_method === 'gcash' && $payment->proof_of_payment ? 'pending_verification' : 'unpaid',
            };
            $qrPath = Setting::get('gcash_qr_path');
            $gcashQrReady = $qrPath && Storage::disk('local')->exists($qrPath);
            return $this->page([
                'paymentTitle' => $documents->count() > 1 ? 'Combined document payment' : 'Document payment',
                'description' => $documents->map(fn ($item) => $item->document_label . ' × ' . $item->copies)->implode(', '),
                'fee' => $payment->amount,
                'status' => $status,
                'paymentMethod' => $payment->payment_method,
                'closed' => $appointment->status === 'cancelled',
                'canSubmit' => $status !== 'not_required' && $appointment->status !== 'cancelled' && $payment->payment_method === 'gcash' && in_array($status, ['unpaid', 'rejected'], true),
                'canSwitchToGcash' => $gcashQrReady && $status === 'unpaid' && $appointment->status !== 'cancelled' && $payment->payment_method === 'walk_in',
                'canSwitchToWalkIn' => $status === 'unpaid' && $appointment->status !== 'cancelled' && $payment->payment_method === 'gcash' && !$payment->proof_of_payment && !$payment->reference_number,
                'switchToGcashUrl' => route('portal.payments.appointments.switch-to-gcash', $appointment),
                'resetPaymentAndBackUrl' => route('portal.payments.appointments.back-to-requests', $appointment),
                'remarks' => $payment->rejection_reason,
                'receipt' => $payment->receipt_no,
                'reference' => $payment->reference_number,
                'backUrl' => route($request->user()->hasRole('alumni') ? 'portal.documents.index' : 'portal.appointments.index'),
                'submitUrl' => route('portal.appointments.bundle-payment.gcash', $appointment),
                'proofUrl' => $payment->proof_of_payment ? route('portal.appointments.payment.proof', $payment) : null,
                'qrUrl' => route('portal.appointments.gcash.qr'),
                'referenceMax' => 100,
            ]);
        }
        if ($appointment->documentRequest) return redirect()->route('portal.payments.document', $appointment->documentRequest);
        $paid = $appointment->isPaid();
        $fee = !$payment || $payment->status === 'rejected'
            ? (float) Setting::get('appointment_processing_fee', 0)
            : (float) $payment->amount;
        $status = $paid ? 'verified' : ($fee <= 0 ? 'not_required' : ($payment ? ($payment->status === 'pending' && $payment->isGcash() ? 'pending_verification' : $payment->status) : 'unpaid'));
        $closed = $appointment->status === 'cancelled';
        return $this->page([
            'paymentTitle' => 'Appointment payment', 'description' => $appointment->document_type,
            'fee' => $fee,
            'status' => $status, 'closed' => $closed,
            'canSubmit' => $fee > 0 && !$closed && !$paid && (!$payment || $payment->status === 'rejected'),
            'paymentMethod' => $payment->payment_method ?? 'walk_in',
            'canSwitchToGcash' => false,
            'remarks' => $payment->rejection_reason ?? null, 'receipt' => $payment->receipt_no ?? null,
            'reference' => $payment->reference_number ?? null,
            'backUrl' => route('portal.appointments.index'), 'submitUrl' => route('portal.appointments.payment.gcash', $appointment),
            'proofUrl' => $payment && $payment->proof_of_payment ? route('portal.appointments.payment.proof', $payment) : null,
            'qrUrl' => route('portal.appointments.gcash.qr'), 'referenceMax' => 50,
        ]);
    }

    private function page(array $data)
    {
        $path = Setting::get('gcash_qr_path');
        return view('portal.payments.details', $data + [
            'gcashQrReady' => $path && Storage::disk('local')->exists($path),
            'gcashName' => Setting::get('gcash_name'), 'gcashNumber' => Setting::get('gcash_number'),
        ]);
    }
}
