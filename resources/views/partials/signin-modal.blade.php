{{--
    DTC Nexus — Sign In Modal
    Trigger: data-signin-modal="trigger" on any element
--}}

<div
    id="dtcSignInModal"
    class="dtc-modal-overlay"
    role="dialog"
    aria-modal="true"
    aria-labelledby="dtcModalTitle"
    hidden
>
    <div class="dtc-modal-backdrop" data-signin-modal="close" aria-hidden="true"></div>

    <div class="dtc-modal-box">

        {{-- ── Header — styled like public navbar ─────────── --}}
        <div class="dtc-modal-header">

            <div class="dtc-modal-brand">
                <img
                    src="{{ asset('images/DTC-LOGO.webp') }}"
                    alt="DTC Logo"
                    class="dtc-modal-logo-img"
                >
                <div>
                    <p class="dtc-modal-brand-name">DTC Nexus</p>
                    <p class="dtc-modal-brand-sub">Danao Technological College</p>
                </div>

                <button
                    class="dtc-modal-close"
                    type="button"
                    data-signin-modal="close"
                    aria-label="Close sign-in dialog"
                >
                    <i data-lucide="x" aria-hidden="true"></i>
                </button>
            </div>

            <h2 class="dtc-modal-tagline" id="dtcModalTitle">
                Your DTC services, <span>connected.</span>
            </h2>

        </div>

        {{-- ── Body ──────────────────────────────────────── --}}
        <div class="dtc-modal-body">

            @if (session('error') || $errors->any())
                <div class="dtc-modal-error" role="alert">
                    <i data-lucide="alert-circle" aria-hidden="true"></i>
                    {{ session('error') ?? $errors->first() }}
                </div>
            @endif

            <p class="dtc-modal-form-sub">
                Use your <strong>DTC Nexus account</strong> to continue
            </p>

            <form
                id="dtcSignInForm"
                method="POST"
                action="{{ route('login') }}"
                novalidate
            >
                @csrf

                {{-- Email --}}
                <div class="dtc-modal-field">
                    <label for="modal_email" class="dtc-modal-label">
                        Email address
                    </label>
                    <div class="dtc-modal-input-wrap">
                        <i data-lucide="mail" class="dtc-modal-input-icon" aria-hidden="true"></i>
                        <input
                            type="email"
                            id="modal_email"
                            name="email"
                            class="dtc-modal-input @error('email') dtc-modal-input--error @enderror"
                            value="{{ old('email') }}"
                            placeholder="you@dtc.edu.ph"
                            autocomplete="email"
                            required
                        >
                    </div>
                    @error('email')
                        @if ($message !== $errors->first())
                            <p class="dtc-modal-field-error">{{ $message }}</p>
                        @endif
                    @enderror
                </div>

                {{-- Password --}}
                <div class="dtc-modal-field">
                    <label for="modal_password" class="dtc-modal-label">
                        Password
                    </label>
                    <div class="dtc-modal-input-wrap">
                        <i data-lucide="lock" class="dtc-modal-input-icon" aria-hidden="true"></i>
                        <input
                            type="password"
                            id="modal_password"
                            name="password"
                            class="dtc-modal-input @error('password') dtc-modal-input--error @enderror"
                            placeholder="••••••••"
                            autocomplete="current-password"
                            required
                        >
                        <button
                            type="button"
                            class="dtc-modal-eye"
                            aria-label="Toggle password visibility"
                            data-signin-modal="toggle-pw"
                        >
                            <i data-lucide="eye" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>

                {{-- Remember me + Forgot password --}}
                <div class="dtc-modal-remember">
                    <label class="dtc-modal-check-label">
                        <input
                            type="checkbox"
                            name="remember"
                            id="modal_remember"
                            {{ old('remember') ? 'checked' : '' }}
                        >
                        Remember me
                    </label>
                    <a href="{{ route('password.request') }}" class="dtc-modal-forgot">
                        Forgot password?
                    </a>
                </div>

                <div style="text-align:right; margin-top:-8px; margin-bottom:16px;">
                    <a href="{{ route('password.activation.request') }}" class="dtc-modal-forgot">
                        Need an activation link?
                    </a>
                </div>

                <button type="submit" class="dtc-modal-submit" data-loading-text="Signing in…">
                    Sign in
                </button>

            </form>

        </div>

        <p class="dtc-modal-footer">
            &copy; {{ date('Y') }} Danao Technological College
        </p>

    </div>
</div>
