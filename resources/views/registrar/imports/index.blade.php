@extends('adminlte::page')
@include('partials.navbar')
@section('title', 'Academic Import Center')
@section('content_header')<h4 class="font-weight-bold mb-1">Academic Import Center</h4><p class="text-muted">Import student and alumni records from CSV. Private uploads expire after 24 hours.</p>@endsection
@push('css')
<link rel="stylesheet" href="{{ asset('css/dtc-filters.css') }}">
<link rel="stylesheet" href="{{ asset('css/registrar-workspace.css') }}">
@endpush
@section('content')
<div class="registrar-workspace rg-import-center">

<div class="row"><div class="col-lg-5"><div class="card"><div class="card-header font-weight-bold">Start an Import</div><div class="card-body"><p class="rg-import-help">Upload a CSV with up to 1,000 rows (5 MB maximum).</p><form method="POST" action="{{ route('registrar.imports.upload') }}" enctype="multipart/form-data">@csrf<div class="form-group"><label for="import_type">Record type</label><select required id="import_type" name="import_type" class="form-control"><option value="">Choose type</option><option value="students">Current Students</option><option value="alumni">Alumni / Historical Graduates</option></select></div><div class="mb-3 rg-template-links"><span>Download a template</span><a href="{{ route('registrar.imports.template', 'students') }}">Student CSV</a><a href="{{ route('registrar.imports.template', 'alumni') }}">Alumni CSV</a></div><div class="form-group"><label for="file">CSV file</label><input type="file" id="file" name="file" class="form-control-file" accept=".csv,text/csv" required>@error('file')<div class="text-danger">{{ $message }}</div>@enderror</div><button type="submit" class="btn btn-primary rg-import-upload">Upload and Preview</button></form></div></div></div>
<div class="col-lg-7">
<div class="dtc-fl rg-import-history-controls">
    <div class="rg-import-history-heading">
        <h5>Recent imports</h5>
        <button type="button" class="dtc-fl-toggle" data-toggle="collapse" data-target="#importHistoryFilters" aria-expanded="false" aria-controls="importHistoryFilters">
            <i data-lucide="sliders-horizontal" aria-hidden="true"></i><span>Filters</span>
        </button>
    </div>
    <div class="collapse" id="importHistoryFilters">
        <div class="dtc-fl-panel">@include('partials.table-rows', ['tableRecords' => $batches, 'tableId' => 'registrar-table-1', 'fixedRows' => true])</div>
    </div>
</div>
<div class="card"><div class="card-header font-weight-bold">Import History</div><div class="table-responsive">
<table id="registrar-table-1" class="rg-record-table dtc-mobile-records table table-hover mb-0"><thead><tr><th>Reference</th><th>Type</th><th>Uploaded by</th><th>Status</th><th>Created / skipped</th><th></th></tr></thead><tbody>@forelse($batches as $batch)<tr><td data-reg-label="Reference" data-mobile-label="Reference">{{ $batch->reference }}</td><td data-reg-label="Type" data-mobile-label="Type">{{ ucfirst($batch->import_type) }}</td><td data-reg-label="Uploaded by" data-mobile-label="Uploaded by">{{ $batch->importer->name ?? 'Staff account removed' }}</td><td data-reg-label="Status" data-mobile-label="Status">{{ ucfirst($batch->status) }}</td><td data-reg-label="Created / skipped" data-mobile-label="Created / skipped">{{ $batch->created_rows }} / {{ $batch->skipped_rows }}</td><td data-reg-label="Actions" data-mobile-label="Actions"><a class="btn btn-sm btn-outline-primary" href="{{ route('registrar.imports.show', $batch) }}">View</a></td></tr>@empty<tr><td colspan="6" class="text-center text-muted">No imports yet.</td></tr>@endforelse</tbody></table></div><div class="card-footer">{{ $batches->links('pagination::bootstrap-4') }}</div></div></div></div>

</div>
@endsection
