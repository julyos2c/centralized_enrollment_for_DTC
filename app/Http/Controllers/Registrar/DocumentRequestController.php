<?php

namespace App\Http\Controllers\Registrar;

use App\Http\Controllers\Controller;
use App\Models\DocumentRequest;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class DocumentRequestController extends Controller
{
    public function index()
    {
        $requests = DocumentRequest::with('user')
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

        if ($validated['status'] === 'released'
            && (! $documentRequest->is_paid || $documentRequest->payment_status !== 'verified')) {
            return back()->withErrors([
                'status' => 'This document cannot be released until the Cashier has verified its payment.',
            ]);
        }

        $documentRequest->update([
            'status'  => $validated['status'],
            'remarks' => $validated['remarks'] ?? $documentRequest->remarks,
        ]);

        // Notify student/alumni
        $messages = [
            'processing' => 'Your document request is now being processed.',
            'released'   => 'Your document has been released. Thank you!',
        ];

        if ($validated['status'] === 'ready') {
            $messages['ready'] = $documentRequest->is_paid && $documentRequest->payment_status === 'verified'
                ? 'Your document is ready for pickup. Please proceed to the Registrar\'s Office.'
                : 'Your document is prepared, but it cannot be released until the Cashier verifies your payment. Check My Requests for your payment status.';
        }

        if (isset($messages[$validated['status']])) {
            NotificationService::send(
                $documentRequest->user,
                'Document Request Update',
                $messages[$validated['status']],
                $validated['status'] === 'ready' && $documentRequest->is_paid && $documentRequest->payment_status === 'verified' ? 'success' : 'info',
                route('portal.documents.index')
            );
        }

        return redirect()->route('registrar.documents.index')
            ->with('success', 'Request status updated.');
    }
}
