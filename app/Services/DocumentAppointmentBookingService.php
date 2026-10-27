<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\AppointmentPayment;
use App\Models\AppointmentSlot;
use App\Models\AuditLog;
use App\Models\DocumentRequest;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DocumentAppointmentBookingService
{
    private const FEES = [
        'tor' => 50,
        'diploma' => 100,
        'certification' => 30,
        'true_copy' => 50,
    ];

    public function book(
        User $user,
        int $slotId,
        array $documents,
        ?string $appointmentDocumentType,
        ?string $purpose,
        string $paymentMethod,
        ?string $referenceNumber,
        ?string $proofPath,
        Request $request
    ): Appointment {
        abort_unless($user->hasAnyRole(['student', 'alumni']), 403);
        return DB::transaction(function () use (
            $user, $slotId, $documents, $appointmentDocumentType, $purpose,
            $paymentMethod, $referenceNumber, $proofPath, $request
        ) {
            $slot = AppointmentSlot::whereKey($slotId)->lockForUpdate()->firstOrFail();
            $schedule = app(AppointmentScheduleService::class);
            if (! $slot->isAvailable() || $schedule->isClosed($slot->date, $slot->start_time, $slot->end_time)
                || $slot->date < today()->toDateString()
                || ($slot->date === today()->toDateString() && $slot->start_time <= now()->format('H:i:s'))) {
                throw ValidationException::withMessages([
                    'appointment_slot_id' => 'This pickup time is no longer available. Please select another.',
                ]);
            }

            if (Appointment::where('user_id', $user->id)
                ->where('appointment_slot_id', $slot->id)
                ->whereIn('status', ['pending', 'confirmed'])
                ->exists()) {
                throw ValidationException::withMessages([
                    'appointment_slot_id' => 'You already have a booking for this slot.',
                ]);
            }

            $normalized = $this->normalizeDocuments($documents);
            $quote = app(DocumentRequestPricing::class)->quote($normalized);
            $normalized = $quote['documents'];
            $total = $quote['total'];
            if ($total === 0 || $total === 0.0) {
                $paymentMethod = 'walk_in';
                $referenceNumber = $proofPath = null;
            }

            $appointment = Appointment::create([
                'user_id' => $user->id,
                'appointment_slot_id' => $slot->id,
                'document_type' => $appointmentDocumentType ?: $normalized[0]['document_type'],
                'purpose' => $purpose,
                'status' => 'pending',
            ]);

            $requestPaymentMethod = $paymentMethod === 'gcash' ? 'gcash_qr' : 'walk_in';
            $requestPaymentStatus = $paymentMethod === 'gcash' ? 'pending_verification' : 'unpaid';
            foreach ($normalized as $document) {
                $documentRequest = DocumentRequest::create([
                    'user_id' => $user->id,
                    'appointment_id' => $appointment->id,
                    'document_type' => $document['document_type'],
                    'copies' => $document['copies'],
                    'purpose' => $purpose,
                    'status' => 'submitted',
                    'fee' => $document['fee'],
                    'payment_method' => $document['fee'] == 0 ? 'walk_in' : $requestPaymentMethod,
                    'payment_status' => $document['fee'] == 0 ? 'unpaid' : $requestPaymentStatus,
                ]);

                app(DocumentRequestPricing::class)->auditNoPayment($documentRequest, $request);
                AuditLog::create([
                    'user_id' => $user->id,
                    'action' => 'document_request.created',
                    'description' => "Document request #{$documentRequest->id} created in appointment #{$appointment->id}.",
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'metadata' => [
                        'document_request_id' => $documentRequest->id,
                        'appointment_id' => $appointment->id,
                        'request_status' => $documentRequest->status,
                        'payment_method' => $documentRequest->payment_method,
                        'payment_status' => $documentRequest->payment_status,
                        'amount_due' => $documentRequest->fee,
                    ],
                ]);
            }

            if ($total > 0) AppointmentPayment::create([
                'appointment_id' => $appointment->id,
                'is_document_bundle' => true,
                'payment_method' => $paymentMethod,
                'reference_number' => $referenceNumber,
                'proof_of_payment' => $proofPath,
                'status' => $paymentMethod === 'gcash' ? 'pending' : 'pending',
                'amount' => $total,
            ]);

            return $appointment->load(['slot', 'documentRequests']);
        });
    }

    private function normalizeDocuments(array $documents): array
    {
        if (! $documents) {
            throw ValidationException::withMessages(['documents' => 'Select at least one document.']);
        }

        $normalized = [];
        $seen = [];
        foreach ($documents as $document) {
            if (! is_array($document)
                || ! isset($document['document_type'], $document['copies'])
                || ! is_string($document['document_type'])
                || ! in_array($document['document_type'], ['tor', 'diploma', 'certification', 'true_copy'], true)
                || filter_var($document['copies'], FILTER_VALIDATE_INT) === false
                || (int) $document['copies'] < 1
                || (int) $document['copies'] > 10) {
                throw ValidationException::withMessages(['documents' => 'The selected documents or copy quantities are invalid.']);
            }

            $type = $document['document_type'];
            if (isset($seen[$type])) {
                throw ValidationException::withMessages(['documents' => 'Choose each document type only once.']);
            }
            $seen[$type] = true;
            $normalized[] = ['document_type' => $type, 'copies' => (int) $document['copies']];
        }

        return $normalized;
    }
}
