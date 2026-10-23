<div class="student-welcome" id="studentWelcome" hidden
     data-endpoint="{{ route('notifications.student-welcome') }}" data-user="{{ auth()->id() }}">
    <section class="student-welcome-dialog" role="dialog" aria-modal="true" aria-labelledby="studentWelcomeTitle" aria-describedby="studentWelcomeBody" tabindex="-1">
        <h2 id="studentWelcomeTitle">You're officially a student!</h2>
        <p id="studentWelcomeBody">Your enrollment is complete. Your student portal is ready.</p>
        <a class="student-welcome-primary" id="studentWelcomeOpen" href="{{ route('portal.dashboard') }}">Open student portal</a>
        <button type="button" class="student-welcome-later" id="studentWelcomeLater">Later</button>
    </section>
</div>
