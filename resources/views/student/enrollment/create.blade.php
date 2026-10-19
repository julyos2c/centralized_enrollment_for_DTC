@extends('adminlte::page')
@include('partials.navbar')

@section('title', 'Enroll Now')

@section('content_header')
    <div>
        <h4 class="mb-0 font-weight-bold" style="color:#1E293B;">Enrollment Form</h4>
        <p class="mb-0" style="color:#64748B; font-size:13px;">
            Select your program and subjects for this semester
        </p>
    </div>
@endsection

@if($activeEnrollment)
<div style="background:#FEF9C3; border:1.5px solid #FDE68A; border-radius:14px;
            padding:16px 20px; margin-bottom:20px; display:flex; align-items:center; gap:14px;">
    <i class="fas fa-exclamation-triangle" style="color:#D97706; font-size:20px; flex-shrink:0;"></i>
    <div>
        <div style="font-size:13px; font-weight:700; color:#92400E;">
            You have an active enrollment submission
        </div>
        <div style="font-size:12px; color:#B45309; margin-top:2px;">
            Your current enrollment is <strong>{{ ucfirst($activeEnrollment->status) }}</strong>.
            Please wait for it to be processed before submitting a new one.
        </div>
        <a href="{{ route('portal.enrollment.index') }}"
           style="font-size:12px; color:#0F4CDB; font-weight:600; text-decoration:none;">
            View my enrollment →
        </a>
    </div>
</div>
@endif

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header font-weight-bold" style="color:#1E293B;">
                <i class="fas fa-file-alt mr-2" style="color:#0F4CDB;"></i>
                Enrollment Details
            </div>
            <div class="card-body">

                @if ($errors->any())
                    <div class="alert alert-danger">{{ $errors->first() }}</div>
                @endif

                <form method="POST" action="{{ route('portal.enrollment.store') }}">
                    @csrf

                    {{-- Program --}}
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
                                </select>
                            </div>
                        </div>
                    </div>

                    {{-- Subjects --}}
                    <div class="form-group">
                        <label>
                            Select Subjects
                            <span style="font-size:11px; color:#94A3B8; font-weight:400;">
                                (Select program, year and semester first)
                            </span>
                        </label>
                        <div id="subjects-container">
                            <div style="background:#F8FAFC; border:2px dashed #E2E8F0;
                                        border-radius:12px; padding:24px; text-align:center;">
                                <i class="fas fa-book fa-2x mb-2" style="color:#E2E8F0;"></i>
                                <p style="color:#94A3B8; font-size:13px; margin:0;">
                                    Select program, year level, and semester to load subjects.
                                </p>
                            </div>
                        </div>
                    </div>

                    {{-- Fee Notice --}}
                    <div style="background:#EEF2FF; border:1.5px solid #C7D2FE;
                                border-radius:12px; padding:16px; margin-bottom:20px;
                                display:flex; align-items:center; gap:12px;">
                        <div style="width:40px; height:40px; border-radius:10px;
                                    background:#0F4CDB; display:flex; align-items:center;
                                    justify-content:center; flex-shrink:0;">
                            <i class="fas fa-info-circle" style="color:#fff; font-size:16px;"></i>
                        </div>
                        <div>
                            <div style="font-size:13px; font-weight:600; color:#1D4ED8;">
                                Enrollment Fee: ₱500.00 (fixed rate)
                            </div>
                            <div style="font-size:12px; color:#4338CA; margin-top:2px;">
                                Payment is collected at the Cashier's Office after Registrar approval.
                            </div>
                        </div>
                    </div>

                    <div class="d-flex" style="gap:10px;">
                        <button type="submit" class="btn btn-primary flex-fill">
                            <i class="fas fa-paper-plane mr-1"></i> Submit Enrollment
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
        <div class="card"
             style="background:linear-gradient(135deg,#0F4CDB,#1a5feb); border:none; color:#fff;">
            <div class="card-body p-4">
                <h5 style="font-weight:700; color:#fff; margin-bottom:16px;">
                    <i class="fas fa-info-circle mr-2"></i> Enrollment Guide
                </h5>
                <div style="font-size:13px; line-height:1.8; opacity:0.9;">
                    @foreach([
                        ['icon'=>'fa-mouse-pointer', 'text'=>'Select your program, year level, and semester.'],
                        ['icon'=>'fa-check-square',  'text'=>'Choose the subjects you want to enroll in.'],
                        ['icon'=>'fa-paper-plane',   'text'=>'Submit your enrollment for Registrar review.'],
                        ['icon'=>'fa-bell',          'text'=>'Wait for approval notification.'],
                        ['icon'=>'fa-money-bill',    'text'=>'Pay ₱500.00 at the Cashier after approval.'],
                    ] as $step)
                    <div style="display:flex; gap:10px; margin-bottom:12px;">
                        <i class="fas {{ $step['icon'] }}" style="color:#FFC72C; margin-top:3px; flex-shrink:0;"></i>
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
function loadSubjects() {
    const programId = document.getElementById('program_id').value;
    const yearLevel = document.getElementById('year_level').value;
    const semester  = document.getElementById('semester').value;
    const container = document.getElementById('subjects-container');

    if (!programId) return;

    container.innerHTML = `
        <div style="text-align:center; padding:20px; color:#94A3B8;">
            <i class="fas fa-spinner fa-spin fa-2x"></i>
            <p style="margin-top:8px; font-size:13px;">Loading subjects...</p>
        </div>`;

    fetch(`/portal/programs/${programId}/subjects?year_level=${yearLevel}&semester=${semester}`)
        .then(res => res.json())
        .then(subjects => {
            if (subjects.length === 0) {
                container.innerHTML = `
                    <div style="background:#FEF9C3; border:1.5px solid #FDE68A;
                                border-radius:12px; padding:16px; text-align:center;">
                        <i class="fas fa-exclamation-triangle" style="color:#D97706; margin-bottom:8px;"></i>
                        <p style="color:#92400E; font-size:13px; margin:0;">
                            No subjects found for this selection.
                        </p>
                    </div>`;
                return;
            }

            container.innerHTML = `
                <div style="border:1.5px solid #E2E8F0; border-radius:12px; overflow:hidden;">
                    <div style="background:#F8FAFC; padding:10px 16px; font-size:11px;
                                font-weight:700; color:#64748B; text-transform:uppercase;
                                letter-spacing:0.5px; border-bottom:1px solid #E2E8F0;">
                        ${subjects.length} Subject${subjects.length > 1 ? 's' : ''} Available
                    </div>
                    ${subjects.map(s => `
                        <label style="display:flex; align-items:center; gap:14px;
                                      padding:14px 16px; border-bottom:1px solid #F1F5F9;
                                      cursor:pointer; transition:background 0.2s; margin:0;"
                               onmouseover="this.style.background='#F8FAFC'"
                               onmouseout="this.style.background='transparent'">
                            <input type="checkbox" name="subject_ids[]" value="${s.id}"
                                   style="width:18px; height:18px; accent-color:#0F4CDB;
                                          cursor:pointer; flex-shrink:0;">
                            <div style="flex:1;">
                                <div style="font-size:13px; font-weight:600; color:#1E293B;">
                                    ${s.subject_code}
                                </div>
                                <div style="font-size:12px; color:#64748B;">
                                    ${s.subject_name}
                                </div>
                            </div>
                        </label>
                    `).join('')}
                    <div style="padding:10px 16px; background:#F8FAFC; border-top:1px solid #E2E8F0;
                                text-align:right;">
                        <button type="button" onclick="selectAll()" style="font-size:12px;
                                color:#0F4CDB; background:none; border:none; cursor:pointer;
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

function selectAll() {
    document.querySelectorAll('input[name="subject_ids[]"]').forEach(cb => cb.checked = true);
}

document.getElementById('program_id').addEventListener('change', loadSubjects);
document.getElementById('year_level').addEventListener('change', loadSubjects);
document.getElementById('semester').addEventListener('change', loadSubjects);
</script>
@endsection