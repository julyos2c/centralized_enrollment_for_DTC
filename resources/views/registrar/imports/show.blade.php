@extends('adminlte::page')
@include('partials.navbar')
@section('title', 'Import '.$batch->reference)
@section('content_header')<div class="d-flex justify-content-between flex-wrap"><div><h4 class="font-weight-bold mb-1">Import {{ $batch->reference }}</h4><p class="text-muted">{{ ucfirst($batch->import_type) }} · {{ $batch->created_at->format('M j, Y g:i A') }}</p></div><a class="btn btn-outline-secondary align-self-start" href="{{ route('registrar.imports.index') }}">Import Center</a></div>@endsection
@push('css')
<link rel="stylesheet" href="{{ asset_v('css/registrar-workspace.css') }}">
@endpush
@section('content')
<div class="registrar-workspace">
<div class="card"><div class="card-body"><p><strong>Status:</strong> {{ ucfirst($batch->status) }} <span class="mx-2">·</span><strong>Total rows:</strong> {{ $batch->total_rows }} <span class="mx-2">·</span><strong>Valid:</strong> {{ $batch->valid_rows }} <span class="mx-2">·</span><strong>Invalid:</strong> {{ $batch->invalid_rows }} <span class="mx-2">·</span><strong>Already exists:</strong> {{ $batch->existing_rows }}</p>
@if(session('provisioning_results'))<div class="alert alert-info" role="status">Invitation results: {{ session('provisioning_results')['sent'] }} sent, {{ session('provisioning_results')['failed'] }} delivery failures, {{ session('provisioning_results')['skipped'] }} skipped.</div>@endif
@if($batch->report_path)<a class="btn btn-sm btn-outline-secondary mb-3" href="{{ route('registrar.imports.report', $batch) }}">Download validation issues</a>@endif
@if($batch->status === 'previewed')<div class="alert alert-warning">Confirming creates only valid rows that do not already exist. Existing records and invalid rows are skipped; no portal accounts are created.</div><form method="POST" action="{{ route('registrar.imports.commit', $batch) }}">@csrf<label class="d-block"><input type="checkbox" name="confirm" value="1" required> I reviewed the preview and want to import these records.</label><button class="btn btn-primary mt-2">Confirm Import</button></form><form method="POST" action="{{ route('registrar.imports.cancel', $batch) }}" class="mt-2">@csrf<button class="btn btn-outline-secondary">Cancel Preview</button></form>@else<p><strong>Created:</strong> {{ $batch->created_rows }} · <strong>Skipped:</strong> {{ $batch->skipped_rows }} · <strong>Failed:</strong> {{ $batch->failed_rows }}</p>@endif
</div></div>
@if($rows)<div class="card"><div class="card-header font-weight-bold">Preview (first 25 rows)</div><div class="table-responsive"><table class="rg-record-table dtc-mobile-records table table-sm table-hover mb-0"><thead><tr><th>CSV row</th><th>Student No.</th><th>Name</th><th>Outcome</th><th>Validation</th></tr></thead><tbody>@foreach(array_slice($rows, 0, 25) as $row)<tr><td data-reg-label="CSV row" data-mobile-label="CSV row">{{ $row['line'] }}</td><td data-reg-label="Student No." data-mobile-label="Student No.">{{ $row['data']['student_number'] ?: '—' }}</td><td data-reg-label="Name" data-mobile-label="Name">{{ trim(($row['data']['first_name'] ?: '').' '.($row['data']['last_name'] ?: '')) }}</td><td data-reg-label="Outcome" data-mobile-label="Outcome">@if(!$row['valid'])<span class="badge badge-danger">Invalid</span>@elseif($row['existing'])<span class="badge badge-secondary">Existing · skip</span>@else<span class="badge badge-success">Create</span>@endif</td><td data-reg-label="Validation" data-mobile-label="Validation">{{ implode(' ', $row['errors']) }}</td></tr>@endforeach</tbody></table></div>@if(count($rows) > 25)<div class="card-footer text-muted">Showing 25 of {{ count($rows) }}. Download the issues report for row-level validation errors.</div>@endif</div>@endif
@if($importedProfiles->isNotEmpty())
<div class="card"><div class="card-header font-weight-bold">Portal account invitations</div><div class="card-body">
<p class="text-muted">Academic records were imported without accounts. Select up to 100 eligible records for a separate invitation review. Recipients verify their email when they activate; records with missing or ambiguous contact identities are not selectable.</p>
<form method="POST" action="{{ route('registrar.imports.provision-selected', $batch) }}" onsubmit="this.querySelector('button[type=submit]').disabled = true;">@csrf
<div class="table-responsive"><table class="rg-record-table dtc-mobile-records table table-sm table-hover"><thead><tr><th scope="col">Select</th><th scope="col">Student No.</th><th scope="col">Name</th><th scope="col">Contact</th><th scope="col">Portal status</th></tr></thead><tbody>
@foreach($importedProfiles as $profile)
    @php
        $duplicateEmail = $profile->email && $duplicateEmails->has(mb_strtolower($profile->email));
        $emailConflict = $profile->email && $accountEmails->has(mb_strtolower($profile->email)) && ! $profile->user;
        $staleSending = $profile->portalInvitation && $profile->portalInvitation->status === 'sending'
            && (! $profile->portalInvitation->updated_at || $profile->portalInvitation->updated_at->lte(
                now()->subMinutes(max(1, (int) config('portal_accounts.sending_lease_minutes', 15)))
            ));
        $retryInvitation = $profile->portalInvitation
            && (in_array($profile->portalInvitation->status, ['delivery_failed', 'expired', 'revoked'], true) || $staleSending)
            && $profile->user && $profile->user->status === 'pending';
        $eligibleForInvitation = filter_var($profile->email, FILTER_VALIDATE_EMAIL) && $profile->student_number && $profile->first_name && $profile->last_name && in_array($profile->academic_status, ['active', 'alumni'], true) && ! $duplicateEmail && ! $emailConflict && (! $profile->user || $retryInvitation);
    @endphp
    <tr>
        <td data-reg-label="Select" data-mobile-label="Select">@if($eligibleForInvitation)<input type="checkbox" name="student_profile_ids[]" value="{{ $profile->id }}" aria-label="Select {{ $profile->student_number }}">@else<span class="text-muted">—</span>@endif</td>
        <td data-reg-label="Student No." data-mobile-label="Student No."><a href="{{ route('registrar.students.show', $profile) }}">{{ $profile->student_number }}</a></td>
        <td data-reg-label="Name" data-mobile-label="Name">{{ trim(implode(' ', array_filter([$profile->first_name, $profile->middle_name, $profile->last_name, $profile->suffix]))) }}</td>
        <td data-reg-label="Contact" data-mobile-label="Contact">{{ $profile->email ?: 'Missing email' }} @if($eligibleForInvitation)<small class="d-block text-muted">Email verified on activation</small>@elseif($duplicateEmail)<small class="d-block text-danger">Ambiguous email</small>@elseif($emailConflict)<small class="d-block text-danger">Email belongs to an existing account</small>@elseif(!$profile->email)<small class="d-block text-danger">Missing email</small>@endif</td>
        <td data-reg-label="Portal status" data-mobile-label="Portal status">{{ $profile->user ? ($profile->portalInvitation->status ?? ($profile->user->email_verified_at ? $profile->user->status : 'unverified account')) : 'No portal account' }}</td>
    </tr>
@endforeach
</tbody></table></div>
<div class="custom-control custom-checkbox mb-3"><input class="custom-control-input" type="checkbox" id="confirm-bulk-provision" name="confirm" value="1" required><label class="custom-control-label" for="confirm-bulk-provision">I reviewed the selected identities and authorize sending setup invitations.</label></div>
@error('student_profile_ids')<div class="text-danger mb-2">{{ $message }}</div>@enderror @error('confirm')<div class="text-danger mb-2">{{ $message }}</div>@enderror
<button class="btn btn-primary">Review and send selected invitations</button>
</form></div></div>
@endif

</div>
@endsection
