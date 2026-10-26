<link rel="manifest" href="{{ url('manifest.webmanifest') }}">
<meta name="theme-color" content="#0f172a">
<link rel="apple-touch-icon" sizes="180x180" href="{{ asset('pwa/icon-180.png') }}">
<script src="{{ asset_v('js/pwa.js') }}" data-worker="{{ url('sw.js') }}" defer></script>
