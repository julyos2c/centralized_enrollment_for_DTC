@props(['type' => 'submit', 'label' => 'Save', 'busy' => 'Processing…'])
<button {{ $attributes->merge(['type' => $type, 'class' => 'dtc-btn dtc-btn-primary dtc-loading-button']) }} data-loading-text="{{ $busy }}">{{ $label }}</button>
