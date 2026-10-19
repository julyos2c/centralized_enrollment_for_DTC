<?php

namespace App\Http\Controllers\Registrar;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\AppointmentSlot;
use App\Models\AuditLog;
use App\Models\OfficeClosure;
use App\Models\Setting;
use App\Services\AppointmentScheduleService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AppointmentScheduleController extends Controller
{
    public function index()
    {
        $closures = OfficeClosure::with('creator')->orderByDesc('start_date')->paginate(15);
        $closures->getCollection()->transform(function ($closure) {
            $closure->affected_count = Appointment::whereIn('status', ['pending', 'confirmed'])->whereHas('slot', function ($query) use ($closure) {
                $query->whereBetween('date', [$closure->start_date, $closure->end_date]);
                if ($closure->start_time) $query->where('start_time', '<', $closure->end_time)->where('end_time', '>', $closure->start_time);
            })->count();
            return $closure;
        });
        return view('registrar.appointments.schedule', [
            'schedule' => app(AppointmentScheduleService::class)->schedule(),
            'closures' => $closures,
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'weekdays' => ['required', 'array', 'min:1'],
            'weekdays.*' => ['required', 'in:monday,tuesday,wednesday,thursday,friday,saturday,sunday'],
            'opens_at' => ['required', 'date_format:H:i'], 'closes_at' => ['required', 'date_format:H:i', 'after:opens_at'],
            'break_start' => ['nullable', 'date_format:H:i', 'required_with:break_end'],
            'break_end' => ['nullable', 'date_format:H:i', 'required_with:break_start', 'after:break_start'],
            'duration' => ['required', 'integer', 'min:5', 'max:480'],
            'capacity' => ['required', 'integer', 'min:1', 'max:50'],
            'window' => ['required', 'integer', 'min:1', 'max:90'],
        ]);
        if (!empty($data['break_start']) && ($data['break_start'] < $data['opens_at'] || $data['break_end'] > $data['closes_at'])) {
            return back()->withErrors(['break_start' => 'The break must fall within office hours.'])->withInput();
        }
        $data['weekdays'] = array_values(array_unique($data['weekdays']));
        Setting::set('appointment_schedule', json_encode($data));
        $generated = app(AppointmentScheduleService::class)->generate();
        AuditLog::create(['user_id' => auth()->id(), 'action' => 'appointment.schedule.updated', 'description' => 'Updated Registrar appointment operating schedule.', 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent()]);
        return back()->with('status', "Appointment schedule saved. Slot generation completed ({$generated} new slot(s)); students can book future open times. Existing slots and bookings were preserved.");
    }

    public function generate(AppointmentScheduleService $service)
    {
        $count = $service->generate();
        return redirect()->route('registrar.appointments.schedule')->with('status', "Generated {$count} new slot(s). Existing and manual slots were preserved.");
    }

    public function previewClosureImpact(Request $request)
    {
        $data = $request->validate(['start_date' => ['required', 'date'], 'end_date' => ['required', 'date', 'after_or_equal:start_date'], 'start_time' => ['nullable', 'date_format:H:i'], 'end_time' => ['nullable', 'date_format:H:i', 'required_with:start_time', 'after:start_time']]);
        $count = Appointment::whereIn('status', ['pending', 'confirmed'])->whereHas('slot', function ($query) use ($data) {
            $query->whereBetween('date', [$data['start_date'], $data['end_date']]);
            if (!empty($data['start_time'])) $query->where('start_time', '<', $data['end_time'])->where('end_time', '>', $data['start_time']);
        })->count();
        return response()->json(['affected_appointments' => $count]);
    }

    public function storeClosure(Request $request)
    {
        $data = $request->validate([
            'start_date' => ['required', 'date'], 'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'start_time' => ['nullable', 'date_format:H:i', 'required_with:end_time'],
            'end_time' => ['nullable', 'date_format:H:i', 'required_with:start_time', 'after:start_time'],
            'title' => ['required', 'string', 'max:150'], 'type' => ['required', 'in:national_holiday,local_holiday,office_closure,weather,emergency,other'],
            'reason' => ['nullable', 'string', 'max:1000'], 'confirm_impact' => ['required', 'accepted'], 'confirmed' => ['required', 'accepted'],
        ]);
        if (!empty($data['start_time']) && $data['start_date'] !== $data['end_date']) {
            return back()->withErrors(['start_date' => 'Partial-day closures must apply to one date. Use full-day closure for a date range.'])->withInput();
        }
        $affected = Appointment::with('user')->whereIn('status', ['pending', 'confirmed'])->whereHas('slot', function ($query) use ($data) {
            $query->whereBetween('date', [$data['start_date'], $data['end_date']]);
            if (!empty($data['start_time'])) $query->where('start_time', '<', $data['end_time'])->where('end_time', '>', $data['start_time']);
        })->get();
        $confirmed = true;
        if ($confirmed && $affected->isNotEmpty() && ! $request->boolean('confirm_impact')) {
            return back()->withErrors(['confirm_impact' => 'Review and confirm the affected appointment count before saving this closure.'])->withInput();
        }
        $closure = DB::transaction(function () use ($data, $confirmed) {
            return OfficeClosure::create(array_merge(collect($data)->except('confirm_impact')->all(), ['is_confirmed' => $confirmed, 'is_active' => true, 'created_by' => auth()->id(), 'updated_by' => auth()->id()]));
        });
        if ($confirmed && $affected->isNotEmpty()) {
            foreach ($affected as $appointment) {
                NotificationService::send($appointment->user, 'Appointment may be affected by an office closure', 'The Registrar has recorded an office closure affecting your appointment. Please check your appointment page for follow-up.', 'warning', route('portal.appointments.index'));
            }
        }
        AuditLog::create(['user_id' => auth()->id(), 'action' => 'appointment.closure.created', 'description' => "Recorded office closure {$closure->title}; affected appointments: {$affected->count()}.", 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent()]);
        return back()->with('status', 'Office closure recorded. Existing appointments and payment records were preserved.');
    }

    public function deactivate(OfficeClosure $closure)
    {
        $closure->update(['is_active' => false, 'updated_by' => auth()->id()]);
        AuditLog::create(['user_id' => auth()->id(), 'action' => 'appointment.closure.deactivated', 'description' => "Deactivated office closure {$closure->title}.", 'ip_address' => request()->ip(), 'user_agent' => request()->userAgent()]);
        return back()->with('status', 'Closure deactivated; existing slots were not reopened or changed.');
    }
}
