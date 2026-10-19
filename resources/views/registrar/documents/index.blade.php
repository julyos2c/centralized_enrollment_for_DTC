@extends('adminlte::page')
@include('partials.navbar')

@section('title', 'Document Requests')

@section('content_header')
    <div>
        <h4 class="mb-0 font-weight-bold u-text" >Document Requests</h4>
        <p class="mb-0 u-text-secondary-sm" >
            Manage alumni requests. Admin-managed rates are saved with each request; payment verification is handled by the Cashier.
        </p>
    </div>
@endsection

@section('content')

@if (session('success'))
    <div class="alert alert-success" role="status">{{ session('success') }}</div>
@endif
@if ($errors->any())
    <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
@endif

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span class="font-weight-bold u-text" >All Document Requests</span>
        <span  class="u-text-secondary-sm">{{ $requests->total() }} total</span>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>Alumni</th>
                        <th>Document</th>
                        <th>Copies</th>
                        <th>Fee</th>
                        <th>Payment</th>
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
                            <div  class="u-flex-center-gap-10">
                                <div style="width:32px; height:32px; border-radius:50%;
                                            background:linear-gradient(135deg,#7C3AED,#A78BFA);
                                            display:flex; align-items:center; justify-content:center;
                                            color:#fff; font-weight:700; font-size:12px; flex-shrink:0;">
                                    {{ strtoupper(substr($req->user->name, 0, 1)) }}
                                </div>
                                <div>
                                    <div  class="u-text-sm-bold-primary">
                                        {{ $req->user->name }}
                                    </div>
                                    <div  class="u-text-xs-secondary">
                                        {{ $req->user->email }}
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="dtc-status-badge is-info" style="font-weight:600; white-space:nowrap; display:inline-block;">
                                {{ $req->document_label }}
                            </span>
                        </td>
                        <td  class="u-text-sm-bold-primary">
                            {{ $req->copies }}
                        </td>
                        <td style="font-size:13px; font-weight:600; color:var(--dtc-success);">
                            ₱{{ number_format($req->fee, 2) }}
                        </td>
                        <td>
                            @php $paymentTone = ['unpaid' => 'warning', 'pending_verification' => 'info', 'verified' => 'success', 'rejected' => 'danger'][$req->payment_status] ?? 'neutral'; @endphp
                            <span class="dtc-status-badge is-{{ $paymentTone }}" style="white-space:nowrap;">
                                {{ ['unpaid' => 'Unpaid', 'pending_verification' => 'Pending verification', 'verified' => 'Verified', 'rejected' => 'Rejected'][$req->payment_status] ?? ucfirst($req->payment_status) }}
                            </span>
                        </td>
                        <td style="font-size:12px; color:var(--dtc-text-secondary); max-width:150px;">
                            {{ $req->purpose ?? '—' }}
                        </td>
                        <td>
                            @php $docToneReg = $req->status === 'released' ? 'success' : ($req->status === 'ready' ? 'purple' : ($req->status === 'processing' ? 'info' : 'warning')); @endphp
                            <span class="dtc-status-badge is-{{ $docToneReg }}" style="font-weight:700;">
                                {{ ucfirst($req->status) }}
                            </span>
                        </td>
                        <td  class="u-text-xxs-secondary">
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
                                <div class="modal-dialog modal-dialog-centered" role="document">
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
                                                <p style="font-size:13px; color:var(--dtc-text-secondary); margin-bottom:16px;">
                                                    <strong>{{ $req->user->name }}</strong> —
                                                    {{ $req->document_label }}
                                                    ({{ $req->copies }} cop{{ $req->copies > 1 ? 'ies' : 'y' }})
                                                </p>
                                                <div class="form-group">
                                                    <label>Status</label>
                                                    <select name="status" class="form-control" required>
                                                        @foreach(['submitted','processing','ready','released'] as $s)
                                                            <option value="{{ $s }}"
                                                                {{ $req->status === $s ? 'selected' : '' }}
                                                                {{ $s === 'released' && (! $req->is_paid || $req->payment_status !== 'verified') ? 'disabled' : '' }}>
                                                                {{ ucfirst($s) }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="form-group">
                                                    <label>Remarks <span style="color:var(--dtc-text-muted); font-weight:400;">(optional)</span></label>
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
                        <td colspan="9" class="text-center py-5" style="color:var(--dtc-text-muted); font-size:13px;">
                            No document requests yet.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card-footer d-flex justify-content-between align-items-center flex-wrap u-gap-15" >
        <small  class="u-text-secondary">
            Showing
            <strong>{{ $requests->firstItem() ?? 0 }}</strong>
            to
            <strong>{{ $requests->lastItem() ?? 0 }}</strong>
            of
            <strong>{{ $requests->total() }}</strong>
            requests
        </small>

        @if($requests->hasPages())
        <div>
            {{ $requests->onEachSide(1)->links('pagination::bootstrap-4') }}
        </div>
        @endif
    </div>
</div>

@endsection
