<?php

namespace App\Http\Controllers\Alumni;

use App\Http\Controllers\Controller;
use App\Models\DocumentRequest;
use App\Models\AppointmentSlot;
use App\Models\Appointment;
use App\Services\NotificationService;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DocumentRequestController extends Controller
{
    public function index()
    {
        $requests = DocumentRequest::where('user_id', Auth::id())
            ->latest()
            ->get();

        $totalRequests  = $requests->count();
        $readyRequests  = $requests->where('status', 'ready')->count();
        $releasedDocs   = $requests->where('status', 'released')->count();
        $totalFees      = $requests->sum('fee');

        return view('alumni.requests.index', compact(
            'requests', 'totalRequests', 'readyRequests',
            'releasedDocs', 'totalFees'
        ));
    }

    public function create()
    {
        $slots = AppointmentSlot::where('date', '>=', today())
            ->orderBy('date')
            ->orderBy('start_time')
            ->get()
            ->filter(fn($slot) => $slot->isAvailable())
            ->values();

        return view('alumni.requests.create', compact('slots'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'document_type'       => ['required', 'in:tor,diploma,certification,true_copy'],
            'copies'              => ['required', 'integer', 'min:1', 'max:10'],
            'purpose'             => ['nullable', 'string', 'max:500'],
            'appointment_slot_id' => ['nullable', 'exists:appointment_slots,id'],
        ]);

        // Calculate fee based on document type and copies
        $baseFee = match($validated['document_type']) {
            'tor'          => 50.00,
            'diploma'      => 100.00,
            'certification'=> 30.00,
            'true_copy'    => 50.00,
            default        => 50.00,
        };

        $fee = $baseFee * $validated['copies'];

        // Create appointment if slot selected
        $appointmentId = null;
        if (! empty($validated['appointment_slot_id'])) {
            $slot = AppointmentSlot::findOrFail($validated['appointment_slot_id']);
            if ($slot->isAvailable()) {
                $appointment = Appointment::create([
                    'user_id'             => Auth::id(),
                    'appointment_slot_id' => $validated['appointment_slot_id'],
                    'document_type'       => $validated['document_type'],
                    'purpose'             => $validated['purpose'],
                    'status'              => 'pending',
                ]);
                $appointmentId = $appointment->id;
            }
        }

        $docRequest = DocumentRequest::create([
            'user_id'        => Auth::id(),
            'appointment_id' => $appointmentId,
            'document_type'  => $validated['document_type'],
            'copies'         => $validated['copies'],
            'purpose'        => $validated['purpose'],
            'status'         => 'submitted',
            'fee'            => $fee,
        ]);

        // ── Notify Registrars ──────────────────────────────────────────
        $registrars = User::role('registrar')->get();
        foreach ($registrars as $registrar) {
            NotificationService::send(
                $registrar,
                'New Document Request',
                Auth::user()->name . ' (Alumni) requested ' .
                $docRequest->document_label . ' — ' .
                $validated['copies'] . ' cop' .
                ($validated['copies'] > 1 ? 'ies' : 'y') . '.',
                'info',
                route('registrar.documents.index')
            );
        }

        // ── Notify Admins ──────────────────────────────────────────────
        $admins = User::role('admin')->get();
        foreach ($admins as $admin) {
            NotificationService::send(
                $admin,
                'New Document Request',
                Auth::user()->name . ' (Alumni) submitted a document request.',
                'info',
                route('registrar.documents.index')
            );
        }

        return redirect()->route('portal.documents.index')
            ->with('success', 'Document request submitted successfully!');
    }
}