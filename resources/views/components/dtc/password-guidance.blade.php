<link rel="stylesheet" href="{{ asset('css/password-guidance.css') }}">
<span id="dtc-password-policy" hidden data-minimum="{{ \App\Support\SecuritySettings::passwordMinimum() }}" data-symbols="{{ \App\Support\SecuritySettings::requiresSymbols() ? '1' : '0' }}"></span>
<script src="{{ asset('js/password-guidance.js') }}" defer></script>
