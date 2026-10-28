@php
    $isAlumniProfile = $profileRole === 'alumni';
    $isApplicantProfile = $profileRole === 'new-applicant';
    $profileEnrollment = $isAlumniProfile ? null : ($latestEnrollment ?? $enrollment ?? null);
    $profileProgram = $isApplicantProfile
        ? ($application?->program?->name ?? 'Not set')
        : ($profileEnrollment?->program?->name ?? $studentProfile?->program?->name);
    $profileYear = $profileEnrollment?->year_level ?? $studentProfile?->year_level;
    $profileYearLabel = [1 => '1st Year', 2 => '2nd Year', 3 => '3rd Year', 4 => '4th Year', 5 => '5th Year'][$profileYear] ?? 'Not set';
    $profileApplicationStatus = $appStatusLabel ?? 'Not started';
    $profileAcademicYear = $currentSchoolYear ?? $profileEnrollment?->school_year ?? $studentProfile?->enrolled_ay;
    $profileComplete = $profileEnrollment && $profileEnrollment->status === 'approved' && $profileEnrollment->is_paid;
    $profileTone = $isAlumniProfile || $profileComplete ? 'success' : ($profileEnrollment?->status === 'rejected' ? 'danger' : 'info');
    $profileRoleLabel = match ($profileRole) {
        'alumni' => 'Alumni',
        'new-applicant' => 'New applicant',
        default => 'Student',
    };
@endphp
<section class="card mb-3 dtc-dashboard-profile" aria-label="{{ $profileRoleLabel }} profile summary">
    <div class="card-body">
        <div class="dtc-profile-heading">
            @include('portal.partials.dashboard-avatar')
            <div class="dtc-profile-name">
                <span>Hello,</span>
                <h2>{{ auth()->user()->name }}</h2>
            </div>
            <span class="dtc-profile-badge is-{{ $profileTone }}"><span aria-hidden="true"></span>{{ $isApplicantProfile ? $profileApplicationStatus : ($isAlumniProfile ? 'Alumni' : ($profileComplete ? 'Officially enrolled' : $appStatusLabel)) }}</span>
        </div>
        <dl class="dtc-profile-facts">
            <div><dt>Program</dt><dd>{{ $profileProgram ?: 'Not set' }}</dd></div>
            <div class="dtc-profile-fact-accent">
                <dt>{{ $isAlumniProfile ? 'Graduating batch' : ($isApplicantProfile ? 'Application status' : 'Year level') }}</dt>
                <dd>{{ $isAlumniProfile ? ($studentProfile?->graduation_year ? 'Batch '.$studentProfile->graduation_year : 'Not set') : ($isApplicantProfile ? $profileApplicationStatus : $profileYearLabel) }}</dd>
            </div>
            <div>
                <dt>{{ $isAlumniProfile ? 'Student ID no.' : ($isApplicantProfile ? 'Reference number' : ($profileAcademicYear ? 'Enrolled AY' : 'Academic year')) }}</dt>
                <dd>{{ $isAlumniProfile ? ($studentProfile?->student_number ?: 'Not set') : ($isApplicantProfile ? ($application?->reference_no ?: 'Not assigned') : ($profileAcademicYear ? 'AY '.$profileAcademicYear : 'Not set')) }}</dd>
            </div>
        </dl>
        @unless($isAlumniProfile)
            <div class="dtc-profile-payment is-{{ $profileTone }}" role="status">
                <i data-lucide="{{ $profileComplete ? 'check' : 'credit-card' }}" aria-hidden="true"></i>
                <div>
                    @if($profileComplete)
                        <strong>Enrollment complete.</strong> <span>Your payment is confirmed.</span>
                    @else
                        <strong>Payment: {{ $paymentStatusLabel }}.</strong>
                    @endif
                    @if($profileEnrollment && !$profileComplete && $profileEnrollment->status === 'approved')
                        @if($profileEnrollment->payment?->status === 'pending')
                            <span>Your payment proof is awaiting Cashier verification.</span>
                        @elseif($profileEnrollment->payment?->status === 'rejected')
                            <span>Your payment proof was rejected. Review the Cashier's feedback before resubmitting.</span>
                        @else
                            <span>Enrollment fee: &#8369;{{ number_format($fee, 2) }}.</span>
                        @endif
                    @endif
                </div>
            </div>
        @endunless
    </div>
</section>
