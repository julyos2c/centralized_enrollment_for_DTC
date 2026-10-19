<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\AppointmentPayment;
use App\Models\AppointmentSlot;
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
        $appointments = Appointment::with(['slot', 'latestPayment'])
            ->where('user_id', Auth::id())
            ->latest()
            ->get();

        $slots = AppointmentSlot::where('date', '>=', today())
            ->orderBy('date')
            ->orderBy('start_time')
            ->get()
            ->filter(fn($slot) => $slot->isAvailable() && !app(AppointmentScheduleService::class)->isClosed($slot->date, $slot->start_time, $slot->end_time) && ($slot->date !== today()->toDateString() || $slot->start_time > now()->format('H:i:s')))
            ->values();

        return view('student.appointments.index', compact('appointments', 'slots'));
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
        $processingFee = Setting::get('appointment_processing_fee', 0);

        return view('student.appointments.create', compact(
            'slots', 'gcashNumber', 'gcashName', 'gcashQrReady', 'processingFee'
        ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'appointment_slot_id' => ['required', 'exists:appointment_slots,id'],
            'document_type'       => ['required', 'in:transcript,diploma,certification,tor'],
            'purpose'             => ['nullable', 'string', 'max:500'],
            'payment_method'      => ['required', 'in:walk_in,gcash'],
            'reference_number'    => ['required_if:payment_method,gcash', 'nullable', 'string', 'max:50'],
            'proof_of_payment'    => ['required_if:payment_method,gcash', 'nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        $appointment = DB::transaction(function () use ($validated) {
            $slot = AppointmentSlot::whereKey($validated['appointment_slot_id'])->lockForUpdate()->firstOrFail();
            $closed = app(AppointmentScheduleService::class)->isClosed($slot->date, $slot->start_time, $slot->end_time);
            if (! $slot->isAvailable() || $closed || $slot->date < today()->toDateString() || ($slot->date === today()->toDateString() && $slot->start_time <= now()->format('H:i:s'))) {
                throw ValidationException::withMessages(['appointment_slot_id' => 'This slot is no longer available. Please select another.']);
            }
            $existing = Appointment::where('user_id', Auth::id())->where('appointment_slot_id', $slot->id)->whereIn('status', ['pending', 'confirmed'])->exists();
            if ($existing) throw ValidationException::withMessages(['appointment_slot_id' => 'You already have a booking for this slot.']);
            return Appointment::create(['user_id' => Auth::id(), 'appointment_slot_id' => $slot->id, 'document_type' => $validated['document_type'], 'purpose' => $validated['purpose'], 'status' => 'pending']);
        });
        $slot = $appointment->slot;

        // Record payment if applicable
        $fee = (float) Setting::get('appointment_processing_fee', 0);
        if ($fee > 0 || $validated['payment_method'] === 'gcash') {
            $paymentData = [
                'appointment_id' => $appointment->id,
                'payment_method' => $validated['payment_method'],
                'amount'         => $fee,
                'status'         => $validated['payment_method'] === 'gcash' ? 'pending' : 'walk_in_pending',
            ];

            if ($validated['payment_method'] === 'gcash') {
                $paymentData['reference_number'] = $validated['reference_number'];
                $paymentData['proof_of_payment'] = $request->file('proof_of_payment')
                    ->store('appointment-proofs', 'local');
            }

            AppointmentPayment::create($paymentData);
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

        $original = $appointment->getOriginal();
        $changes = [];
        if ($original['document_type'] !== $validated['document_type']) $changes[] = 'document type changed to ' . ucfirst($validated['document_type']);
        if ($original['appointment_slot_id'] != $validated['appointment_slot_id']) {
            $newSlot = AppointmentSlot::findOrFail($validated['appointment_slot_id']);
            $changes[] = 'schedule changed to ' . \Carbon\Carbon::parse($newSlot->date)->format('M d, Y') . ' at ' . \Carbon\Carbon::parse($newSlot->start_time)->format('h:i A');
        }
        if ($original['purpose'] !== $validated['purpose']) $changes[] = 'purpose updated';

        if ($validated['appointment_slot_id'] != $appointment->appointment_slot_id) {
            DB::transaction(function () use ($validated, $appointment) {
                $slot = AppointmentSlot::whereKey($validated['appointment_slot_id'])->lockForUpdate()->firstOrFail();
                if (! $slot->isAvailable() || app(AppointmentScheduleService::class)->isClosed($slot->date, $slot->start_time, $slot->end_time) || $slot->date < today()->toDateString() || ($slot->date === today()->toDateString() && $slot->start_time <= now()->format('H:i:s'))) {
                    throw ValidationException::withMessages(['appointment_slot_id' => 'This slot is no longer available. Please select another.']);
                }
                if (Appointment::where('user_id', Auth::id())->where('appointment_slot_id', $slot->id)->whereIn('status', ['pending', 'confirmed'])->exists()) {
                    throw ValidationException::withMessages(['appointment_slot_id' => 'You already have a booking for this slot.']);
                }
                $appointment->update($validated);
            });
        }

        if ($validated['appointment_slot_id'] == $appointment->appointment_slot_id) $appointment->update($validated);

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

        $result = DB::transaction(function () use ($appointment) {
            $lockedAppointment = Appointment::whereKey($appointment->id)->lockForUpdate()->firstOrFail();
            if ($lockedAppointment->status !== 'pending') {
                return 'not_pending';
            }

            $payments = AppointmentPayment::where('appointment_id', $lockedAppointment->id)
                ->lockForUpdate()
                ->get();
            if ($payments->contains('status', 'verified')) {
                return 'paid';
            }

            $lockedAppointment->update(['status' => 'cancelled']);
            return 'cancelled';
        });

        if ($result === 'not_pending') {
            return back()->withErrors(['error' => 'Only pending appointments can be cancelled.']);
        }
        if ($result === 'paid') {
            return back()->withErrors(['error' => 'This appointment has a verified payment. Contact the Registrar to resolve it before cancellation.']);
        }

        $notifyUsers = User::role(['registrar', 'admin'])->get();
        foreach ($notifyUsers as $staff) {
            NotificationService::send(
                $staff,
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

    public function showGcashQr()
    {
        $path = Setting::get('gcash_qr_path');
        if (! $path || ! Storage::disk('local')->exists($path)) {
            abort(404);
        }
        return response()->file(Storage::disk('local')->path($path));
    }
}
