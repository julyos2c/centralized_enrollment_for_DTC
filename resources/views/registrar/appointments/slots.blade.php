@extends('adminlte::page')
@include('partials.navbar')

@section('title', 'Manage Appointment Slots')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center flex-wrap u-gap-10">
        <div>
            <h4 class="mb-0 font-weight-bold u-text">Manage Appointment Slots</h4>
            <p class="mb-0 u-text-secondary-sm">Set available dates and times for student appointments</p>
        </div>
        <div class="d-flex flex-wrap u-gap-8">
            <a href="{{ route('registrar.appointments.schedule') }}" class="btn btn-secondary btn-sm"><i data-lucide="calendar-cog" class="mr-1"></i> Office Schedule & Closures</a>
            <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#addSlotModal">
                <i data-lucide="plus" class="mr-1"></i> Add Slot
            </button>
            <a href="{{ route('registrar.appointments.index') }}" class="btn btn-secondary btn-sm">
                <i data-lucide="calendar-check" class="mr-1"></i> View Requests
            </a>
        </div>
    </div>
@endsection

@push('css')
<link rel="stylesheet" href="{{ asset('css/registrar-workspace.css') }}">
@endpush
@section('content')
<div class="registrar-workspace">


@if($errors->any() && !old('date'))
<div class="alert alert-danger">{{ $errors->first() }}</div>
@endif

@if(!$appointmentSchedule)
<div class="alert alert-info d-flex justify-content-between align-items-center flex-wrap" style="gap:10px">
    <span>Automatic slots are not configured yet. Set the Registrar's office hours and capacity first.</span>
    <a href="{{ route('registrar.appointments.schedule') }}" class="btn btn-sm btn-primary">Configure Office Schedule</a>
</div>
@endif

@php
    $totalSlots = $slots->total();
@endphp

{{-- ── Summary Stats ──────────────────────────────────────────────────── --}}
<div class="dtc-slot-stats mb-4">
    <div class="dtc-slot-stat-card">
        <div class="dtc-slot-stat-icon" style="background:var(--dtc-primary-soft); color:var(--dtc-primary);">
            <i data-lucide="calendar-days"></i>
        </div>
        <div>
            <div class="dtc-slot-stat-value">{{ $totalSlots }}</div>
            <div class="dtc-slot-stat-label">Total Slots</div>
        </div>
    </div>
    <div class="dtc-slot-stat-card">
        <div class="dtc-slot-stat-icon" style="background:#DCFCE7; color:var(--dtc-success);">
            <i data-lucide="circle-check"></i>
        </div>
        <div>
            <div class="dtc-slot-stat-value" style="color:var(--dtc-success);">{{ $availableCount }}</div>
            <div class="dtc-slot-stat-label">Available</div>
        </div>
    </div>
    <div class="dtc-slot-stat-card">
        <div class="dtc-slot-stat-icon" style="background:#FEE2E2; color:var(--dtc-danger);">
            <i data-lucide="circle-x"></i>
        </div>
        <div>
            <div class="dtc-slot-stat-value" style="color:var(--dtc-danger);">{{ $fullCount }}</div>
            <div class="dtc-slot-stat-label">Full</div>
        </div>
    </div>
    <div class="dtc-slot-stat-card">
        <div class="dtc-slot-stat-icon" style="background:var(--dtc-surface-soft); color:var(--dtc-text-muted);">
            <i data-lucide="clock-4"></i>
        </div>
        <div>
            <div class="dtc-slot-stat-value" style="color:var(--dtc-text-muted);">{{ $pastCount }}</div>
            <div class="dtc-slot-stat-label">Past</div>
        </div>
    </div>
</div>

{{-- ── Slot Cards ─────────────────────────────────────────────────────── --}}
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap u-gap-8">
        <span class="font-weight-bold u-text">
            <i data-lucide="clock" class="mr-2 u-link"></i> Available Slots
        </span>
        <span class="u-text-secondary-sm">{{ $slots->total() }} total</span>
    </div>

    <div class="card-body p-0">
        @forelse ($slots as $slot)
        @php
            $available   = $slot->availableSlots();
            $booked      = $slot->appointments_count;
            $max         = $slot->max_bookings;
            $isPast      = $slot->date < today()->toDateString() || ($slot->date === today()->toDateString() && $slot->end_time <= now()->format('H:i:s'));
            $isFull      = $available <= 0;
            $isClosure   = app(\App\Services\AppointmentScheduleService::class)->isClosed($slot->date, $slot->start_time, $slot->end_time);
            $isClosed    = ($slot->status ?? 'open') === 'closed' || $isClosure;
            $isNearlyFull = !$isFull && ($available / max($max,1)) <= 0.3;
            $pct         = $max > 0 ? round(($booked / $max) * 100) : 0;
            $barColor    = $isFull ? 'var(--dtc-danger)' : ($isNearlyFull ? 'var(--dtc-accent)' : 'var(--dtc-success)');
        @endphp

        <div class="dtc-slot-row {{ $isPast ? 'is-past' : '' }}">

            {{-- Date chip --}}
            <div class="dtc-slot-date-block">
                <div class="dtc-date-chip {{ $isPast ? 'is-muted' : '' }}">
                    {{ \Carbon\Carbon::parse($slot->date)->format('M') }}<br>
                    <span class="dtc-date-chip-day">{{ \Carbon\Carbon::parse($slot->date)->format('d') }}</span>
                </div>
                <div class="dtc-slot-day-label">
                    {{ \Carbon\Carbon::parse($slot->date)->format('D, Y') }}
                    @if($isPast)
                    <span class="dtc-slot-past-badge">Past</span>
                    @endif
                </div>
            </div>

            {{-- Time --}}
            <div class="dtc-slot-time-block">
                <i data-lucide="clock" class="dtc-slot-meta-icon"></i>
                <span>
                    {{ \Carbon\Carbon::parse($slot->start_time)->format('h:i A') }}
                    <span class="u-text-muted">—</span>
                    {{ \Carbon\Carbon::parse($slot->end_time)->format('h:i A') }}
                </span>
            </div>

            {{-- Capacity bar --}}
            <div class="dtc-slot-capacity-block">
                <div class="dtc-slot-capacity-nums">
                    <span>
                        <strong style="color:var(--dtc-primary);">{{ $booked }}</strong>
                        <span class="u-text-muted">/ {{ $max }}</span>
                    </span>
                    <span class="dtc-slot-avail-text" style="color:{{ $isFull ? 'var(--dtc-danger)' : 'var(--dtc-success)' }};">
                        {{ $available }} left
                    </span>
                </div>
                <div class="dtc-slot-bar-track">
                    <div class="dtc-slot-bar-fill" style="width:{{ $pct }}%; background:{{ $barColor }};"></div>
                </div>
            </div>

            {{-- Status --}}
            <div class="dtc-slot-status-block">
                <x-dtc.status-badge :status="$slot->source === 'automatic' ? 'Automatic' : 'Manual'" :variant="$slot->source === 'automatic' ? 'primary' : 'secondary'" />
                @if($isClosed)
                    <x-dtc.status-badge :status="$isClosure ? 'Office Closed' : 'Closed'" variant="secondary" />
                @elseif($isPast)
                    <x-dtc.status-badge status="Past" variant="secondary" />
                @elseif($isFull)
                    <x-dtc.status-badge status="Full" variant="danger" />
                @elseif($isNearlyFull)
                    <x-dtc.status-badge status="Nearly Full" variant="warning" />
                @else
                    <x-dtc.status-badge status="Available" variant="success" />
                @endif
            </div>

            {{-- Actions --}}
            <div class="dtc-slot-actions-block">
                <form method="POST" action="{{ route('registrar.appointments.slots.status', $slot) }}" id="slot-status-{{ $slot->id }}">
                    @csrf @method('PUT')
                    <input type="hidden" name="status" value="{{ ($slot->status ?? 'open') === 'open' ? 'closed' : 'open' }}">
                    <button class="dtc-icon-btn" type="button" title="{{ ($slot->status ?? 'open') === 'open' ? 'Close slot' : 'Reopen slot' }}" data-dtc-confirm data-dtc-confirm-tone="neutral" data-dtc-confirm-title="{{ ($slot->status ?? 'open') === 'open' ? 'Close appointment slot?' : 'Reopen appointment slot?' }}" data-dtc-confirm-message="Changing this slot affects future bookings. Existing appointments remain on record." data-dtc-confirm-ok="{{ ($slot->status ?? 'open') === 'open' ? 'Close Slot' : 'Reopen Slot' }}" data-dtc-confirm-form="#slot-status-{{ $slot->id }}"><i data-lucide="{{ ($slot->status ?? 'open') === 'open' ? 'lock' : 'lock-open' }}"></i></button>
                </form>
                {{-- Edit --}}
                <button type="button"
                        class="dtc-icon-btn dtc-slot-edit-btn"
                        title="Edit slot"
                        data-slot-id="{{ $slot->id }}"
                        data-slot-date="{{ $slot->date }}"
                        data-slot-start="{{ \Carbon\Carbon::parse($slot->start_time)->format('H:i') }}"
                        data-slot-end="{{ \Carbon\Carbon::parse($slot->end_time)->format('H:i') }}"
                        data-slot-max="{{ $slot->max_bookings }}"
                        data-slot-booked="{{ $booked }}"
                        data-slot-url="{{ route('registrar.appointments.slots.update', $slot) }}">
                    <i data-lucide="pencil"></i>
                </button>

                {{-- Delete --}}
                <form id="del-slot-{{ $slot->id }}"
                      method="POST"
                      action="{{ route('registrar.appointments.slots.delete', $slot) }}">
                    @csrf
                    @method('DELETE')
                    <button type="button"
                            class="dtc-icon-btn danger"
                            title="Delete slot"
                            data-dtc-confirm
                            data-dtc-confirm-title="Delete appointment slot?"
                            data-dtc-confirm-message="Delete this unused slot? Slots with appointment history are protected from deletion."
                            data-dtc-confirm-ok="Delete Slot"
                            data-dtc-confirm-tone="danger"
                            data-dtc-confirm-form="#del-slot-{{ $slot->id }}">
                        <i data-lucide="trash-2"></i>
                    </button>
                </form>
            </div>

        </div>
        @empty
        <div class="text-center py-5">
            <i data-lucide="calendar-x" class="mb-3 u-border-color" style="width:3em;height:3em;display:block;margin:0 auto 12px;"></i>
            <p style="color:var(--dtc-text-muted); font-size:13px;">No slots added yet.</p>
        </div>
        @endforelse
    </div>

    {{-- Pagination --}}
    <div class="card-footer u-panel-footer">
        <div class="d-flex justify-content-between align-items-center flex-wrap u-gap-15">
            <small class="u-text-secondary">
                Showing
                <strong>{{ $slots->firstItem() ?? 0 }}</strong>
                to
                <strong>{{ $slots->lastItem() ?? 0 }}</strong>
                of
                <strong>{{ $slots->total() }}</strong>
                slots
            </small>
            @if($slots->hasPages())
            <div>{{ $slots->onEachSide(1)->links('pagination::bootstrap-4') }}</div>
            @endif
        </div>
    </div>
</div>

{{-- ── ADD SLOT MODAL ─────────────────────────────────────────────────── --}}
<div class="modal fade" id="addSlotModal" tabindex="-1" role="dialog" aria-labelledby="addSlotModalLabel" aria-modal="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content dtc-slot-modal-content">
            <form method="POST" action="{{ route('registrar.appointments.slots.store') }}">
                @csrf
                <div class="dtc-slot-modal-header">
                    <div class="dtc-slot-modal-header-icon">
                        <i data-lucide="calendar-plus"></i>
                    </div>
                    <div>
                        <h5 class="dtc-slot-modal-title">Add New Slot</h5>
                        <p class="dtc-slot-modal-sub">Create a new appointment window for students</p>
                    </div>
                    <button type="button" class="dtc-confirm-close ml-auto" data-dismiss="modal" aria-label="Close">
                        <i data-lucide="x"></i>
                    </button>
                </div>
                <div class="modal-body px-4 py-3">
                    @if ($errors->any() && !old('edit_slot_id') && (old('date') || old('start_time')))
                        <div class="alert alert-danger py-2 px-3" style="font-size:13px;">{{ $errors->first() }}</div>
                    @endif

                    <div class="form-group">
                        <label class="dtc-slot-label">Date</label>
                        <input type="date" name="date" class="form-control dtc-slot-input"
                               min="{{ today()->toDateString() }}"
                               value="{{ old('date') }}" required>
                    </div>
                    <div class="row">
                        <div class="col-6">
                            <div class="form-group">
                                <label class="dtc-slot-label">Start Time</label>
                                <input type="time" name="start_time" class="form-control dtc-slot-input"
                                       value="{{ old('start_time') }}" required>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-group">
                                <label class="dtc-slot-label">End Time</label>
                                <input type="time" name="end_time" class="form-control dtc-slot-input"
                                       value="{{ old('end_time') }}" required>
                            </div>
                        </div>
                    </div>
                    <div class="form-group mb-0">
                        <label class="dtc-slot-label">Max Bookings</label>
                        <input type="number" name="max_bookings" class="form-control dtc-slot-input"
                               value="{{ old('max_bookings', 5) }}" min="1" max="50" required>
                        <small class="dtc-slot-hint">Maximum students that can book this slot</small>
                    </div>
                </div>
                <div class="modal-footer border-0 px-4 pb-4 pt-2">
                    <button type="button" class="dtc-btn dtc-btn-ghost" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="dtc-btn dtc-btn-primary">
                        <i data-lucide="plus"></i> Add Slot
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ── EDIT SLOT MODAL ─────────────────────────────────────────────────── --}}
<div class="modal fade" id="editSlotModal" tabindex="-1" role="dialog" aria-labelledby="editSlotModalLabel" aria-modal="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content dtc-slot-modal-content">
            <form method="POST" id="editSlotForm" action="">
                @csrf
                @method('PUT')
                <div class="dtc-slot-modal-header">
                    <div class="dtc-slot-modal-header-icon" style="background:var(--dtc-primary-soft); color:var(--dtc-primary);">
                        <i data-lucide="pencil"></i>
                    </div>
                    <div>
                        <h5 class="dtc-slot-modal-title">Edit Slot</h5>
                        <p class="dtc-slot-modal-sub">Update the slot's date, time, or capacity</p>
                    </div>
                    <button type="button" class="dtc-confirm-close ml-auto" data-dismiss="modal" aria-label="Close">
                        <i data-lucide="x"></i>
                    </button>
                </div>
                <div class="modal-body px-4 py-3">
                    <div id="editSlotErrors" class="alert alert-danger py-2 px-3 mb-3" style="font-size:13px; display:none;">{{ $errors->first() }}</div>

                    <div class="form-group">
                        <label class="dtc-slot-label">Date</label>
                        <input type="date" name="date" id="edit_date" class="form-control dtc-slot-input" required>
                    </div>
                    <div class="row">
                        <div class="col-6">
                            <div class="form-group">
                                <label class="dtc-slot-label">Start Time</label>
                                <input type="time" name="start_time" id="edit_start_time" class="form-control dtc-slot-input" required>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-group">
                                <label class="dtc-slot-label">End Time</label>
                                <input type="time" name="end_time" id="edit_end_time" class="form-control dtc-slot-input" required>
                            </div>
                        </div>
                    </div>
                    <div class="form-group mb-0">
                        <label class="dtc-slot-label">Max Bookings</label>
                        <input type="number" name="max_bookings" id="edit_max_bookings"
                               class="form-control dtc-slot-input" min="1" max="50" required>
                        <small class="dtc-slot-hint" id="edit_max_hint">Maximum students that can book this slot</small>
                    </div>
                </div>
                <div class="modal-footer border-0 px-4 pb-4 pt-2">
                    <button type="button" class="dtc-btn dtc-btn-ghost" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="dtc-btn dtc-btn-primary">
                        <i data-lucide="save"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


</div>
@endsection

@section('css')
    <link rel="stylesheet" href="{{ asset('css/registrar-slots.css') }}">
@endsection

@section('js')
<script>
(function () {
    // Re-open add modal on validation error
    @if($errors->any() && (old('date') || old('start_time') || old('max_bookings')))
    @if(old('edit_slot_id'))
    $(document).ready(function () {
        var button = document.querySelector('.dtc-slot-edit-btn[data-slot-id="{{ old('edit_slot_id') }}"]');
        if (button) { button.click(); document.getElementById('edit_date').value = @json(old('date')); document.getElementById('edit_start_time').value = @json(old('start_time')); document.getElementById('edit_end_time').value = @json(old('end_time')); document.getElementById('edit_max_bookings').value = @json(old('max_bookings')); document.getElementById('editSlotErrors').style.display = 'block'; }
    });
    @else
    $(document).ready(function () { $('#addSlotModal').modal('show'); });
    @endif
    @endif

    // Edit slot modal: populate fields and fix focus
    var lastEditTrigger = null;

    document.querySelectorAll('.dtc-slot-edit-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            lastEditTrigger = btn;

            var url    = btn.dataset.slotUrl;
            var date   = btn.dataset.slotDate;
            var start  = btn.dataset.slotStart;
            var end    = btn.dataset.slotEnd;
            var max    = btn.dataset.slotMax;
            var booked = parseInt(btn.dataset.slotBooked, 10);

            document.getElementById('editSlotForm').action         = url;
            document.getElementById('edit_date').value             = date;
            document.getElementById('edit_start_time').value       = start;
            document.getElementById('edit_end_time').value         = end;
            document.getElementById('edit_max_bookings').value     = max;
            document.getElementById('edit_max_bookings').min       = booked || 1;

            var hint = document.getElementById('edit_max_hint');
            if (booked > 0) {
                hint.textContent = booked + ' student' + (booked !== 1 ? 's' : '') + ' already booked - minimum is ' + booked + '.';
                hint.style.color = 'var(--dtc-accent)';
            } else {
                hint.textContent = 'Maximum students that can book this slot';
                hint.style.color = '';
            }

            document.getElementById('editSlotErrors').style.display = 'none';
            $('#editSlotModal').modal('show');
        });
    });

    // Return focus to trigger after modal closes - prevents aria-hidden warning.
    $('#editSlotModal').on('hidden.bs.modal', function () {
        if (lastEditTrigger) {
            lastEditTrigger.focus();
            lastEditTrigger = null;
        }
    });

    $('#addSlotModal').on('hidden.bs.modal', function () {
        var addBtn = document.querySelector('[data-target="#addSlotModal"]');
        if (addBtn) addBtn.focus();
    });
})();
</script>
@endsection
