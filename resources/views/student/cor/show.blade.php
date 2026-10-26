@extends('adminlte::page')
@include('partials.navbar')

@section('title', 'Certificate of Registration')

@push('css')
    <link rel="stylesheet" href="{{ asset_v('css/cor-details.css') }}">
@endpush

@section('content_header')
    <div class="d-flex justify-content-between align-items-center flex-wrap u-gap-10">
        <div>
            <h4 class="mb-0 font-weight-bold u-text">Certificate of Registration</h4>
            <p class="mb-0 u-text-secondary-sm">
                @if(isset($backUrl))
                    {{ $enrollment->user->name }}'s official Study Load for the current semester
                @else
                    Your official Study Load for the current semester
                @endif
            </p>
        </div>
        <div class="d-flex u-gap-10">
            @if(isset($backUrl))
                <a href="{{ $backUrl }}" class="btn btn-secondary btn-sm">
                    <i data-lucide="arrow-left" class="mr-1"></i> Back
                </a>
                <a href="{{ route('registrar.enrollments.cor.download', $enrollment) }}" class="btn btn-primary btn-sm">
                    <i data-lucide="download" class="mr-1"></i> Download PDF
                </a>
            @else
                <a href="{{ route('portal.cor.download') }}" class="btn btn-primary btn-sm">
                    <i data-lucide="download" class="mr-1"></i> Download PDF
                </a>
            @endif
        </div>
    </div>
@endsection

@section('content')

<div class="card cor-document" style="border:1px solid var(--dtc-border);">
    <div class="card-body">

        {{-- Letterhead --}}
        <div class="text-center mb-4 cor-letterhead" style="border-bottom:1px solid var(--dtc-border); padding-bottom:14px;">
            <h4 class="mb-0 font-weight-bold u-text">Danao Technological College</h4>
            <p class="mb-0 u-text-secondary-sm">Poblacion, Danao, Bohol, Philippines</p>
            <p class="mb-0 u-text-secondary-sm font-weight-bold mt-2">Office of the College Registrar</p>
        </div>

        <div class="text-center mb-4 cor-program">
            <h5 class="mb-0 font-weight-bold u-text text-uppercase">{{ $enrollment->program->name }}</h5>
            <p class="mb-0 u-text-secondary-sm">
                {{ $enrollment->semester == 1 ? 'First' : ($enrollment->semester == 2 ? 'Second' : 'Summer') }} Semester
                A.Y. {{ $enrollment->school_year ?? \App\Models\Setting::get('current_school_year', now()->year . '-' . (now()->year + 1)) }}
            </p>
        </div>

        <div class="d-flex justify-content-between flex-wrap u-gap-10 mb-3 cor-student">
            <p class="mb-0 u-text"><strong>Name:</strong> {{ $enrollment->user->name }}</p>
            <p class="mb-0 u-text"><strong>Program:</strong> {{ $enrollment->program->code }} {{ ['','I','II','III','IV'][$enrollment->year_level] ?? '' }}</p>
        </div>

        <div class="table-responsive cor-subjects">
            <table class="table mb-0 cor-table" style="border:1px solid var(--dtc-border);" aria-label="Registered subjects">
                <thead>
                    <tr style="background:var(--dtc-surface-soft);">
                        <th class="u-text">Time / Day</th>
                        <th class="u-text">Room</th>
                        <th class="u-text">Course Code</th>
                        <th class="u-text">Course Description</th>
                        <th class="u-text">Units</th>
                        <th class="u-text">Instructor</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($subjects as $subject)
                        @php $sched = $subject->schedules->first(); @endphp
                        <tr>
                            <td class="u-text-sm-secondary-primary cor-schedule" data-cor-label="Time / day">
                                @if($sched)
                                    {{ \Illuminate\Support\Carbon::parse($sched->time_start)->format('g:i A') }}-{{ \Illuminate\Support\Carbon::parse($sched->time_end)->format('g:i A') }} {{ $sched->day_pattern }}
                                @else
                                    TBA
                                @endif
                            </td>
                            <td class="u-text-sm-secondary-primary cor-room" data-cor-label="Room">{{ $sched->room ?? 'TBA' }}</td>
                            <td class="u-text-sm-secondary-primary cor-code">
                                <span class="dtc-status-badge is-neutral">{{ $subject->subject_code }}</span>
                            </td>
                            <td class="u-text-sm-secondary-primary cor-subject-name">{{ $subject->subject_name }}</td>
                            <td class="u-text-sm-secondary-primary cor-units" data-cor-label="Units">{{ rtrim(rtrim(number_format($subject->units, 1), '0'), '.') }}</td>
                            <td class="u-text-sm-secondary-primary cor-instructor" data-cor-label="Instructor">{{ $sched->instructor_name ?? 'TBA' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-4 u-text-muted">No subjects found for this enrollment.</td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4" class="text-right font-weight-bold u-text">TOTAL UNITS</td>
                        <td class="font-weight-bold u-text">{{ rtrim(rtrim(number_format($totalUnits, 1), '0'), '.') }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="text-center mt-5 cor-signature">
            <p class="mb-0 font-weight-bold u-text text-uppercase cor-signature-name" style="border-bottom:1px solid var(--dtc-border); display:inline-block; padding-top:4px; min-width:280px;">
                {{ \App\Models\Setting::get('registrar_name', 'College Registrar') }}
            </p>
            <p class="mb-0 u-text-secondary-sm">College Registrar</p>
        </div>

    </div>
</div>

@endsection
