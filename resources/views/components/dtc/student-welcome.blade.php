<div class="student-welcome" id="studentWelcome" hidden
     data-endpoint="{{ route('notifications.student-welcome') }}" data-user="{{ auth()->id() }}"
     data-dashboard-url="{{ route('portal.dashboard') }}"
     data-redirect-after-ack="{{ request()->routeIs('portal.application.show') || (request()->routeIs('portal.dashboard') && auth()->user()->hasRole('new_applicant')) ? '1' : '0' }}">
    <section class="student-welcome-dialog" role="dialog" aria-modal="true" aria-labelledby="studentWelcomeTitle" aria-describedby="studentWelcomeBody" tabindex="-1">
        <div class="student-welcome-emblem" aria-hidden="true">
            <span><svg viewBox="0 0 32 32" fill="none"><path d="m7 16 6 6L26 9" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
        </div>
        <h2 id="studentWelcomeTitle"><span class="student-welcome-congrats">CONGRATS!</span>You're officially a student!</h2>
        <p id="studentWelcomeBody">Your enrollment is complete.</p>
        <button type="button" class="student-welcome-primary" id="studentWelcomeOpen">Continue</button>
    </section>
</div>
