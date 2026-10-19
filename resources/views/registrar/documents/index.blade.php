@extends('adminlte::page')
@include('partials.navbar')

@section('title', 'Document Requests')

@section('content_header')
    <div>
        <h4 class="mb-0 font-weight-bold" style="color:#1E293B;">Document Requests</h4>
        <p class="mb-0" style="color:#64748B; font-size:13px;">
            Manage alumni document requests
        </p>
    </div>
@endsection

@section('content')

@if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

<div class="card">
    <div class="card-body p-0">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>Alumni</th>
                    <th>Document</th>
                    <th>Copies</th>
                    <th>Fee</th>
                    <th>Purpose</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($requests as $req)
                <tr>
                    <td>
                        <div style="display:flex; align-items:center; gap:10px;">
                            <div style="width:32px; height:32px; border-radius:50%;
                                        background:linear-gradient(135deg,#7C3AED,#A78BFA);
                                        display:flex; align-items:center; justify-content:center;
                                        color:#fff; font-weight:700; font-size:12px; flex-shrink:0;">
                                {{ strtoupper(substr($req->user->name, 0, 1)) }}
                            </div>
                            <div>
                                <div style="font-size:13px; font-weight:600; color:#1E293B;">
                                    {{ $req->user->name }}
                                </div>
                                <div style="font-size:11px; color:#64748B;">
                                    {{ $req->user->email }}
                                </div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <span style="background:#EEF2FF; color:#0F4CDB; padding:4px 10px;
                                     border-radius:20px; font-size:12px; font-weight:600;">
                            {{ $req->document_label }}
                        </span>
                    </td>
                    <td style="font-size:13px; font-weight:600; color:#1E293B;">
                        {{ $req->copies }}
                    </td>
                    <td style="font-size:13px; font-weight:600; color:#15803D;">
                        ₱{{ number_format($req->fee, 2) }}
                    </td>
                    <td style="font-size:12px; color:#64748B; max-width:150px;">
                        {{ $req->purpose ?? '—' }}
                    </td>
                    <td>
                        <span style="font-size:11px; font-weight:700; padding:4px 10px;
                                     border-radius:20px;
                                     background:{{ $req->status === 'released'   ? '#DCFCE7' :
                                                   ($req->status === 'ready'      ? '#EDE9FE' :
                                                   ($req->status === 'processing' ? '#DBEAFE' : '#FEF9C3')) }};
                                     color:{{ $req->status_color }};">
                            {{ ucfirst($req->status) }}
                        </span>
                    </td>
                    <td style="font-size:12px; color:#64748B;">
                        {{ $req->created_at->format('M d, Y') }}
                    </td>
                    <td>
                        <button type="button" class="btn btn-sm btn-primary"
                                data-toggle="modal"
                                data-target="#statusModal{{ $req->id }}">
                            Update
                        </button>

                        {{-- Status Modal --}}
                        <div class="modal fade" id="statusModal{{ $req->id }}" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <form method="POST"
                                          action="{{ route('registrar.documents.status', $req) }}">
                                        @csrf
                                        <div class="modal-header">
                                            <h5 class="modal-title">Update Request Status</h5>
                                            <button type="button" class="close"
                                                    data-dismiss="modal">&times;</button>
                                        </div>
                                        <div class="modal-body">
                                            <p style="font-size:13px; color:#64748B; margin-bottom:16px;">
                                                <strong>{{ $req->user->name }}</strong> —
                                                {{ $req->document_label }}
                                                ({{ $req->copies }} cop{{ $req->copies > 1 ? 'ies' : 'y' }})
                                            </p>
                                            <div class="form-group">
                                                <label>Status</label>
                                                <select name="status" class="form-control" required>
                                                    @foreach(['submitted','processing','ready','released'] as $s)
                                                        <option value="{{ $s }}"
                                                            {{ $req->status === $s ? 'selected' : '' }}>
                                                            {{ ucfirst($s) }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="form-group">
                                                <label>Remarks <span style="color:#94A3B8; font-weight:400;">(optional)</span></label>
                                                <textarea name="remarks" class="form-control" rows="3"
                                                          placeholder="Any notes for the alumni...">{{ $req->remarks }}</textarea>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary"
                                                    data-dismiss="modal">Cancel</button>
                                            <button type="submit" class="btn btn-primary">
                                                Update Status
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="text-center py-5" style="color:#94A3B8; font-size:13px;">
                        No document requests yet.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection