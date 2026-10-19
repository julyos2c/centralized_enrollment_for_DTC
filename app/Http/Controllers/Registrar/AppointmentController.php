<?php

namespace App\Http\Controllers\Registrar;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\AppointmentSlot;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class AppointmentController extends Controller
{
    // ── Slot Management ───────────────────────────────────────────────────

    public function slots()
    {
        $slots = AppointmentSlot::withCount('appointments')
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();

        return view('registrar.appointments.slots', compact('slots'));
    }

    public function storeSlot(Request $request)
    {
        $validated = $request->validate([
            'date'         => ['required', 'date', 'after_or_equal:today'],
            'start_time'   => ['required'],
            'end_time'     => ['required', 'after:start_time'],
            'max_bookings' => ['required', 'integer', 'min:1', 'max:50'],
        ]);

        AppointmentSlot::create($validated);

        return redirect()->route('registrar.appointments.slots')
            ->with('success', 'Appointment slot added successfully.');
    }

    public function deleteSlot(AppointmentSlot $slot)
    {
        $slot->delete();

        return redirect()->route('registrar.appointments.slots')
            ->with('success', 'Slot removed.');
    }

    // ── Appointment Requests ──────────────────────────────────────────────

    public function index()
    {
        $appointments = Appointment::with('user', 'slot')
            ->where('status', 'pending')
            ->latest()
            ->get();

        return view('registrar.appointments.index', compact('appointments'));
    }

    public function confirm(Appointment $appointment)
    {
        $appointment->update(['status' => 'confirmed']);

        NotificationService::send(
            $appointment->user,
            'Appointment Confirmed',
            'Your appointment for ' . ucfirst($appointment->document_type) .
            ' has been confirmed on ' .
            \Carbon\Carbon::parse($appointment->slot->date)->format('M d, Y') .
            ' at ' .
            \Carbon\Carbon::parse($appointment->slot->start_time)->format('h:i A') . '.',
            'success',
            route('portal.appointments.index')
        );

        return redirect()->route('registrar.appointments.index')
            ->with('success', 'Appointment confirmed and student notified.');
    }

    public function cancel(Request $request, Appointment $appointment)
    {
        $validated = $request->validate([
            'remarks' => ['required', 'string', 'max:500'],
        ]);

        $appointment->update([
            'status'  => 'cancelled',
            'remarks' => $validated['remarks'],
        ]);

        NotificationService::send(
            $appointment->user,
            'Appointment Cancelled',
            'Your appointment was cancelled. Reason: ' . $validated['remarks'],
            'danger',
            route('portal.appointments.index')
        );

        return redirect()->route('registrar.appointments.index')
            ->with('success', 'Appointment cancelled and student notified.');
    }
}