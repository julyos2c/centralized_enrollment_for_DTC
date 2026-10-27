<div class="dr-gcash-transfer">
    <div class="dr-gcash-transfer-row">
        <div><span>GCash account</span><strong>{{ $gcashName ?: 'See official QR' }}</strong><span class="dr-gcash-number">{{ $gcashNumber ?: 'See official QR' }}</span></div>
        @if($gcashNumber)<button type="button" class="dr-gcash-copy" data-copy-value="{{ $gcashNumber }}" data-copy-label="GCash number" aria-label="Copy GCash account number">Copy number</button>@endif
    </div>
    <div class="dr-gcash-transfer-row">
        <div><span>Amount to pay</span><strong data-document-amount>{{ $gcashAmount === null ? 'Choose documents first' : '₱'.number_format($gcashAmount, 2) }}</strong></div>
        <button type="button" class="dr-gcash-copy" data-document-copy-amount data-copy-value="{{ $gcashAmount === null ? '' : number_format($gcashAmount, 2, '.', '') }}" data-copy-label="Amount" aria-label="Copy exact amount to pay" @disabled($gcashAmount === null)>Copy amount</button>
    </div>
</div>
