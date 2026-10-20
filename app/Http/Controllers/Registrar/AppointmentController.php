<?php

namespace App\Http\Controllers\Registrar;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\AppointmentPayment;
use App\Models\AppointmentSlot;
use App\Models\DocumentRequest;
use App\Models\Setting;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\AppointmentScheduleService;
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
            ->whereHas('appointment', fn ($query) => $query->where('status', '!=', 'cancelled'))
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
        $result = DB::transaction(function () use ($appointment) {
            $slotId = Appointment::whereKey($appointment->id)->value('appointment_slot_id');
            $slot = AppointmentSlot::whereKey($slotId)->lockForUpdate()->firstOrFail();
            $locked = Appointment::whereKey($appointment->id)->lockForUpdate()->firstOrFail();

            if ((int) $locked->appointment_slot_id !== (int) $slot->id) {
                return 'slot_unavailable';
            }

            if ($locked->status === 'confirmed') {
                return 'already_confirmed';
            }
            if ($locked->status !== 'pending') {
                return 'invalid_status';
            }

            $linkedRequest = DocumentRequest::where('appointment_id', $locked->id)->lockForUpdate()->first();
            if (! $linkedRequest) {
                // Safely connect a legacy standalone booking only when there is
                // exactly one eligible document request it could belong to.
                $documentTypes = match ($locked->document_type) {
                    'transcript' => ['tor'],
                    'tor' => ['tor', 'true_copy'],
                    default => [$locked->document_type],
                };
                $eligibleRequests = DocumentRequest::where('user_id', $locked->user_id)
                    ->whereIn('document_type', $documentTypes)
                    ->where('status', 'ready')
                    ->where('is_paid', true)
                    ->where('payment_status', 'verified')
                    ->whereNull('appointment_id')
                    ->lockForUpdate()
                    ->get();

                if ($eligibleRequests->count() === 1) {
                    $linkedRequest = $eligibleRequests->first();
                    $linkedRequest->update(['appointment_id' => $locked->id]);
                }
            }
            $studentBooking = $linkedRequest
                && User::whereKey($locked->user_id)->first()?->hasRole(['student', 'new_applicant']);
            $requestInProgress = $studentBooking && in_array($linkedRequest->status, ['submitted', 'processing'], true);
            $requestReadyAndPaid = $linkedRequest
                && $linkedRequest->status === 'ready'
                && $linkedRequest->is_paid
                && $linkedRequest->payment_status === 'verified';
            if ($linkedRequest && ! $requestInProgress && ! $requestReadyAndPaid) {
                return 'request_not_ready';
            }

            $schedule = app(AppointmentScheduleService::class);
            $isPast = $slot->date < today()->toDateString()
                || ($slot->date === today()->toDateString() && $slot->start_time <= now()->format('H:i:s'));
            $activeBookings = $slot->appointments()->whereIn('status', ['pending', 'confirmed'])->count();
            if (($slot->status ?? 'open') !== 'open' || $isPast || $schedule->isClosed($slot->date, $slot->start_time, $slot->end_time)
                || $activeBookings > $slot->max_bookings) {
                return 'slot_unavailable';
            }

            if (! $linkedRequest) {
                $requestType = match ($locked->document_type) {
                    'transcript' => 'tor',
                    'tor' => 'true_copy',
                    default => $locked->document_type,
                };
                $baseFee = (float) Setting::get('document_fee_' . $requestType, match ($requestType) {
                    'tor' => 50,
                    'diploma' => 100,
                    'certification' => 30,
                    'true_copy' => 50,
                });

                $linkedRequest = DocumentRequest::create([
                    'user_id' => $locked->user_id,
                    'appointment_id' => $locked->id,
                    'document_type' => $requestType,
                    'copies' => 1,
                    'purpose' => $locked->purpose,
                    'status' => 'submitted',
                    'fee' => $baseFee,
                    'payment_method' => 'walk_in',
                    'payment_status' => 'unpaid',
                ]);
            }

            $locked->update(['status' => 'confirmed']);
            return 'confirmed';
        });

        if ($result !== 'confirmed') {
            $message = [
                'already_confirmed' => 'This appointment is already confirmed.',
                'invalid_status' => 'Only a pending appointment can be confirmed.',
                'request_not_ready' => 'This appointment can be confirmed while the document request is being processed, or when a ready request has verified payment.',
                'slot_unavailable' => 'This appointment slot is closed, in the past, or no longer within capacity. Please arrange another slot.',
            ][$result];
            return back()->with('error', $message);
        }

        $appointment->refresh()->load(['slot', 'user']);
        $linkedRequest = DocumentRequest::where('appointment_id', $appointment->id)->first();
        $pickupDate = \Carbon\Carbon::parse($appointment->slot->date)->format('M d, Y');
        $pickupTime = \Carbon\Carbon::parse($appointment->slot->start_time)->format('h:i A');
        if ($linkedRequest && in_array($linkedRequest->status, ['submitted', 'processing'], true)) {
            $notificationMessage = 'Your request for ' . $linkedRequest->document_label . ' and appointment on ' . $pickupDate . ' at ' . $pickupTime . ' are confirmed. The Registrar will process your document request. Bring a valid ID when you visit.';
            $applicantDestination = route('portal.appointments.index');
        } elseif ($linkedRequest) {
            $notificationMessage = 'Your pickup appointment for ' . $linkedRequest->document_label . ' is confirmed for ' . $pickupDate . ' at ' . $pickupTime . '. Bring a valid ID and your payment receipt. After pickup, the Registrar will mark your request as released.';
            $applicantDestination = $appointment->user->hasRole('alumni')
                ? route('portal.documents.index')
                : route('portal.appointments.index');
        } else {
            $notificationMessage = 'Your appointment for ' . ucfirst($appointment->document_type) . ' has been confirmed on ' . $pickupDate . ' at ' . $pickupTime . '. Bring a valid ID.';
            $applicantDestination = route('portal.appointments.index');
        }

        $notified = NotificationService::sendSafely(
            $appointment->user,
            'Appointment Confirmed',
            $notificationMessage,
            'success',
            $applicantDestination
        );

        $destination = $linkedRequest
            ? route('registrar.documents.index')
            : route('registrar.appointments.index');
        $nextStep = $linkedRequest && $linkedRequest->status === 'ready'
            ? ' The linked document request is ready for pickup and remains available in Document Requests to mark as released after handover.'
            : ($linkedRequest ? ' The linked document request is in Document Requests for processing and status updates.' : '');

        return redirect($destination)
            ->with('success', 'Appointment confirmed.' . $nextStep . ($notified ? ' The applicant was notified in-app.' : ' The applicant notification could not be saved; the failure was logged.'));
    }

    public function cancel(Request $request, Appointment $appointment)
    {
        $validated = $request->validate([
            'remarks' => ['required', 'string', 'max:500'],
        ]);
        $result = app(\App\Services\AppointmentCancellationService::class)
            ->cancel($appointment, Auth::user(), $request, $validated['remarks']);

        return match ($result['result']) {
            'already_cancelled' => back()->with('error', 'This appointment has already been cancelled.'),
            'invalid_status' => back()->with('error', 'Only pending or confirmed appointments can be cancelled.'),
            'verified_legacy_payment' => back()->with('error', 'This appointment has a verified payment. Resolve it with the Cashier before cancellation.'),
            default => redirect()->route('registrar.appointments.index')->with('success', 'Appointment cancelled. The linked document request and payment were handled according to their current status.'),
        };
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

        $notified = NotificationService::sendSafely(
            $payment->appointment->user,
            'GCash Payment Verified',
            'Your GCash payment for your ' .
            ucfirst($payment->appointment->document_type) .
            ' appointment has been verified.',
            'success',
            route('portal.appointments.index')
        );

        return redirect()->route('registrar.appointments.index')
            ->with('success', 'GCash payment verified.' . ($notified ? ' The applicant was notified in-app.' : ' The applicant notification could not be saved; the failure was logged.'));
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

        $notified = NotificationService::sendSafely(
            $payment->appointment->user,
            'GCash Payment Rejected',
            'Your GCash payment was rejected. Reason: ' . $validated['rejection_reason'] .
            '. Please resubmit with the correct details.',
            'danger',
            route('portal.appointments.index')
        );

        return redirect()->route('registrar.appointments.index')
            ->with('success', 'Payment rejected.' . ($notified ? ' The applicant was notified in-app.' : ' The applicant notification could not be saved; the failure was logged.'));
    }

    public function viewProof(AppointmentPayment $payment)
    {
        if (! $payment->proof_of_payment || ! Storage::disk('local')->exists($payment->proof_of_payment)) {
            abort(404, 'Proof not found.');
        }

        return response()->file(Storage::disk('local')->path($payment->proof_of_payment));
    }
}
