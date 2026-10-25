<section class="card application-personal-card" aria-labelledby="applicant-information-title">
    <div class="card-header">
        <h2 id="applicant-information-title">Applicant Information</h2>
    </div>
    <div class="card-body">
        <div class="application-personal-name">
            <span class="application-detail-label">Full name</span>
            <p>{{ $application->full_name }}</p>
        </div>
        <dl class="application-personal-grid">
            <div><dt>Gender</dt><dd>{{ $application->gender ? ucfirst($application->gender) : 'Not provided' }}</dd></div>
            <div><dt>Birthdate</dt><dd>{{ $application->birthdate ? $application->birthdate->format('M d, Y') : 'Not provided' }}</dd></div>
            <div><dt>Age</dt><dd>{{ $application->age ?? 'Not provided' }}</dd></div>
            <div><dt>Nationality</dt><dd>{{ $application->nationality ?: 'Not provided' }}</dd></div>
        </dl>
        <details class="application-personal-details">
            <summary>Name details</summary>
            <dl class="application-personal-grid">
                @foreach(['honorific' => 'Honorific', 'first_name' => 'First Name', 'middle_name' => 'Middle Name', 'last_name' => 'Last Name', 'suffix' => 'Suffix'] as $field => $label)
                    @if(filled($application->{$field}))
                        <div><dt>{{ $label }}</dt><dd>{{ $application->{$field} }}</dd></div>
                    @endif
                @endforeach
            </dl>
        </details>
        @php
            $additionalDetails = collect([
                'Place of Birth' => $application->birth_place,
                'Religion' => $application->religion,
                'LRN' => $application->lrn,
                'Marital Status' => $application->marital_status ? ucfirst($application->marital_status) : null,
                'Spouse Name' => $application->marital_status === 'married' ? $application->spouse_name : null,
            ])->filter(fn ($value) => filled($value));
        @endphp
        @if($additionalDetails->isNotEmpty())
            <div class="application-personal-additional">
                <h3>Additional details</h3>
                <dl class="application-personal-grid">
                    @foreach($additionalDetails as $label => $value)
                        <div><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>
                    @endforeach
                </dl>
            </div>
        @endif
    </div>
</section>
