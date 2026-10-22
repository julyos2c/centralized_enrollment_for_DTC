@extends('adminlte::page')
@include('partials.navbar')

@section('title', 'Profile Settings')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-0 font-weight-bold u-text">Profile Settings</h4>
            <p class="mb-0 u-text-secondary-sm">Manage your account information and password</p>
        </div>
    </div>
@endsection

@section('content')

@php
    $profileRoutePrefix = $profileRoutePrefix ?? 'portal.profile';
@endphp

@if ($errors->any() && !$errors->has('current_password') && !$errors->has('password'))
<div class="alert dtc-alert-danger d-flex align-items-center gap-2 mb-4" role="alert">
    <i data-lucide="alert-circle" style="width:18px;height:18px;flex-shrink:0;"></i>
    <span>Please fix the errors below before saving.</span>
</div>
@endif

<div class="row">
<div class="col-12" style="max-width:820px;">

{{-- ══════════════════════════════════════════════════════════════════════ --}}
{{-- HERO CARD                                                              --}}
{{-- ══════════════════════════════════════════════════════════════════════ --}}
<div class="card mb-4 ps-hero-card">
    <div class="card-body d-flex align-items-center gap-3 flex-wrap">

        {{-- Avatar --}}
        <div class="ps-avatar-wrap">
            <x-dtc.user-avatar :user="$user" class="ps-avatar-initials" />
            <button type="button"
                    class="ps-avatar-edit-btn"
                    onclick="document.getElementById('avatarFileInput').click()"
                    title="Change photo"
                    aria-label="Change profile photo">
                <i data-lucide="camera" style="width:14px;height:14px;"></i>
            </button>
        </div>

        {{-- Identity --}}
        <div>
            <div class="ps-hero-name">{{ $user->name }}</div>
            <div class="ps-hero-email">{{ $user->email }}</div>
            <span class="ps-role-badge">
                <i data-lucide="shield" style="width:11px;height:11px;"></i>
                {{ $user->getRoleNames()->map(fn ($role) => ucfirst(str_replace('_', ' ', $role)))->implode(' / ') ?: 'User' }}
            </span>
        </div>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════════════════════ --}}
{{-- PERSONAL INFORMATION                                                   --}}
{{-- ══════════════════════════════════════════════════════════════════════ --}}
<div class="card mb-4">
    <div class="card-header ps-card-header">
        <div class="ps-card-icon ps-icon-blue">
            <i data-lucide="user" style="width:17px;height:17px;"></i>
        </div>
        <div>
            <div class="ps-card-title">Personal Information</div>
            <div class="ps-card-subtitle">Update your name, email and profile photo</div>
        </div>
    </div>
    <div class="card-body">
        <form method="POST"
              action="{{ route($profileRoutePrefix . '.update') }}"
              enctype="multipart/form-data"
              id="profileForm">
            @csrf

            {{-- Hidden file input --}}
            <input type="file"
                   id="avatarFileInput"
                   name="avatar"
                   accept="image/jpeg,image/png,image/webp"
                   style="display:none">

            <div class="row">
                <div class="col-12 col-sm-6 mb-3">
                    <label for="name" class="ps-label">
                        Full Name <span class="text-danger">*</span>
                    </label>
                    <input type="text"
                           id="name"
                           name="name"
                           class="form-control ps-input @error('name') is-invalid @enderror"
                           value="{{ old('name', $user->name) }}"
                           placeholder="Enter your full name"
                           autocomplete="name"
                           required>
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-12 col-sm-6 mb-3">
                    <label for="email" class="ps-label">
                        Email Address <span class="text-danger">*</span>
                    </label>
                    <input type="email"
                           id="email"
                           name="email"
                           class="form-control ps-input @error('email') is-invalid @enderror"
                           value="{{ old('email', $user->email) }}"
                           placeholder="you@example.com"
                           autocomplete="email"
                           required>
                    @error('email')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            {{-- Avatar preview strip --}}
            <div id="avatarPreviewRow" style="display:none;" class="align-items-center gap-3 mb-3">
                <img id="avatarPreview"
                     alt="Preview"
                     class="ps-avatar-preview">
                <div>
                    <div style="font-size:.82rem;font-weight:600;color:var(--dtc-text);">New photo selected</div>
                    <div style="font-size:.75rem;color:var(--dtc-text-secondary);">Save changes to apply.</div>
                </div>
                <button type="button"
                        id="clearAvatarBtn"
                        class="btn btn-sm btn-outline-secondary ml-auto">
                    <i data-lucide="x" style="width:13px;height:13px;"></i> Clear
                </button>
            </div>

            @error('avatar')
                <div class="text-danger small mb-3 d-flex align-items-center gap-1">
                    <i data-lucide="alert-circle" style="width:13px;height:13px;"></i>
                    {{ $message }}
                </div>
            @enderror

        </form>

        {{-- NOTE: this button row sits OUTSIDE #profileForm on purpose. Nesting a
             second <form> (Remove Photo) inside #profileForm is invalid HTML — the
             browser silently drops the inner <form> tag but keeps its hidden
             @method('DELETE') input as a child of the OUTER form, which made every
             profile-form submission (Save Changes, avatar change) go out as DELETE
             instead of POST and 405. The "form" attribute below re-associates the
             Save Changes button with #profileForm without nesting. --}}
        <div class="d-flex align-items-center flex-wrap gap-2 mt-2">
            <button type="submit" form="profileForm" class="btn btn-primary">
                <i data-lucide="save" class="mr-1" style="width:15px;height:15px;"></i>
                Save Changes
            </button>

            @if ($user->avatar)
                <form method="POST"
                      action="{{ route($profileRoutePrefix . '.avatar.remove') }}"
                      data-confirm-title="Remove Profile Photo"
                      data-confirm-message="This will permanently remove your profile photo."
                      data-confirm-ok="Remove"
                      class="m-0">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger btn-sm">
                        <i data-lucide="trash-2" class="mr-1" style="width:13px;height:13px;"></i>
                        Remove Photo
                    </button>
                </form>
            @endif
        </div>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════════════════════ --}}
{{-- ACADEMIC RECORD (read-only, shown only when studentProfile exists)    --}}
{{-- ══════════════════════════════════════════════════════════════════════ --}}
@if ($studentProfile)
<div class="card mb-4">
    <div class="card-header ps-card-header">
        <div class="ps-card-icon ps-icon-yellow">
            <i data-lucide="graduation-cap" style="width:17px;height:17px;"></i>
        </div>
        <div>
            <div class="ps-card-title">Academic Record</div>
            <div class="ps-card-subtitle">Managed by the Registrar — contact them for corrections</div>
        </div>
    </div>
    <div class="card-body p-0">

        <div class="ps-info-row">
            <div class="ps-info-icon"><i data-lucide="hash" style="width:14px;height:14px;"></i></div>
            <div>
                <div class="ps-info-label">Student Number</div>
                <div class="ps-info-value">{{ $studentProfile->student_number ?? '—' }}</div>
            </div>
        </div>

        @if ($studentProfile->program)
        <div class="ps-info-row">
            <div class="ps-info-icon"><i data-lucide="book-open" style="width:14px;height:14px;"></i></div>
            <div>
                <div class="ps-info-label">Program / Course</div>
                <div class="ps-info-value">{{ $studentProfile->program->name }}</div>
            </div>
        </div>
        @endif

        @if ($studentProfile->enrolled_ay)
        <div class="ps-info-row">
            <div class="ps-info-icon"><i data-lucide="calendar" style="width:14px;height:14px;"></i></div>
            <div>
                <div class="ps-info-label">Academic Year Enrolled</div>
                <div class="ps-info-value">{{ $studentProfile->enrolled_ay }}</div>
            </div>
        </div>
        @endif

        @if ($user->hasRole('alumni') && $studentProfile->graduation_year)
        <div class="ps-info-row">
            <div class="ps-info-icon"><i data-lucide="award" style="width:14px;height:14px;"></i></div>
            <div>
                <div class="ps-info-label">Graduation Year</div>
                <div class="ps-info-value">{{ $studentProfile->graduation_year }}</div>
            </div>
        </div>
        @endif

        @if ($studentProfile->admission_type)
        <div class="ps-info-row" style="border-bottom:none;">
            <div class="ps-info-icon"><i data-lucide="tag" style="width:14px;height:14px;"></i></div>
            <div>
                <div class="ps-info-label">Admission Type</div>
                <div class="ps-info-value">{{ ucfirst(str_replace('_', ' ', $studentProfile->admission_type)) }}</div>
            </div>
        </div>
        @endif

    </div>
</div>
@endif

{{-- ══════════════════════════════════════════════════════════════════════ --}}
{{-- CHANGE PASSWORD                                                        --}}
{{-- ══════════════════════════════════════════════════════════════════════ --}}
<div class="card mb-4">
    <div class="card-header ps-card-header">
        <div class="ps-card-icon ps-icon-slate">
            <i data-lucide="lock" style="width:17px;height:17px;"></i>
        </div>
        <div>
            <div class="ps-card-title">Change Password</div>
            <div class="ps-card-subtitle">Choose a strong password — at least 8 characters</div>
        </div>
    </div>
    <div class="card-body">

        @if ($errors->has('current_password') || $errors->has('password'))
        <div class="alert dtc-alert-danger d-flex align-items-center gap-2 mb-3" role="alert">
            <i data-lucide="alert-circle" style="width:17px;height:17px;flex-shrink:0;"></i>
            <span>{{ $errors->first('current_password') ?: $errors->first('password') }}</span>
        </div>
        @endif

        <form method="POST"
              action="{{ route($profileRoutePrefix . '.password') }}"
              id="passwordForm">
            @csrf

            <div class="mb-3">
                <label for="current_password" class="ps-label">
                    Current Password <span class="text-danger">*</span>
                </label>
                <div class="ps-pw-wrap">
                    <input type="password"
                           id="current_password"
                           name="current_password"
                           class="form-control ps-input ps-pw-input @error('current_password') is-invalid @enderror"
                           placeholder="Enter current password"
                           autocomplete="current-password"
                           required>
                    <button type="button"
                            class="ps-pw-toggle"
                            data-target="current_password"
                            aria-label="Toggle password visibility">
                        <i data-lucide="eye" style="width:16px;height:16px;"></i>
                    </button>
                </div>
            </div>

            <div class="row">
                <div class="col-12 col-sm-6 mb-3">
                    <label for="password" class="ps-label">
                        New Password <span class="text-danger">*</span>
                    </label>
                    <div class="ps-pw-wrap">
                        <input type="password"
                               id="password"
                               name="password"
                               class="form-control ps-input ps-pw-input @error('password') is-invalid @enderror"
                               placeholder="Min. 8 characters"
                               autocomplete="new-password"
                               required
                               minlength="8"
                               oninput="updateStrength(this.value)">
                        <button type="button"
                                class="ps-pw-toggle"
                                data-target="password"
                                aria-label="Toggle password visibility">
                            <i data-lucide="eye" style="width:16px;height:16px;"></i>
                        </button>
                    </div>
                    {{-- Strength bar --}}
                    <div class="ps-strength-bar mt-1">
                        <div class="ps-strength-fill" id="strengthFill"></div>
                    </div>
                    <div class="ps-strength-label" id="strengthLabel">Enter a new password</div>
                </div>

                <div class="col-12 col-sm-6 mb-3">
                    <label for="password_confirmation" class="ps-label">
                        Confirm New Password <span class="text-danger">*</span>
                    </label>
                    <div class="ps-pw-wrap">
                        <input type="password"
                               id="password_confirmation"
                               name="password_confirmation"
                               class="form-control ps-input ps-pw-input"
                               placeholder="Repeat new password"
                               autocomplete="new-password"
                               required
                               minlength="8">
                        <button type="button"
                                class="ps-pw-toggle"
                                data-target="password_confirmation"
                                aria-label="Toggle password visibility">
                            <i data-lucide="eye" style="width:16px;height:16px;"></i>
                        </button>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary">
                <i data-lucide="lock" class="mr-1" style="width:15px;height:15px;"></i>
                Update Password
            </button>
        </form>
    </div>
</div>

</div>{{-- /col --}}
</div>{{-- /row --}}

{{-- ══════════════════════════════════════════════════════════════════════ --}}
{{-- AVATAR CROP MODAL                                                      --}}
{{-- ══════════════════════════════════════════════════════════════════════ --}}
<div class="modal fade" id="avatarCropModal" tabindex="-1" role="dialog" aria-modal="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width:520px;">
        <div class="modal-content ps-crop-modal">
            <div class="modal-header ps-crop-header">
                <div class="d-flex align-items-center gap-2">
                    <div class="ps-crop-header-icon">
                        <i data-lucide="image" style="width:16px;height:16px;"></i>
                    </div>
                    <h6 class="modal-title mb-0">Crop Profile Photo</h6>
                </div>
                <button type="button" class="close ps-crop-close" data-dismiss="modal" aria-label="Close">
                    <i data-lucide="x" style="width:18px;height:18px;"></i>
                </button>
            </div>
            <div class="modal-body ps-crop-body">
                <div class="ps-crop-hint">
                    <i data-lucide="move" style="width:13px;height:13px;"></i>
                    Drag to reposition &bull; Scroll to zoom &bull; Drag corner to resize
                </div>
                <div class="ps-crop-container">
                    <img id="cropImage" src="" alt="Crop preview" style="max-width:100%;display:block;">
                </div>
            </div>
            <div class="ps-crop-preview-bar">
                <span class="ps-crop-preview-label">Preview</span>
                <div class="ps-crop-previews">
                    <div class="text-center">
                        <div class="ps-preview-circle ps-preview-lg" id="previewLg"></div>
                        <div class="ps-preview-size">80 px</div>
                    </div>
                    <div class="text-center">
                        <div class="ps-preview-circle ps-preview-md" id="previewMd"></div>
                        <div class="ps-preview-size">48 px</div>
                    </div>
                    <div class="text-center">
                        <div class="ps-preview-circle ps-preview-sm" id="previewSm"></div>
                        <div class="ps-preview-size">32 px</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer ps-crop-footer">
                <button type="button" class="btn ps-crop-btn-secondary" data-dismiss="modal">
                    <i data-lucide="x" style="width:14px;height:14px;"></i> Cancel
                </button>
                <button type="button" class="btn ps-crop-btn-rotate" id="cropRotateLeft" title="Rotate left">
                    <i data-lucide="rotate-ccw" style="width:14px;height:14px;"></i>
                </button>
                <button type="button" class="btn ps-crop-btn-rotate" id="cropRotateRight" title="Rotate right">
                    <i data-lucide="rotate-cw" style="width:14px;height:14px;"></i>
                </button>
                <button type="button" class="btn ps-crop-btn-primary" id="cropApplyBtn">
                    <i data-lucide="check" style="width:14px;height:14px;"></i> Apply &amp; Use Photo
                </button>
            </div>
        </div>
    </div>
</div>

@endsection

@section('css')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.css">
<link rel="stylesheet" href="{{ asset('css/profile.css') }}">
@endsection

@section('js')
<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.js"></script>
<script src="{{ asset('js/profile.js') }}"></script>
@endsection
