<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\DocumentRequest;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class DocumentRequestPricing
{
    private const FEES = ['tor' => 50, 'diploma' => 100, 'certification' => 30, 'true_copy' => 50];

    /** Quote in integer centavos, using only configured prices and validated selections. */
    public function quote(array $documents): array
    {
        $prices = Setting::whereIn('key', array_map(fn ($type) => 'document_fee_'.$type, array_keys(self::FEES)))->pluck('value', 'key');
        $quoted = [];
        $seen = [];
        $total = 0;
        foreach ($documents as $document) {
            $type = $document['document_type'] ?? null;
            $copies = $document['copies'] ?? null;
            if (!is_string($type) || !array_key_exists($type, self::FEES) || isset($seen[$type])
                || filter_var($copies, FILTER_VALIDATE_INT) === false || $copies < 1 || $copies > 10) {
                throw ValidationException::withMessages(['documents' => 'Select valid documents and copy quantities without duplicate document types.']);
            }
            $seen[$type] = true;
            $price = (string) ($prices['document_fee_'.$type] ?? self::FEES[$type]);
            if (!preg_match('/^\d+(?:\.\d{1,2})?$/D', $price) || (float) $price > 999999.99) {
                throw ValidationException::withMessages(['payment' => 'Document pricing is unavailable. Please contact the Registrar.']);
            }
            $cents = (int) round((float) $price * 100) * (int) $copies;
            $total += $cents;
            $quoted[] = ['document_type' => $type, 'copies' => (int) $copies, 'fee' => $cents / 100];
        }
        if (!$quoted || count($quoted) > 4 || $total > 99999999) {
            throw ValidationException::withMessages(['documents' => 'The selected documents exceed the supported booking limit.']);
        }
        return ['documents' => $quoted, 'total' => $total / 100];
    }

    public function auditNoPayment(DocumentRequest $document, Request $request): void
    {
        if (!$document->paymentNotRequired()) return;
        AuditLog::firstOrCreate([
            'action' => 'document_request.no_payment_required',
            'description' => 'No payment required for document request #'.$document->id.': the server-calculated document fee is PHP 0.00.',
        ], [
            'user_id' => $request->user()->id,
            'ip_address' => $request->ip(), 'user_agent' => $request->userAgent(),
            'metadata' => ['document_request_id' => $document->id, 'amount_due' => '0.00', 'reason' => 'authoritative_zero_fee'],
        ]);
    }
}
