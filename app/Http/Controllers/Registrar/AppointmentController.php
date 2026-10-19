<?php

namespace App\Http\Controllers\Registrar;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\AppointmentPayment;
use App\Models\AppointmentSlot;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AppointmentController extends Controller
{
    // ── Slot Management ───────────────────────────────────────────────────

    public function slots()
    {
        $activeCount = fn ($query) => $query->whereIn('status', ['pending', 'confirmed']);
        $slots = AppointmentSlot::withCount(['appointments as appointments_count' => $activeCount])
            ->orderBy('date')
            ->orderBy('start_time')
            ->paginate(10);

        $allSlots = AppointmentSlot::withCount(['appointments as appointments_count' => $activeCount])->get();
        $scheduleService = app(\App\Services\AppointmentScheduleService::class);
        $now = now();
        $availableCount = $allSlots->filter(fn ($slot) => $slot->isAvailable() && ! $scheduleService->isClosed($slot->date, $slot->start_time, $slot->end_time) && ($slot->date > $now->toDateString() || ($slot->date === $now->toDateString() && $slot->start_time > $now->format('H:i:s'))))->count();
        $fullCount = $allSlots->filter(fn ($slot) => $slot->status === 'open' && $slot->availableSlots() <= 0)->count();
        $pastCount = $allSlots->filter(fn ($slot) => $slot->date < $now->toDateString() || ($slot->date === $now->toDateString() && $slot->end_time <= $now->format('H:i:s')))->count();

        $appointmentSchedule = $scheduleService->schedule();
        return view('registrar.appointments.slots', compact('slots', 'availableCount', 'fullCount', 'pastCount', 'appointmentSchedule'));
    }

    public function storeSlot(Request $request)
    {
        $validated = $request->validate([
            'date'         => ['required', 'date', 'after_or_equal:today'],
            'start_time'   => ['required', 'date_format:H:i'],
            'end_time'     => ['required', 'date_format:H:i', 'after:start_time'],
            'max_bookings' => ['required', 'integer', 'min:1', 'max:50'],
        ]);

        if ($validated['date'] === today()->toDateString() && $validated['start_time'] <= now()->format('H:i')) {
            return back()->withErrors(['start_time' => 'The slot must start in the future.'])->withInput();
        }
        if (app(\App\Services\AppointmentScheduleService::class)->isClosed($validated['date'], $validated['start_time'], $validated['end_time'])) {
            return back()->withErrors(['date' => 'The Registrar’s Office is closed during this time.'])->withInput();
        }

        if ($this->slotConflicts($validated['date'], $validated['start_time'], $validated['end_time'])) {
            return back()->withErrors(['start_time' => 'This time overlaps an existing slot.'])->withInput();
        }

        AppointmentSlot::create($validated);

        return redirect()->route('registrar.appointments.slots')
            ->with('success', 'Appointment slot added successfully.');
    }

    public function updateSlot(Request $request, AppointmentSlot $slot)
    {
        $request->merge(['edit_slot_id' => $slot->id]);
        $booked = $slot->appointments()->whereIn('status', ['pending', 'confirmed'])->count();
        $validated = $request->validate([
            'date'         => ['required', 'date', 'after_or_equal:today'],
            'start_time'   => ['required', 'date_format:H:i'],
            'end_time'     => ['required', 'date_format:H:i', 'after:start_time'],
            'max_bookings' => ['required', 'integer', 'min:' . $booked, 'max:50'],
        ]);

        if ($validated['date'] === today()->toDateString() && $validated['start_time'] <= now()->format('H:i')) {
            return back()->withErrors(['start_time' => 'The slot must start in the future.'])->withInput();
        }
        if ($booked > 0 && ($validated['date'] !== $slot->date || $validated['start_time'] !== substr($slot->start_time, 0, 5) || $validated['end_time'] !== substr($slot->end_time, 0, 5))) {
            return back()->withErrors(['date' => 'This slot has existing bookings. Use the appointment request workflow to contact and reschedule affected students before changing its time.'])->withInput();
        }
        if (app(\App\Services\AppointmentScheduleService::class)->isClosed($validated['date'], $validated['start_time'], $validated['end_time'])) {
            return back()->withErrors(['date' => 'The Registrar’s Office is closed during this time.'])->withInput();
        }

        if ($this->slotConflicts($validated['date'], $validated['start_time'], $validated['end_time'], $slot->id)) {
            return back()->withErrors(['start_time' => 'This time overlaps an existing slot.'])->withInput();
        }

        $slot->update(array_merge($validated, ['source' => 'manual']));

        return redirect()->route('registrar.appointments.slots')
            ->with('success', 'Appointment slot updated successfully.');
    }

    public function deleteSlot(AppointmentSlot $slot)
    {
        if ($slot->appointments()->exists()) {
            return back()->withErrors(['slot' => 'This slot has appointment history and cannot be deleted. Close it instead to preserve records.']);
        }
        $slot->delete();

        return redirect()->route('registrar.appointments.slots')
            ->with('success', 'Slot removed.');
    }

    public function setSlotStatus(Request $request, AppointmentSlot $slot)
    {
        $data = $request->validate(['status' => ['required', 'in:open,closed']]);
        $slot->update(['status' => $data['status']]);
        \App\Models\AuditLog::create(['user_id' => Auth::id(), 'action' => 'appointment.slot.status_changed', 'description' => "Changed appointment slot #{$slot->id} status to {$data['status']}.", 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent()]);
        return back()->with('success', 'Slot status updated. Existing appointment and payment history was preserved.');
    }

    private function slotConflicts(string $date, string $start, string $end, ?int $except = null): bool
    {
        return AppointmentSlot::whereDate('date', $date)->when($except, fn ($q) => $q->where('id', '!=', $except))
            ->where('start_time', '<', $end)->where('end_time', '>', $start)->exists();
    }

    // ── Appointment Requests ──────────────────────────────────────────────

    public function index(Request $request)
    {
        $status = $request->get('status', 'pending');
        $allowed = ['pending', 'confirmed', 'cancelled', 'all'];
        if (! in_array($status, $allowed)) $status = 'pending';

        $query = Appointment::with(['user', 'slot', 'latestPayment'])
            ->latest();

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $appointments = $query->paginate(15)->withQueryString();

        // Pending GCash payments needing attention (across all appointments)
        $pendingPayments = AppointmentPayment::with(['appointment.user', 'appointment.slot'])
            ->where('status', 'pending')
            ->where('payment_method', 'gcash')
            ->latest()
            ->get();

        $counts = [
            'pending'   => Appointment::where('status', 'pending')->count(),
            'confirmed' => Appointment::where('status', 'confirmed')->count(),
            'cancelled' => Appointment::where('status', 'cancelled')->count(),
        ];

        return view('registrar.appointments.index', compact(
            'appointments', 'pendingPayments', 'status', 'counts'
        ));
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

        $result = DB::transaction(function () use ($appointment, $validated) {
            $lockedAppointment = Appointment::whereKey($appointment->id)->lockForUpdate()->firstOrFail();
            $hasVerifiedPayment = AppointmentPayment::where('appointment_id', $lockedAppointment->id)
                ->where('status', 'verified')
                ->lockForUpdate()
                ->exists();
            if ($hasVerifiedPayment) {
                return false;
            }

            $lockedAppointment->update([
                'status'  => 'cancelled',
                'remarks' => $validated['remarks'],
            ]);
            return true;
        });

        if (! $result) {
            return back()->with('error', 'This appointment has a verified payment. Resolve it with the Cashier before cancellation.');
        }

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

    // ── GCash Payment Verification ────────────────────────────────────────

    public function verifyPayment(AppointmentPayment $payment)
    {
        DB::transaction(function () use ($payment) {
            $appointment = Appointment::whereKey($payment->appointment_id)->lockForUpdate()->firstOrFail();
            $lockedPayment = AppointmentPayment::whereKey($payment->id)->lockForUpdate()->firstOrFail();

            abort_if($lockedPayment->status !== 'pending' || $lockedPayment->payment_method !== 'gcash', 422, 'Payment is not pending.');
            abort_if($appointment->status === 'cancelled', 422, 'Payment cannot be verified for a cancelled appointment.');
            abort_if(
                AppointmentPayment::where('appointment_id', $appointment->id)
                    ->where('status', 'verified')
                    ->where('id', '!=', $lockedPayment->id)
                    ->exists(),
                422,
                'A verified payment already exists for this appointment.'
            );

            $lockedPayment->update([
                'status'      => 'verified',
                'verified_by' => Auth::id(),
                'verified_at' => now(),
            ]);
        });

        $payment = $payment->fresh();

        NotificationService::send(
            $payment->appointment->user,
            'GCash Payment Verified',
            'Your GCash payment for your ' .
            ucfirst($payment->appointment->document_type) .
            ' appointment has been verified.',
            'success',
            route('portal.appointments.index')
        );

        return redirect()->route('registrar.appointments.index')
            ->with('success', 'GCash payment verified and student notified.');
    }

    public function rejectPayment(Request $request, AppointmentPayment $payment)
    {
        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($payment, $validated) {
            Appointment::whereKey($payment->appointment_id)->lockForUpdate()->firstOrFail();
            $lockedPayment = AppointmentPayment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            abort_if($lockedPayment->status !== 'pending' || $lockedPayment->payment_method !== 'gcash', 422, 'Payment is not pending.');

            $lockedPayment->update([
                'status'           => 'rejected',
                'rejection_reason' => $validated['rejection_reason'],
                'verified_by'      => Auth::id(),
                'verified_at'      => now(),
            ]);
        });

        $payment = $payment->fresh();

        NotificationService::send(
            $payment->appointment->user,
            'GCash Payment Rejected',
            'Your GCash payment was rejected. Reason: ' . $validated['rejection_reason'] .
            '. Please resubmit with the correct details.',
            'danger',
            route('portal.appointments.index')
        );

        return redirect()->route('registrar.appointments.index')
            ->with('success', 'Payment rejected and student notified.');
    }

    public function viewProof(AppointmentPayment $payment)
    {
        if (! $payment->proof_of_payment || ! Storage::disk('local')->exists($payment->proof_of_payment)) {
            abort(404, 'Proof not found.');
        }

        return response()->file(Storage::disk('local')->path($payment->proof_of_payment));
    }
}
