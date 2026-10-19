<?php

namespace App\Http\Controllers\Registrar;

use App\Http\Controllers\Controller;
use App\Models\DocumentRequest;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DocumentRequestController extends Controller
{
    public function index()
    {
        $requests = DocumentRequest::with('user')
            ->latest()
            ->get();

        return view('registrar.documents.index', compact('requests'));
    }

    public function updateStatus(Request $request, DocumentRequest $documentRequest)
    {
        $validated = $request->validate([
            'status'  => ['required', 'in:submitted,processing,ready,released'],
            'remarks' => ['nullable', 'string', 'max:500'],
        ]);

        $documentRequest->update([
            'status'  => $validated['status'],
            'remarks' => $validated['remarks'] ?? $documentRequest->remarks,
        ]);

        // Notify student/alumni
        $messages = [
            'processing' => 'Your document request is now being processed.',
            'ready'      => 'Your document is ready for pickup! Please proceed to the Registrar\'s Office.',
            'released'   => 'Your document has been released. Thank you!',
        ];

        if (isset($messages[$validated['status']])) {
            NotificationService::send(
                $documentRequest->user,
                'Document Request Update',
                $messages[$validated['status']],
                $validated['status'] === 'ready' ? 'success' : 'info',
                route('portal.documents.index')
            );
        }

        return redirect()->route('registrar.documents.index')
            ->with('success', 'Request status updated.');
    }
}