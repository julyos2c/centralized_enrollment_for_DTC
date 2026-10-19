@extends('adminlte::page')
@include('partials.navbar')

@section('title', 'Enroll Now')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header font-weight-bold u-text" >
                <i data-lucide="file-text" class="mr-2 u-link"></i>
                Enrollment Details
            </div>
            <div class="card-body">

                @if ($errors->any())
                    <div class="alert alert-danger">{{ $errors->first() }}</div>
                @endif

                <form method="POST" action="{{ route('portal.enrollment.store') }}">
                    @csrf

                    {{-- Program --}}
                    @if ($lockedProgram)
                        <div class="form-group">
                            <label>Program</label>
                            <div class="alert alert-success" style="border-radius:8px; padding:14px 16px;
                                        display:flex; align-items:center; gap:12px;">
                                <i data-lucide="check-circle" style="font-size:18px;"></i>
                                <div>
                                    <div style="font-size:14px; font-weight:600; color:var(--dtc-text);">
                                        {{ $lockedProgram->name }}
                                    </div>
                                    <div style="font-size:11px; margin-top:2px;">
                                        Based on your approved pre-enrollment application
                                    </div>
                                </div>
                            </div>
                            <input type="hidden" name="program_id" id="program_id" value="{{ $lockedProgram->id }}">
                        </div>
                    @else
                        <div class="form-group">
                            <label>Program</label>
                            <select name="program_id" id="program_id" class="form-control" required>
                                <option value="">— Select Program —</option>
                                @foreach ($programs as $program)
                                    <option value="{{ $program->id }}"
                                        {{ old('program_id') == $program->id ? 'selected' : '' }}>
                                        {{ $program->name }} ({{ $program->code }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    {{-- Enrollment Type --}}
                    <div class="form-group">
                        <label>Enrollment Type</label>
                        <div class="d-flex u-gap-15">
                            <label style="display:flex; align-items:center; gap:8px; font-weight:400; cursor:pointer; margin:0;">
                                <input type="radio" name="is_irregular" value="0" id="type-regular" checked>
                                Regular
                            </label>
                            <label style="display:flex; align-items:center; gap:8px; font-weight:400; cursor:pointer; margin:0;">
                                <input type="radio" name="is_irregular" value="1" id="type-irregular">
                                Irregular / Shiftee / Transferee
                            </label>
                        </div>
                        <small style="color:var(--dtc-text-muted); display:block; margin-top:4px;">
                            Choose Irregular if you need to take subjects from more than one year level or
                            semester in this enrollment (e.g. catching up on a missed subject).
                        </small>
                    </div>

                    {{-- Year + Semester --}}
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Year Level</label>
                                <select name="year_level" id="year_level" class="form-control" required>
                                    <option value="1" {{ old('year_level') == 1 ? 'selected' : '' }}>1st Year</option>
                                    <option value="2" {{ old('year_level') == 2 ? 'selected' : '' }}>2nd Year</option>
                                    <option value="3" {{ old('year_level') == 3 ? 'selected' : '' }}>3rd Year</option>
                                    <option value="4" {{ old('year_level') == 4 ? 'selected' : '' }}>4th Year</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Semester</label>
                                <select name="semester" id="semester" class="form-control" required>
                                    <option value="1" {{ old('semester') == 1 ? 'selected' : '' }}>1st Semester</option>
                                    <option value="2" {{ old('semester') == 2 ? 'selected' : '' }}>2nd Semester</option>
                                    <option value="3" {{ old('semester') == 3 ? 'selected' : '' }}>Summer</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    {{-- Subjects --}}
                    <div class="form-group">
                        <label>
                            Select Subjects
                            <span id="subjects-hint" style="font-size:11px; color:var(--dtc-text-muted); font-weight:400;">
                                (Select program, year and semester first)
                            </span>
                        </label>
                        <div id="subjects-container">
                            <div style="background:var(--dtc-surface-soft); border:2px dashed var(--dtc-border);
                                        border-radius:12px; padding:24px; text-align:center;">
                                <i data-lucide="book" class="mb-2 u-border-color" style="width:2em;height:2em"></i>
                                <p style="color:var(--dtc-text-muted); font-size:13px; margin:0;">
                                    Select program, year level, and semester to load subjects.
                                </p>
                            </div>
                        </div>
                    </div>

                    {{-- Fee Notice --}}
                    <div class="alert alert-info" style="border-radius:12px; padding:16px; margin-bottom:20px;
                                display:flex; align-items:center; gap:12px;">
                        <div style="width:40px; height:40px; border-radius:10px;
                                    background:var(--dtc-primary); display:flex; align-items:center;
                                    justify-content:center; flex-shrink:0;">
                            <i data-lucide="info" style="color:#fff; font-size:16px;"></i>
                        </div>
                        <div>
                            <div style="font-size:13px; font-weight:600;">
                                Enrollment Fee: ₱{{ number_format($fee, 2) }} (fixed rate)
                            </div>
                            <div style="font-size:12px; opacity:0.85; margin-top:2px;">
                                Payment is collected at the Cashier's Office after Registrar approval.
                            </div>
                        </div>
                    </div>

                    <div class="d-flex u-gap-10" >
                        <button type="submit" class="btn btn-primary flex-fill">
                            <i data-lucide="send" class="mr-1"></i> Submit Enrollment
                        </button>
                        <a href="{{ route('portal.dashboard') }}"
                           class="btn btn-secondary flex-fill">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Info Panel --}}
    <div class="col-lg-4">
        <div class="card u-primary-gradient-btn"
             >
            <div class="card-body p-4">
                <h5 style="font-weight:700; color:#fff; margin-bottom:16px;">
                    <i data-lucide="info" class="mr-2"></i> Enrollment Guide
                </h5>
                <div style="font-size:13px; line-height:1.8; opacity:0.9;">
                   @foreach([
                    ['icon'=>'mouse-pointer', 'text'=>'Select your program, year level, and semester.'],
                    ['icon'=>'check-square',  'text'=>'Choose the subjects you want to enroll in.'],
                    ['icon'=>'send',   'text'=>'Submit your enrollment for Registrar review.'],
                    ['icon'=>'bell',          'text'=>'Wait for approval notification.'],
                    ['icon'=>'banknote',    'text'=>'Pay ₱' . number_format($fee, 2) . ' at the Cashier after approval.'],
                ] as $step)
                    <div style="display:flex; gap:10px; margin-bottom:12px;">
                        <i data-lucide="{{ $step['icon'] }}" style="color:#FFC72C; margin-top:3px; flex-shrink:0;"></i>
                        <span>{{ $step['text'] }}</span>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('js')
<script>
function isIrregularMode() {
    return document.getElementById('type-irregular').checked;
}

function loadSubjects() {
    if (isIrregularMode()) {
        return loadAllSubjects();
    }

    const programId = document.getElementById('program_id').value;
    const yearLevel = document.getElementById('year_level').value;
    const semester  = document.getElementById('semester').value;
    const container = document.getElementById('subjects-container');

    if (!programId) return;

    container.innerHTML = `
        <div style="text-align:center; padding:20px; color:var(--dtc-text-muted);">
            <i data-lucide="loader" class="lucide-spin" style="width:2em;height:2em"></i>
            <p style="margin-top:8px; font-size:13px;">Loading subjects...</p>
        </div>`;

    fetch(`/portal/programs/${programId}/subjects?year_level=${yearLevel}&semester=${semester}`)
        .then(res => res.json())
        .then(subjects => {
            if (subjects.length === 0) {
                container.innerHTML = `
                    <div class="alert alert-warning" style="border-radius:12px; padding:16px; text-align:center;">
                        <i data-lucide="alert-triangle" style="margin-bottom:8px;"></i>
                        <p style="font-size:13px; margin:0;">
                            No subjects found for this selection.
                        </p>
                    </div>`;
                return;
            }

            container.innerHTML = `
                <div style="border:1.5px solid var(--dtc-border); border-radius:12px; overflow:hidden;">
                    <div style="background:var(--dtc-surface-soft); padding:10px 16px; font-size:11px;
                                font-weight:700; color:var(--dtc-text-secondary); text-transform:uppercase;
                                letter-spacing:0.5px; border-bottom:1px solid var(--dtc-border);">
                        ${subjects.length} Subject${subjects.length > 1 ? 's' : ''} Available
                    </div>
                    ${subjects.map(s => `
                        <label style="display:flex; align-items:center; gap:14px;
                                      padding:14px 16px; border-bottom:1px solid var(--dtc-border-soft);
                                      cursor:pointer; transition:background 0.2s; margin:0;"
                               onmouseover="this.style.background='var(--dtc-surface-soft)'"
                               onmouseout="this.style.background='transparent'">
                            <input type="checkbox" name="subject_ids[]" value="${s.id}"
                                   style="width:18px; height:18px; accent-color:var(--dtc-primary);
                                          cursor:pointer; flex-shrink:0;">
                            <div  class="u-flex-1">
                                <div  class="u-text-sm-bold-primary">
                                    ${s.subject_code}
                                </div>
                                <div  class="u-text-xxs-secondary">
                                    ${s.subject_name}
                                </div>
                            </div>
                        </label>
                    `).join('')}
                    <div style="padding:10px 16px; background:var(--dtc-surface-soft); border-top:1px solid var(--dtc-border);
                                text-align:right;">
                        <button type="button" onclick="selectAll()" style="font-size:12px;
                                color:var(--dtc-primary); background:none; border:none; cursor:pointer;
                                font-weight:600; font-family:'Poppins',sans-serif;">
                            Select All
                        </button>
                    </div>
                </div>`;
        })
        .catch(() => {
            container.innerHTML = `<div class="alert alert-danger">Failed to load subjects.</div>`;
        });
}

// ── Irregular mode: fetch ALL subjects for the program, grouped by
// year_level + semester, so students can select across groups in one
// enrollment (shiftees, transferees, catching up on missed subjects). ──

const YEAR_LABELS = { 1: '1st Year', 2: '2nd Year', 3: '3rd Year', 4: '4th Year' };
const SEM_LABELS  = { 1: '1st Semester', 2: '2nd Semester', 3: 'Summer' };

function loadAllSubjects() {
    const programId = document.getElementById('program_id').value;
    const container = document.getElementById('subjects-container');

    if (!programId) return;

    container.innerHTML = `
        <div style="text-align:center; padding:20px; color:var(--dtc-text-muted);">
            <i data-lucide="loader" class="lucide-spin" style="width:2em;height:2em"></i>
            <p style="margin-top:8px; font-size:13px;">Loading all subjects...</p>
        </div>`;

    fetch(`/portal/programs/${programId}/subjects?mode=all`)
        .then(res => res.json())
        .then(groups => {
            if (groups.length === 0) {
                container.innerHTML = `
                    <div class="alert alert-warning" style="border-radius:12px; padding:16px; text-align:center;">
                        <p style="font-size:13px; margin:0;">No subjects found for this program.</p>
                    </div>`;
                return;
            }

            container.innerHTML = groups.map(group => `
                <div style="border:1.5px solid var(--dtc-border); border-radius:12px; overflow:hidden; margin-bottom:14px;">
                    <div style="background:var(--dtc-surface-soft); padding:10px 16px; font-size:12px;
                                font-weight:700; color:var(--dtc-text); border-bottom:1px solid var(--dtc-border);">
                        ${YEAR_LABELS[group.year_level] || ('Year ' + group.year_level)} — ${SEM_LABELS[group.semester] || ('Semester ' + group.semester)}
                    </div>
                    ${group.subjects.map(s => `
                        <label style="display:flex; align-items:center; gap:14px;
                                      padding:14px 16px; border-bottom:1px solid var(--dtc-border-soft);
                                      cursor:pointer; transition:background 0.2s; margin:0;"
                               onmouseover="this.style.background='var(--dtc-surface-soft)'"
                               onmouseout="this.style.background='transparent'">
                            <input type="checkbox" name="subject_ids[]" value="${s.id}"
                                   style="width:18px; height:18px; accent-color:var(--dtc-primary);
                                          cursor:pointer; flex-shrink:0;">
                            <div  class="u-flex-1">
                                <div  class="u-text-sm-bold-primary">${s.subject_code}</div>
                                <div  class="u-text-xxs-secondary">${s.subject_name} · ${s.units} unit${s.units == 1 ? '' : 's'}</div>
                            </div>
                        </label>
                    `).join('')}
                </div>
            `).join('') + `
                <div style="text-align:right;">
                    <button type="button" onclick="selectAll()" style="font-size:12px;
                            color:var(--dtc-primary); background:none; border:none; cursor:pointer;
                            font-weight:600; font-family:'Poppins',sans-serif;">
                        Select All
                    </button>
                </div>`;
        })
        .catch(() => {
            container.innerHTML = `<div class="alert alert-danger">Failed to load subjects.</div>`;
        });
}

function selectAll() {
    document.querySelectorAll('input[name="subject_ids[]"]').forEach(cb => cb.checked = true);
}

document.getElementById('program_id').addEventListener('change', loadSubjects);
document.getElementById('year_level').addEventListener('change', loadSubjects);
document.getElementById('semester').addEventListener('change', loadSubjects);
document.getElementById('type-regular').addEventListener('change', function () {
    document.getElementById('subjects-hint').textContent = '(Select program, year and semester first)';
    loadSubjects();
});
document.getElementById('type-irregular').addEventListener('change', function () {
    document.getElementById('subjects-hint').textContent = '(Showing all subjects for the selected program, grouped by year & semester)';
    loadSubjects();
});

@if ($lockedProgram)
document.addEventListener('DOMContentLoaded', function () {
    loadSubjects();
});
@endif
</script>
@endsection