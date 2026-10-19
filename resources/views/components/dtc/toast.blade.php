{{--
    DTC Nexus Toast Container
    ─────────────────────────────────────────────────────────────────────────
    Renders the fixed toast stack and automatically fires any session flash
    messages as DTC toasts via inline JS.

    Supported flash keys (set via ->with('key', 'message') in controllers):
        success | error | warning | info | status

    Usage: include once in your page layout, just before </body>.
    JS API (after this component):
        window.dtcToast.show({ type, message, duration? })
        window.dtcToast.success('Done!')
        window.dtcToast.error('Something went wrong.')
        window.dtcToast.warning('Please check the form.')
        window.dtcToast.info('Your request is under review.')
--}}

{{-- ── Container (injected once; JS will create it if missing too) ──────── --}}
<div id="dtc-toast-container"
     role="region"
     aria-live="polite"
     aria-label="Notifications"
     aria-atomic="false">
</div>

{{-- ── Flash → Toast bridge ────────────────────────────────────────────── --}}
@php
    $flashMap = [
        'success' => ['type' => 'success', 'msg' => session('success')],
        'error'   => ['type' => 'error',   'msg' => session('error')],
        'warning' => ['type' => 'warning', 'msg' => session('warning')],
        'info'    => ['type' => 'info',    'msg' => session('info')],
        // Laravel's built-in 'status' key (used by password reset etc.)
        'status'  => ['type' => 'success', 'msg' => session('status')],
    ];
    $flashes = array_filter($flashMap, fn($f) => !empty($f['msg']));
@endphp

@if(count($flashes))
<script>
document.addEventListener('DOMContentLoaded', function () {
    @foreach($flashes as $flash)
    window.dtcToast && window.dtcToast.show({
        type   : '{{ $flash['type'] }}',
        message: {!! json_encode($flash['msg']) !!},
    });
    @endforeach
});
</script>
@endif
