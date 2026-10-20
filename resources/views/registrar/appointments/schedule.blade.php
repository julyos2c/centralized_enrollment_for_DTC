@extends('adminlte::page')
@include('partials.navbar')
@section('title', 'Office Appointment Schedule')
@section('content_header')
<div class="d-flex justify-content-between align-items-center flex-wrap" style="gap:12px"><div><h4 class="mb-0 font-weight-bold u-text">Office Schedule & Closures</h4><p class="mb-0 u-text-secondary-sm">Configure the Registrar's operating hours and confirmed office closures.</p></div><a href="{{ route('registrar.appointments.slots') }}" class="btn btn-secondary btn-sm">Manage Slots</a></div>
@endsection
@section('content')
@if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<div class="row">
<div class="col-lg-6"><div class="card"><div class="card-header"><strong>Recurring schedule</strong></div><form method="POST" action="{{ route('registrar.appointments.schedule.update') }}">@csrf @method('PUT') @php($s = old() ? old() : ($schedule ?? []))<div class="card-body">
<p class="text-muted">Unconfigured schedules generate no automatic slots. Class suspensions do not affect office availability.</p>
<div class="form-group"><label>Operating weekdays</label><div class="d-flex flex-wrap" style="gap:12px">@foreach(['monday','tuesday','wednesday','thursday','friday','saturday','sunday'] as $day)<label class="mb-0"><input type="checkbox" name="weekdays[]" value="{{ $day }}" {{ in_array($day, $s['weekdays'] ?? [], true) ? 'checked' : '' }}> {{ ucfirst($day) }}</label>@endforeach</div></div>
<div class="form-row"><div class="form-group col"><label>Opens</label><input class="form-control" type="time" name="opens_at" value="{{ $s['opens_at'] ?? '' }}" required></div><div class="form-group col"><label>Closes</label><input class="form-control" type="time" name="closes_at" value="{{ $s['closes_at'] ?? '' }}" required></div></div>
<div class="form-row"><div class="form-group col"><label>Break starts (optional)</label><input class="form-control" type="time" name="break_start" value="{{ $s['break_start'] ?? '' }}"></div><div class="form-group col"><label>Break ends</label><input class="form-control" type="time" name="break_end" value="{{ $s['break_end'] ?? '' }}"></div></div>
<div class="form-row"><div class="form-group col"><label>Slot duration (minutes)</label><input class="form-control" type="number" name="duration" min="5" max="480" value="{{ $s['duration'] ?? 30 }}" required></div><div class="form-group col"><label>Capacity per slot</label><input class="form-control" type="number" name="capacity" min="1" max="50" value="{{ $s['capacity'] ?? 5 }}" required></div><div class="form-group col"><label>Booking window (days)</label><input class="form-control" type="number" name="window" min="1" max="90" value="{{ $s['window'] ?? 30 }}" required></div></div>
</div><div class="card-footer d-flex justify-content-between flex-wrap" style="gap:8px"><button class="btn btn-primary">Save & Generate Slots</button></form><form method="POST" action="{{ route('registrar.appointments.schedule.generate') }}">@csrf<button class="btn btn-outline-primary" {{ !$schedule ? 'disabled' : '' }}>Generate Slots Now</button></form></div></div></div>
<div class="col-lg-6"><div class="card"><div class="card-header"><strong>Confirm an office closure</strong></div><form method="POST" action="{{ route('registrar.appointments.closures.store') }}">@csrf<div class="card-body"><p class="text-muted">Only confirmed Registrar's Office closures block appointments. Class suspension alone has no effect. Existing appointments are retained and students are notified.</p>
<div class="form-row"><div class="form-group col"><label>From</label><input type="date" class="form-control" name="start_date" min="{{ today()->toDateString() }}" value="{{ old('start_date') }}" required></div><div class="form-group col"><label>Through</label><input type="date" class="form-control" name="end_date" min="{{ today()->toDateString() }}" value="{{ old('end_date') }}" required></div></div>
<div class="form-row"><div class="form-group col"><label>Partial closure starts (optional)</label><input type="time" class="form-control" name="start_time" value="{{ old('start_time') }}"></div><div class="form-group col"><label>Ends</label><input type="time" class="form-control" name="end_time" value="{{ old('end_time') }}"></div></div>
<div class="form-group"><label>Title</label><input class="form-control" name="title" maxlength="150" value="{{ old('title') }}" required></div>
<div class="form-group"><label>Type</label><select class="form-control" name="type" required>@foreach(['national_holiday'=>'National holiday','local_holiday'=>'Local holiday','office_closure'=>'Registrar office closure','weather'=>'Weather-related office closure','emergency'=>'Emergency office closure','other'=>'Other official closure'] as $key=>$label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></div>
<div class="form-group"><label>Reason</label><textarea class="form-control" name="reason" maxlength="1000">{{ old('reason') }}</textarea></div>
<div class="alert alert-info py-2" id="closure-impact-count">Select dates to see the number of existing bookings affected.</div>
<div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="confirmed" value="1" id="closure-confirm" required><label class="form-check-label" for="closure-confirm">I confirm the Registrar's Office is closed for these dates and times.</label></div>
<div class="form-check"><input class="form-check-input" type="checkbox" name="confirm_impact" value="1" id="closure-impact" required><label class="form-check-label" for="closure-impact">I reviewed the affected booking count above. Existing appointments will be retained for staff follow-up.</label></div>
</div><div class="card-footer"><button class="btn btn-primary">Record Confirmed Closure</button></div></form></div></div>
</div>
<div class="card"><div class="card-header"><strong>Closure calendar</strong></div><div class="card-body table-responsive"><table class="table"><thead><tr><th>Date(s)</th><th>Closure</th><th>Type</th><th>Existing appointments</th><th>Recorded by</th><th>Status</th><th>Action</th></tr></thead><tbody>@forelse($closures as $closure)<tr><td>{{ $closure->start_date }} – {{ $closure->end_date }}<br>{{ $closure->start_time ? $closure->start_time.'–'.$closure->end_time : 'All day' }}</td><td>{{ $closure->title }}<br><small>{{ $closure->reason }}</small></td><td>{{ str_replace('_',' ',ucfirst($closure->type)) }}</td><td>{{ $closure->affected_count }}</td><td>{{ optional($closure->creator)->name ?? 'Account removed' }}</td><td>{{ $closure->is_active && $closure->is_confirmed ? 'Confirmed' : 'Inactive' }}</td><td>@if($closure->is_active)<form method="POST" action="{{ route('registrar.appointments.closures.deactivate',$closure) }}">@csrf<button class="btn btn-sm btn-outline-danger">Deactivate</button></form>@endif</td></tr>@empty<tr><td colspan="7" class="text-center text-muted">No closures recorded.</td></tr>@endforelse</tbody></table>{{ $closures->links() }}</div></div>
@endsection
@section('js')
<script>
(() => {
    const fields = ['start_date', 'end_date', 'start_time', 'end_time'].map(name => document.querySelector('[name="' + name + '"]'));
    const output = document.getElementById('closure-impact-count');
    let timer;
    const refreshImpact = () => {
        clearTimeout(timer);
        timer = setTimeout(async () => {
            const values = Object.fromEntries(fields.map(field => [field.name, field.value]));
            if (!values.start_date || !values.end_date) return;
            output.textContent = 'Checking affected appointments…';
            try {
                const query = new URLSearchParams(values);
                const response = await fetch('{{ route('registrar.appointments.closures.impact') }}?' + query.toString(), { headers: { 'Accept': 'application/json' } });
                if (!response.ok) throw new Error('Could not check affected appointments.');
                const result = await response.json();
                output.textContent = result.affected_appointments + ' existing appointment(s) will be affected. They will remain in the system for staff follow-up.';
            } catch (error) { output.textContent = 'Unable to check the affected count. Try again before recording the closure.'; }
        }, 250);
    };
    fields.forEach(field => field.addEventListener('change', refreshImpact));
})();
</script>
@endsection
