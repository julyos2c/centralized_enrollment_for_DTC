<section class="card mb-3 dtc-welcome-card" aria-label="Your account">
    <div class="card-body">
        <div class="dtc-welcome-layout">
            @include('portal.partials.dashboard-avatar', ['avatarRoutePrefix' => 'profile'])
            <div class="dtc-welcome-copy">
                <h2 class="dtc-welcome-title"><span class="dtc-welcome-greeting">Hello,</span>{{ auth()->user()->name }}</h2>
                <p class="dtc-welcome-meta">{{ $staffRoleLabel }}</p>
                <p class="dtc-welcome-text">{{ $staffWelcomeMessage }}</p>
            </div>
        </div>
    </div>
</section>

@include('portal.partials.dashboard-avatar-modals')
@push('css')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.css">
<link rel="stylesheet" href="{{ asset_v('css/profile.css') }}">
@endpush
@push('js')
<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.js"></script>
<script src="{{ asset_v('js/dashboard-avatar.js') }}"></script>
@endpush
