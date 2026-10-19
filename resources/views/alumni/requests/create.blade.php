@extends('adminlte::page')
@include('partials.navbar')

@section('title', 'Request Document')

@section('content_header')
    <div>
        <h4 class="mb-0 font-weight-bold" style="color:#1E293B;">Request a Document</h4>
        <p class="mb-0" style="color:#64748B; font-size:13px;">
            Submit a document request to the Registrar's Office
        </p>
    </div>
@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header font-weight-bold" style="color:#1E293B;">
                <i class="fas fa-folder-plus mr-2" style="color:#0F4CDB;"></i>
                Document Request Form
            </div>
            <div class="card-body">

                @if ($errors->any())
                    <div class="alert alert-danger">{{ $errors->first() }}</div>
                @endif

                <form method="POST" action="{{ route('portal.documents.store') }}">
                    @csrf

                    {{-- Document Type --}}
                    <div class="form-group">
                        <label>Document Type</label>
                        <div class="row">
                            @foreach([
                                ['value' => 'tor',          'label' => 'Transcript of Records', 'icon' => 'fa-file-alt',       'fee' => '₱50/copy'],
                                ['value' => 'diploma',      'label' => 'Diploma',               'icon' => 'fa-graduation-cap', 'fee' => '₱100/copy'],
                                ['value' => 'certification','label' => 'Certification',          'icon' => 'fa-certificate',    'fee' => '₱30/copy'],
                                ['value' => 'true_copy',    'label' => 'True Copy of Records',  'icon' => 'fa-copy',           'fee' => '₱50/copy'],
                            ] as $doc)
                            <div class="col-6 mb-2">
                                <label style="cursor:pointer; width:100%;">
                                    <input type="radio" name="document_type"
                                           value="{{ $doc['value'] }}"
                                           style="display:none;"
                                           class="doc-radio"
                                           {{ old('document_type') === $doc['value'] ? 'checked' : '' }}>
                                    <div class="doc-option"
                                         style="border:2px solid #E2E8F0; border-radius:12px;
                                                padding:14px; text-align:center; transition:all 0.2s;
                                                background:#F8FAFC;">
                                        <i class="fas {{ $doc['icon'] }}"
                                           style="font-size:22px; color:#94A3B8; margin-bottom:8px; display:block;"></i>
                                        <div style="font-size:12px; font-weight:600; color:#64748B;">
                                            {{ $doc['label'] }}
                                        </div>
                                        <div style="font-size:11px; color:#94A3B8; margin-top:4px;">
                                            {{ $doc['fee'] }}
                                        </div>
                                    </div>
                                </label>
                            </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- Number of Copies --}}
                    <div class="form-group">
                        <label>Number of Copies</label>
                        <div style="display:flex; align-items:center; gap:12px;">
                            <button type="button" onclick="adjustCopies(-1)"
                                    style="width:36px; height:36px; border-radius:10px;
                                           background:#F1F5F9; border:1.5px solid #E2E8F0;
                                           font-size:18px; cursor:pointer; display:flex;
                                           align-items:center; justify-content:center;">−</button>
                            <input type="number" name="copies" id="copies"
                                   class="form-control" value="{{ old('copies', 1) }}"
                                   min="1" max="10" required
                                   style="width:80px; text-align:center;">
                            <button type="button" onclick="adjustCopies(1)"
                                    style="width:36px; height:36px; border-radius:10px;
                                           background:#F1F5F9; border:1.5px solid #E2E8F0;
                                           font-size:18px; cursor:pointer; display:flex;
                                           align-items:center; justify-content:center;">+</button>
                        </div>
                    </div>

                    {{-- Purpose --}}
                    <div class="form-group">
                        <label>Purpose <span style="color:#94A3B8; font-weight:400;">(optional)</span></label>
                        <textarea name="purpose" class="form-control" rows="3"
                                  placeholder="e.g. For employment, board exam, scholarship...">{{ old('purpose') }}</textarea>
                    </div>

                    {{-- Appointment Slot (optional) --}}
                    <div class="form-group">
                        <label>
                            Schedule Pickup Appointment
                            <span style="color:#94A3B8; font-weight:400;">(optional)</span>
                        </label>
                        <select name="appointment_slot_id" class="form-control">
                            <option value="">— I will schedule later —</option>
                            @foreach ($slots as $slot)
                                <option value="{{ $slot->id }}"
                                    {{ old('appointment_slot_id') == $slot->id ? 'selected' : '' }}>
                                    {{ \Carbon\Carbon::parse($slot->date)->format('M d, Y') }}
                                    {{ \Carbon\Carbon::parse($slot->start_time)->format('h:i A') }} —
                                    {{ \Carbon\Carbon::parse($slot->end_time)->format('h:i A') }}
                                    ({{ $slot->availableSlots() }} slot/s left)
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Fee Summary --}}
                    <div style="background:#EEF2FF; border-radius:12px; padding:16px;
                                margin-bottom:20px; border:1.5px solid #C7D2FE;">
                        <div style="font-size:12px; font-weight:600; color:#0F4CDB;
                                    text-transform:uppercase; letter-spacing:0.5px; margin-bottom:8px;">
                            Fee Summary
                        </div>
                        <div style="font-size:13px; color:#64748B;">
                            Select a document type to see the estimated fee.
                        </div>
                        <div id="fee-summary" style="display:none; margin-top:8px;">
                            <div style="display:flex; justify-content:space-between; font-size:13px;">
                                <span style="color:#64748B;">Document Type:</span>
                                <span id="fee-doc-label" style="font-weight:600; color:#1E293B;"></span>
                            </div>
                            <div style="display:flex; justify-content:space-between; font-size:13px; margin-top:4px;">
                                <span style="color:#64748B;">Copies:</span>
                                <span id="fee-copies-label" style="font-weight:600; color:#1E293B;"></span>
                            </div>
                            <div style="border-top:1px solid #C7D2FE; margin:8px 0;"></div>
                            <div style="display:flex; justify-content:space-between;">
                                <span style="font-size:13px; color:#64748B;">Estimated Total:</span>
                                <span id="fee-total" style="font-size:16px; font-weight:800; color:#0F4CDB;"></span>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="fas fa-paper-plane mr-1"></i> Submit Request
                    </button>
                    <a href="{{ route('portal.documents.index') }}" class="btn btn-secondary btn-block mt-2">
                        Cancel
                    </a>
                </form>
            </div>
        </div>
    </div>

    {{-- Info Panel --}}
    <div class="col-lg-5">
        <div class="card"
             style="background:linear-gradient(135deg,#0F4CDB,#1a5feb); border:none; color:#fff;">
            <div class="card-body p-4">
                <h5 style="font-weight:700; margin-bottom:16px; color:#fff;">
                    <i class="fas fa-info-circle mr-2"></i> How It Works
                </h5>
                <div style="font-size:13px; line-height:1.8; opacity:0.9;">
                    <div style="display:flex; gap:12px; margin-bottom:14px; align-items:flex-start;">
                        <div style="background:rgba(255,199,44,0.2); border-radius:50%; width:28px;
                                    height:28px; display:flex; align-items:center; justify-content:center;
                                    flex-shrink:0; font-weight:700; color:#FFC72C; font-size:12px;">1</div>
                        <div>Submit your document request with the required details.</div>
                    </div>
                    <div style="display:flex; gap:12px; margin-bottom:14px; align-items:flex-start;">
                        <div style="background:rgba(255,199,44,0.2); border-radius:50%; width:28px;
                                    height:28px; display:flex; align-items:center; justify-content:center;
                                    flex-shrink:0; font-weight:700; color:#FFC72C; font-size:12px;">2</div>
                        <div>The Registrar will review and process your request.</div>
                    </div>
                    <div style="display:flex; gap:12px; margin-bottom:14px; align-items:flex-start;">
                        <div style="background:rgba(255,199,44,0.2); border-radius:50%; width:28px;
                                    height:28px; display:flex; align-items:center; justify-content:center;
                                    flex-shrink:0; font-weight:700; color:#FFC72C; font-size:12px;">3</div>
                        <div>You'll be notified when your document is ready for pickup.</div>
                    </div>
                    <div style="display:flex; gap:12px; align-items:flex-start;">
                        <div style="background:rgba(255,199,44,0.2); border-radius:50%; width:28px;
                                    height:28px; display:flex; align-items:center; justify-content:center;
                                    flex-shrink:0; font-weight:700; color:#FFC72C; font-size:12px;">4</div>
                        <div>Pay the fee at the Cashier's Office and claim your document.</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Fee Reference --}}
        <div class="card mt-3">
            <div class="card-header font-weight-bold" style="color:#1E293B;">
                Fee Reference
            </div>
            <div class="card-body p-0">
                @foreach([
                    ['label' => 'Transcript of Records', 'fee' => '₱50.00 per copy'],
                    ['label' => 'Diploma',               'fee' => '₱100.00 per copy'],
                    ['label' => 'Certification',          'fee' => '₱30.00 per copy'],
                    ['label' => 'True Copy of Records',  'fee' => '₱50.00 per copy'],
                ] as $item)
                <div style="padding:12px 20px; border-bottom:1px solid #F1F5F9;
                            display:flex; justify-content:space-between; align-items:center;">
                    <span style="font-size:13px; color:#1E293B;">{{ $item['label'] }}</span>
                    <span style="font-size:13px; font-weight:700; color:#0F4CDB;">{{ $item['fee'] }}</span>
                </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
@endsection

@section('js')
<script>
const fees = { tor: 50, diploma: 100, certification: 30, true_copy: 50 };
const labels = {
    tor: 'Transcript of Records',
    diploma: 'Diploma',
    certification: 'Certification',
    true_copy: 'True Copy of Records'
};

function updateFee() {
    const selected = document.querySelector('.doc-radio:checked');
    const copies = parseInt(document.getElementById('copies').value) || 1;

    if (selected) {
        const type = selected.value;
        const total = fees[type] * copies;
        document.getElementById('fee-doc-label').textContent = labels[type];
        document.getElementById('fee-copies-label').textContent = copies;
        document.getElementById('fee-total').textContent = '₱' + total.toFixed(2);
        document.getElementById('fee-summary').style.display = 'block';
    }
}

// Document type selection
document.querySelectorAll('.doc-radio').forEach(radio => {
    radio.addEventListener('change', function () {
        document.querySelectorAll('.doc-option').forEach(opt => {
            opt.style.borderColor = '#E2E8F0';
            opt.style.background = '#F8FAFC';
            opt.querySelector('i').style.color = '#94A3B8';
        });
        const selected = this.nextElementSibling;
        selected.style.borderColor = '#0F4CDB';
        selected.style.background = '#EEF2FF';
        selected.querySelector('i').style.color = '#0F4CDB';
        updateFee();
    });
    if (this.checked) this.dispatchEvent(new Event('change'));
});

// Copies input
document.getElementById('copies').addEventListener('input', updateFee);

function adjustCopies(delta) {
    const input = document.getElementById('copies');
    const val = Math.min(10, Math.max(1, parseInt(input.value || 1) + delta));
    input.value = val;
    updateFee();
}
</script>
@endsection