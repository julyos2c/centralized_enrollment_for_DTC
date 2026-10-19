<div class="dtc-welcome-avatar-wrap">
    <a href="{{ route('portal.profile.show') }}"
       class="dtc-welcome-avatar-link"
       aria-label="Open profile settings"
       title="Profile settings">
        @if(auth()->user()->avatar)
            <img src="{{ Storage::url(auth()->user()->avatar) }}"
                 alt=""
                 aria-hidden="true"
                 class="dtc-welcome-avatar-image">
        @else
            <div aria-hidden="true" class="dtc-welcome-avatar-initials">
                {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
            </div>
        @endif
    </a>
    <button type="button"
            class="dtc-avatar-camera-badge"
            data-avatar-picker-trigger
            aria-label="Choose a new profile photo"
            title="Change profile photo">
        <i data-lucide="camera" style="width:15px;height:15px;"></i>
    </button>
</div>

<form method="POST"
      action="{{ route('portal.profile.update') }}"
      enctype="multipart/form-data"
      id="dashboardAvatarForm"
      class="d-none"
      data-dashboard-avatar-form>
    @csrf
    <input type="hidden" name="return_to_dashboard" value="1">
    <input type="hidden" name="name" value="{{ auth()->user()->name }}">
    <input type="hidden" name="email" value="{{ auth()->user()->email }}">
    <input type="file"
           id="dashboardAvatarInput"
           name="avatar"
           accept="image/jpeg,image/png,image/webp"
           data-dashboard-avatar-input>
</form>
