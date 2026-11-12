{{-- Privacy Notice body. Expects $legal from App\Support\LegalDocuments::context(). --}}
@php $school = $legal['institution']; @endphp
<article class="legal-doc">
    @include('legal._meta', ['meta' => $legal['privacy'], 'titleId' => $titleId ?? 'privacy-title'])

    <section>
        <h3>1. About this notice</h3>
        <p>{{ $school }} ("DTC", "we") is the personal information controller for the information you give through {{ $legal['system'] }}, its online admission and enrollment portal. This notice explains what we collect, why, how it is used and protected, how long it is kept, and your rights under the Data Privacy Act of 2012 (Republic Act No. 10173), its Implementing Rules and Regulations, and issuances of the National Privacy Commission.</p>
        <p>When you apply, you are asked to <strong>acknowledge</strong> that you have read this notice. That acknowledgment is not a blanket consent to every use of your information. Where we rely on your consent — for the optional sensitive details described in section 4 — we ask for it separately.</p>
    </section>

    <section>
        <h3>2. Information we collect</h3>
        <p>We collect only what the portal asks for. Fields not marked as required may be left blank.</p>
        <h4>a. Application information</h4>
        <ul>
            <li><strong>Identity:</strong> full name (with honorific and suffix, if any), sex, date of birth (from which your age is calculated), place of birth, nationality, and Learner Reference Number (LRN).</li>
            <li><strong>Contact and address:</strong> email address, contact number, home address (street, barangay, city or municipality, province, region, postal code, country).</li>
            <li><strong>Family and guardian:</strong> names, occupations, address, and contact numbers of your parents or guardian, and your own occupation.</li>
            <li><strong>Optional sensitive details:</strong> religion, civil status and spouse's name, disability, and PWD ID number (see section 4).</li>
            <li><strong>Admission details:</strong> applicant type, intended program, the admission term, your application reference number, and your application status.</li>
            <li><strong>Educational background:</strong> education level and completion status, previous school (and its DepEd School ID, type, and location when you choose a listed school), school years attended, Senior High School curriculum and strand or elective cluster, and — for transferees, shiftees, returnees, and cross-enrollees — previous program, home institution, and last year level attended.</li>
        </ul>
        <h4>b. Admission requirements</h4>
        <p>The documents you upload (for example your report card or Form 138, PSA birth certificate, certificate of good moral character, transfer credentials, transcript, marriage certificate, or ID photo, depending on what the school requires), with the file name, type, size, and upload time, and the review result and any reason for rejection. Replaced uploads are kept as part of the review history.</p>
        <h4>c. Entrance examination</h4>
        <p>Your examination bookings (session, date, venue, and status), attendance, and — when the school records them — your result, score, and remarks. You see a result only after the school releases it.</p>
        <h4>d. Decisions, communications, and requests</h4>
        <p>Registrar remarks, requests for revision, cancellation requests and their outcome, the admission decision, and the emails and in-portal notifications sent to you about your application.</p>
        <h4>e. After admission (student and alumni portal)</h4>
        <p>Your portal account (name, email address, a securely hashed password, and an optional profile photo); enrollment submissions, subjects, and the Certificate of Registration; payment records, including proofs of payment you upload (such as GCash receipts) and their verification by the Cashier; office appointments; and, for alumni, document requests and their payment and release details.</p>
        <h4>f. Account and technical information</h4>
        <ul>
            <li>The time, IP address, and browser of your agreement to the Terms, acknowledgment of this notice, and any consent you give, kept as a record of what you agreed to.</li>
            <li>Your IP address and the email address used, held briefly to limit repeated login, lookup, and submission attempts.</li>
            <li>Session information needed to keep you signed in, and records of actions performed by signed-in users (including staff), with the IP address and browser used, for security and accountability.</li>
            <li>Server and error logs kept by the system to operate and troubleshoot the portal.</li>
        </ul>
    </section>

    <section>
        <h3>3. Why we process your information</h3>
        <table class="legal-table">
            <thead><tr><th>Purpose</th><th>Information used</th><th>Basis under the Data Privacy Act</th></tr></thead>
            <tbody>
                <tr><td>Receiving and managing your admission application, and giving you a reference number and status</td><td>Application information, requirements</td><td>Processing needed to act on your request before entering into an enrollment contract (sec. 12(b)); for sensitive information needed for admission, such as age and education records, see the note below</td></tr>
                <tr><td>Verifying your identity and the information and documents you submit</td><td>Identity, educational background, requirements</td><td>Sec. 12(b); the school's legitimate interest in admitting qualified applicants (sec. 12(f))</td></tr>
                <tr><td>Reviewing requirements and telling you the result</td><td>Requirements, contact details</td><td>Sec. 12(b)</td></tr>
                <tr><td>Scheduling, administering, and recording entrance examinations</td><td>Contact details, bookings, attendance, results</td><td>Sec. 12(b)</td></tr>
                <tr><td>Sending application updates, reminders, and account links</td><td>Name, email address, application details</td><td>Sec. 12(b)</td></tr>
                <tr><td>Evaluating admission and making the admission decision</td><td>Application, requirements, examination results</td><td>Sec. 12(b); sec. 12(f)</td></tr>
                <tr><td>Processing enrollment and payments and issuing the Certificate of Registration</td><td>Account, enrollment, and payment records</td><td>Performance of the enrollment contract (sec. 12(b))</td></tr>
                <tr><td>Keeping student and institutional records and complying with reporting obligations of the school</td><td>Admission and enrollment records</td><td>Compliance with legal obligations of the school (sec. 12(c)) and the school's legitimate interest (sec. 12(f))</td></tr>
                <tr><td>Producing authorized administrative reports (for example, applicants per program or examination attendance)</td><td>Application and examination records</td><td>Sec. 12(f)</td></tr>
                <tr><td>Protecting the portal, preventing misuse, and keeping security and accountability records</td><td>Technical information, records of actions</td><td>Sec. 12(f)</td></tr>
                <tr><td>Processing the optional sensitive details you choose to give</td><td>Religion, civil status and spouse's name, disability, PWD ID</td><td>Your consent (sec. 13(a)), asked for separately</td></tr>
            </tbody>
        </table>
        <p class="legal-note">Your age (from your date of birth) and your education records are sensitive personal information under the Data Privacy Act. DTC processes them only as needed to evaluate your application and keep your academic record. The specific legal basis for this processing is being confirmed by DTC's Data Protection Officer and will be stated here before this notice takes effect.</p>
    </section>

    <section>
        <h3>4. Optional sensitive information and your consent</h3>
        <p>Religion, civil status, spouse's name, disability, and PWD ID number are optional. If you fill in any of them, the form asks for your separate consent to process them. They are used to complete your student record and to provide support or accommodations you may need. You may leave these fields blank without affecting your application. You may withdraw your consent later by contacting the privacy contact in section 12. Withdrawal does not affect processing already done.</p>
    </section>

    <section>
        <h3>5. How DTC personnel use your information</h3>
        <p>Your information is handled by school personnel whose duties require it, according to their role in the portal:</p>
        <ul>
            <li><strong>Registrar's Office staff</strong> review applications and documents, manage examination sessions and results, decide on admission, and process enrollment and student records.</li>
            <li><strong>Cashier staff</strong> record and verify payments.</li>
            <li><strong>System administrators</strong> manage accounts, settings, reports, and backups.</li>
        </ul>
        <p>Staff access is restricted by role. Uploaded documents and payment proofs can be opened only by authorized staff and by the applicant or student they belong to. Staff actions on records are logged.</p>
    </section>

    <section>
        <h3>6. Sharing and disclosure</h3>
        <p>DTC does not sell your personal information and does not share it for marketing. Your information may be disclosed only:</p>
        <ul>
            <li>to authorized DTC personnel, as described in section 5;</li>
            <li>to service providers that operate the portal for DTC, under DTC's instructions and appropriate safeguards — the web hosting provider that stores the portal and its files, the service that delivers the portal and protects it from attacks (Cloudflare), and the school's email service. Pages also load fonts and display components from public content networks (Google Fonts, jsDelivr, and unpkg), which receive your IP address and browser information when your browser requests them;</li>
            <li>to the issuing school or agency, when DTC verifies a document or record you submitted; and</li>
            <li>when required by law, a lawful order, or a government authority with jurisdiction.</li>
        </ul>
        <p>Some of these providers may process information outside the Philippines. DTC remains responsible for your information when it is processed by its providers.</p>
        <p>The portal itself does not send your information to GCash or to any bank. When you pay through GCash, you deal with GCash directly under its own terms; DTC receives only the proof of payment you upload.</p>
    </section>

    <section>
        <h3>7. How we protect your information</h3>
        <ul>
            <li>The portal is served over an encrypted (HTTPS) connection.</li>
            <li>Uploaded documents and payment proofs are stored in private storage, under random file names, and are not reachable through public links.</li>
            <li>Applicant self-service access uses expiring secure links. Account passwords are stored only in hashed form. Sessions end after a period of inactivity.</li>
            <li>Login and lookup attempts are rate-limited, and staff access is limited by role and logged.</li>
            <li>Database backups are restricted to system administrators.</li>
        </ul>
        <p>No system is completely secure. If a personal data breach affects you, DTC will act on it and notify you and the National Privacy Commission as the Data Privacy Act requires.</p>
    </section>

    <section>
        <h3>8. How long we keep your information</h3>
        <p>DTC keeps personal information only as long as needed for the purposes in section 3, for the school's legal and regulatory obligations, and for the establishment or defense of legal claims. Records of enrolled students form part of the school's permanent academic records.</p>
        <p class="legal-pending">DTC's retention schedule is not yet published. It will state how long the records of applicants who are not admitted, who cancel, or who do not enroll are kept, and how they are disposed of. It will be added to this notice once DTC confirms it.</p>
    </section>

    <section>
        <h3>9. Your rights</h3>
        <p>Under the Data Privacy Act you have the right:</p>
        <ul>
            <li><strong>to be informed</strong> that your personal information is being processed, and how — which is what this notice does;</li>
            <li><strong>to access</strong> your personal information held by DTC;</li>
            <li><strong>to object</strong> to processing, including withdrawing consent you gave, where processing is based on consent or legitimate interest;</li>
            <li><strong>to rectification</strong> of inaccurate or incomplete information;</li>
            <li><strong>to erasure or blocking</strong> of information that is incomplete, outdated, false, unlawfully obtained, or no longer necessary, subject to the school's legal obligations to keep records;</li>
            <li><strong>to data portability</strong>, to obtain a copy of your information in an electronic format where the law provides;</li>
            <li><strong>to lodge a complaint</strong> with the National Privacy Commission (<a href="https://privacy.gov.ph" target="_blank" rel="noopener">privacy.gov.ph</a>); and</li>
            <li><strong>to claim damages</strong> for harm caused by inaccurate, incomplete, false, unlawfully obtained, or unauthorized use of your information, as provided by law.</li>
        </ul>
        <p>To exercise these rights, contact the privacy contact in section 12 and mention your application reference number. DTC may need to verify your identity before acting on a request.</p>
    </section>

    <section>
        <h3>10. Applicants who are minors</h3>
        <p>Many applicants are below 18 years old. If you are a minor, please complete your application with your parent or legal guardian and give their details in the family section. A parent or legal guardian may exercise your privacy rights on your behalf. DTC may contact the parent or guardian you list about your application when appropriate.</p>
    </section>

    <section>
        <h3>11. Cookies and information stored on your device</h3>
        <p>The portal does not use advertising cookies or third-party analytics or tracking tools. It uses:</p>
        <ul>
            <li><strong>Necessary cookies</strong>: a session cookie that keeps your application or account session working, a security cookie that protects forms from forgery, and — only if you tick "remember me" when signing in — a cookie that keeps you signed in. The portal cannot work without the first two.</li>
            <li><strong>Browser storage on your device</strong>: your unfinished application is saved in your browser so it is not lost if the page closes, and it is cleared after you submit. Your light or dark display preference is also saved. On a shared or public computer, finish or clear your application and close the browser when you are done.</li>
            <li><strong>Saved progress while you apply</strong>: each time you select Next, the answers in that section and the sections before it are sent to the portal to be checked, and are saved with your browser session so you can continue where you left off. Files you choose are not uploaded until you submit. These saved answers are deleted when you submit and are kept no longer than your session, which ends after {{ (int) config('session.lifetime') }} minutes without activity. No application record is created until you submit.</li>
            <li><strong>Offline notice</strong>: if you install the portal as an app, it stores a single "you are offline" page; no personal information is stored this way.</li>
        </ul>
    </section>

    <section>
        <h3>12. Privacy contact</h3>
        @include('legal._contact')
    </section>

    <section>
        <h3>13. Changes to this notice</h3>
        <p>DTC may update this notice when the portal or the law changes. Each version has a version number, effective date, and last-updated date. The version you acknowledged is recorded with your application.</p>
    </section>
</article>
