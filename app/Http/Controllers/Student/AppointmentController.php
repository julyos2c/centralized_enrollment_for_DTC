<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\AppointmentSlot;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AppointmentController extends Controller
{
    public function index()
    {
        $appointments = Appointment::with('slot')
            ->where('user_id', Auth::id())
            ->latest()
            ->get();

        return view('student.appointments.index', compact('appointments'));
    }

    public function create()
    {
        $slots = AppointmentSlot::where('date', '>=', today())
            ->orderBy('date')
            ->orderBy('start_time')
            ->get()
            ->filter(fn($slot) => $slot->isAvailable())
            ->values();

        return view('student.appointments.create', compact('slots'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'appointment_slot_id' => ['required', 'exists:appointment_slots,id'],
            'document_type'       => ['required', 'in:transcript,diploma,certification,tor'],
            'purpose'             => ['nullable', 'string', 'max:500'],
        ]);

        $slot = AppointmentSlot::findOrFail($validated['appointment_slot_id']);

        if (! $slot->isAvailable()) {
            return back()->withErrors([
                'appointment_slot_id' => 'This slot is no longer available. Please select another.'
            ]);
        }

        // Prevent duplicate pending appointments for same slot
        $existing = Appointment::where('user_id', Auth::id())
            ->where('appointment_slot_id', $validated['appointment_slot_id'])
            ->whereIn('status', ['pending', 'confirmed'])
            ->exists();

        if ($existing) {
            return back()->withErrors([
                'appointment_slot_id' => 'You already have a booking for this slot.'
            ]);
        }

        $appointment = Appointment::create([
            'user_id'             => Auth::id(),
            'appointment_slot_id' => $validated['appointment_slot_id'],
            'document_type'       => $validated['document_type'],
            'purpose'             => $validated['purpose'],
            'status'              => 'pending',
        ]);

        // ── Notify ALL registrars ─────────────────────────────────────────
        $registrars = User::role('registrar')->get();
        foreach ($registrars as $registrar) {
            NotificationService::send(
                $registrar,
                'New Appointment Request',
                Auth::user()->name . ' has booked an appointment for ' .
                ucfirst($appointment->document_type) . ' on ' .
                \Carbon\Carbon::parse($slot->date)->format('M d, Y') .
                ' at ' . \Carbon\Carbon::parse($slot->start_time)->format('h:i A') . '.',
                'info',
                route('registrar.appointments.index')
            );
        }

        // ── Also notify Admin ─────────────────────────────────────────────
        $admins = User::role('admin')->get();
        foreach ($admins as $admin) {
            NotificationService::send(
                $admin,
                'New Appointment Request',
                Auth::user()->name . ' has booked an appointment for ' .
                ucfirst($appointment->document_type) . ' on ' .
                \Carbon\Carbon::parse($slot->date)->format('M d, Y') . '.',
                'info',
                route('registrar.appointments.index')
            );
        }

        return redirect()->route('portal.appointments.index')
            ->with('success', 'Appointment booked successfully! Please wait for the Registrar to confirm.');
    }

public function cancel(Appointment $appointment)
{
    abort_if($appointment->user_id !== Auth::id(), 403);

    if ($appointment->status !== 'pending') {
        return back()->withErrors([
            'error' => 'Only pending appointments can be cancelled.'
        ]);
    }

    $appointment->update(['status' => 'cancelled']);

    // ── Notify Registrars ─────────────────────────────────────────────
    $registrars = User::role('registrar')->get();
    foreach ($registrars as $registrar) {
        NotificationService::send(
            $registrar,
            'Appointment Cancelled',
            Auth::user()->name . ' has cancelled their appointment booking for ' .
            ucfirst($appointment->document_type) . '.',
            'warning',
            route('registrar.appointments.index')
        );
    }

    // ── Notify Admins ─────────────────────────────────────────────────
    $admins = User::role('admin')->get();
    foreach ($admins as $admin) {
        NotificationService::send(
            $admin,
            'Appointment Cancelled',
            Auth::user()->name . ' has cancelled their appointment booking for ' .
            ucfirst($appointment->document_type) . '.',
            'warning',
            route('registrar.appointments.index')
        );
    }

    return redirect()->route('portal.appointments.index')
        ->with('success', 'Appointment cancelled.');
 }
}