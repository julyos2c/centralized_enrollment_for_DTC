<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\DocumentRequest;
use App\Models\Setting;
use Illuminate\Http\Request;
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
        $canSubmit = !$closed && !$paid && in_array($status, ['unpaid', 'rejected'], true)
            && (!$alumni || ($status === 'rejected' && $documentRequest->payment_method === 'gcash_qr'));
        return $this->page([
            'paymentTitle' => 'Document payment', 'description' => $documentRequest->document_label,
            'fee' => $documentRequest->fee, 'status' => $status, 'closed' => $closed, 'canSubmit' => $canSubmit,
            'remarks' => $documentRequest->payment_rejection_reason, 'receipt' => $documentRequest->receipt_no,
            'reference' => $documentRequest->reference_number,
            'backUrl' => route($alumni ? 'portal.documents.index' : 'portal.appointments.index'),
            'submitUrl' => route($alumni ? 'portal.documents.payment.submit' : 'portal.appointments.document-payment.gcash', $documentRequest),
            'proofUrl' => $documentRequest->proof_of_payment ? route($alumni ? 'portal.documents.payment.proof' : 'portal.appointments.document-payment.proof', $documentRequest) : null,
            'qrUrl' => route($alumni ? 'portal.documents.gcash.qr' : 'portal.appointments.gcash.qr'), 'referenceMax' => 100,
        ]);
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
                default => $payment->payment_method === 'gcash' ? 'pending_verification' : 'unpaid',
            };
            return $this->page([
                'paymentTitle' => 'Combined document payment',
                'description' => $documents->map(fn ($item) => $item->document_label . ' × ' . $item->copies)->implode(', '),
                'fee' => $payment->amount,
                'status' => $status,
                'closed' => $appointment->status === 'cancelled',
                'canSubmit' => $status !== 'not_required' && $appointment->status !== 'cancelled' && $payment->payment_method === 'gcash' && in_array($status, ['rejected'], true),
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
        $status = $paid ? 'verified' : ($payment ? ($payment->status === 'pending' && $payment->isGcash() ? 'pending_verification' : $payment->status) : 'unpaid');
        $closed = $appointment->status === 'cancelled';
        return $this->page([
            'paymentTitle' => 'Appointment payment', 'description' => $appointment->document_type,
            'fee' => !$payment || $payment->status === 'rejected' ? Setting::get('appointment_processing_fee', 0) : $payment->amount,
            'status' => $status, 'closed' => $closed, 'canSubmit' => !$closed && !$paid && (!$payment || $payment->status === 'rejected'),
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
