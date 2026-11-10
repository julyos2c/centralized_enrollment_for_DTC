{{-- Terms and Conditions body. Expects $legal from App\Support\LegalDocuments::context(). --}}
@php $school = $legal['institution']; @endphp
<article class="legal-doc">
    @include('legal._meta', ['meta' => $legal['terms'], 'titleId' => $titleId ?? 'terms-title'])

    <section>
        <h3>1. Introduction and acceptance of these terms</h3>
        <p>These Terms and Conditions govern your use of {{ $legal['system'] }}, the online admission and enrollment portal of {{ $school }} ("DTC", "the school", "we"). The portal is used to apply for admission, upload admission requirements, book entrance examinations, track an application, and complete enrollment.</p>
        <p>By ticking the agreement box when you submit an application, or by using a portal account, you agree to these terms. If you do not agree, please do not submit an application online; contact the Registrar's Office instead. If you are below 18 years old, please read these terms with your parent or legal guardian (see section 13).</p>
        <p>How we handle your personal information is explained separately in the <strong>Privacy Notice</strong>. Agreeing to these terms is not the same as acknowledging the Privacy Notice; the application form asks for each separately.</p>
    </section>

    <section>
        <h3>2. Purpose of the online admission portal</h3>
        <p>The portal lets prospective students begin the admission process without first visiting the campus. Through it you can:</p>
        <ul>
            <li>submit an admission application and receive an application reference number;</li>
            <li>upload digital copies of admission requirements and see whether each one is approved, rejected, or still under review;</li>
            <li>book, change, or cancel an entrance examination session when the school offers online booking;</li>
            <li>check your application status and receive updates by email; and</li>
            <li>after admission, activate a student account, submit enrollment, and follow payment verification.</li>
        </ul>
        <p>The portal supports, but does not replace, the school's admission and enrollment rules, which are set and applied by DTC.</p>
    </section>

    <section>
        <h3>3. Applying and portal accounts</h3>
        <p>You do not need an account to apply. When you submit an application you receive a reference number. You can reopen your application in the <strong>admission portal</strong> from the same browser for a limited time, or through a secure link sent to the email address on your application. That link expires after 24 hours and may be requested again from the status page.</p>
        <p>If your application is approved, the school creates a portal account using the email address on your application and sends a link to set your password. Account access is personal: use your own email address, do not create accounts for other people, and do not share your password or access links.</p>
    </section>

    <section>
        <h3>4. Accuracy and completeness of submitted information</h3>
        <p>You must give true, accurate, current, and complete information, written as it appears on your official documents. Fields marked as required must be completed; other fields may be left blank. If your information changes or you discover a mistake after submitting, tell the Registrar's Office. The school may ask you to correct or explain information, and may decline an application that contains false or misleading information.</p>
    </section>

    <section>
        <h3>5. Personal and contact information</h3>
        <p>Use an email address and contact number that you can access throughout the admission period. Important messages — your reference number, document review results, examination schedules, and the admission decision — are sent by email. The school is not responsible for messages you miss because the contact details you gave are wrong or no longer used.</p>
    </section>

    <section>
        <h3>6. Educational background information</h3>
        <p>Give your previous school, school years, completion status, and (for new students) your Senior High School curriculum and strand as accurately as you can. Choosing a school from the list does not by itself verify your records. If your school or strand is not listed, or you do not remember an exact detail, use the options provided rather than choosing an inaccurate entry. The school may verify educational information with the issuing school.</p>
    </section>

    <section>
        <h3>7. Submission of admission requirements</h3>
        <p>The documents you are asked to upload, whether each is required or optional, and the accepted file types and sizes are set by the school and shown beside each requirement. You may upload requirements with your application or later in the admission portal.</p>
        <ul>
            <li>Upload a clear, complete, and readable copy of the correct document for each requirement.</li>
            <li>A document that is rejected shows the reason; you may upload a corrected copy.</li>
            <li>An approved document is locked. Contact the Registrar's Office if it needs to be corrected.</li>
            <li>Uploaded copies are for screening. You must still present original or physical documents to the Registrar's Office when asked, before official enrollment.</li>
        </ul>
    </section>

    <section>
        <h3>8. Authenticity and validity of uploaded documents</h3>
        <p>Every document you upload must be authentic, unaltered, and issued to you. Do not upload documents that belong to someone else, that have been edited, or that you are not entitled to use. The school may compare uploaded copies with the originals and may verify them with the issuing institution or agency.</p>
    </section>

    <section>
        <h3>9. Entrance examination appointment scheduling</h3>
        <p>Whether an entrance examination is part of admission, and whether it is required, is decided by the school and may change between admission periods. When online booking is open:</p>
        <ul>
            <li>examination dates, times, venues, and the number of seats are set by the school, and sessions are subject to availability;</li>
            <li>the school may require your required documents to be approved before you can book;</li>
            <li>a booking may be confirmed immediately or may first need approval by the Registrar's Office, as shown when you book; and</li>
            <li>you may hold only one active examination booking at a time.</li>
        </ul>
        <p>Arrive on time with a valid identification card and your application reference number, and follow any instructions shown for your session.</p>
    </section>

    <section>
        <h3>10. Appointment cancellation and rescheduling</h3>
        <p>If the school allows online changes, you may move or cancel your booking in the admission portal until the cut-off shown there before the examination starts. After the cut-off, contact the Registrar's Office. The school may reschedule, move, or cancel a session — for example because of a closure or a change of venue — and will notify booked applicants by email so they can choose another session. If you do not attend, you may be marked absent; you may then book another available session if the school's policy allows.</p>
    </section>

    <section>
        <h3>11. Admission evaluation and qualification</h3>
        <p><strong>Submitting an application does not guarantee admission.</strong> DTC evaluates every application under its own admission policies, which may consider your submitted information, your requirements, and — where the school requires it — your entrance examination result. Examination results are shown to you only after the school releases them. The admission decision rests with the school's authorized personnel.</p>
    </section>

    <section>
        <h3>12. Application status and notifications</h3>
        <p>You are responsible for monitoring your application. Check the status page or the admission portal, and read the emails sent to the address on your application, including your spam or junk folder. Email delivery can be delayed or fail for reasons outside the school's control; if you expect an update and have not received one, check the portal or contact the Registrar's Office.</p>
    </section>

    <section>
        <h3>13. Applicant responsibilities</h3>
        <ul>
            <li>Keep your reference number, portal links, and account credentials private.</li>
            <li>Respond to requests for corrections or additional documents.</li>
            <li>Attend scheduled examinations and appointments, or change or cancel them in time.</li>
            <li>Follow the official instructions of the Registrar's Office and the Cashier.</li>
            <li>If you are a minor, involve your parent or legal guardian in your application, and give their correct details where asked.</li>
        </ul>
    </section>

    <section>
        <h3>14. Prohibited activities and misrepresentation</h3>
        <p>You must not:</p>
        <ul>
            <li>give false information, pretend to be another person, or apply on someone's behalf without their authority;</li>
            <li>upload falsified, altered, or borrowed documents;</li>
            <li>try to access another applicant's application, documents, or account;</li>
            <li>interfere with the portal — for example by automated submissions, attempts to bypass limits or security, or uploading harmful files; or</li>
            <li>use the portal for any purpose other than your own admission and enrollment.</li>
        </ul>
        <p>The school may decline or cancel an application, restrict portal access, and take action under its policies and applicable law if these terms are violated.</p>
    </section>

    <section>
        <h3>15. Account security and unauthorized access</h3>
        <p>The portal limits repeated login and lookup attempts and uses expiring links to protect applicants. You are responsible for activity under your account or access links that results from sharing them. If you think someone has accessed your application or account without permission, contact the Registrar's Office immediately.</p>
    </section>

    <section>
        <h3>16. Application review and processing</h3>
        <p>Authorized school personnel review applications and documents. An application may be marked as submitted, under review, requiring revision, approved, rejected, or cancelled. When corrections are requested, the reason is shown in the portal and sent by email. You may ask to cancel an application that has not yet been decided; the Registrar's Office reviews each cancellation request.</p>
    </section>

    <section>
        <h3>17. Enrollment procedures</h3>
        <p>Admission and enrollment are separate steps. After admission, activate your account and follow the enrollment instructions in the student portal: submit your enrollment for review by the Registrar's Office and complete payment. Enrollment is complete only after the school approves your enrollment and verifies your payment. Fees, payment methods, and deadlines are set by the school and are shown in the portal or announced by the school; they are not part of these terms.</p>
    </section>

    <section>
        <h3>18. System availability and maintenance</h3>
        <p>The school aims to keep the portal available but does not promise uninterrupted access. The portal may be unavailable during maintenance, updates, or circumstances outside the school's control. If you cannot complete a step because the portal is unavailable, contact the Registrar's Office.</p>
    </section>

    <section>
        <h3>19. Limitation of liability</h3>
        <p>To the extent permitted by Philippine law, the school is not liable for loss or delay caused by inaccurate information you provided, by your failure to monitor your application or email, by your devices or internet connection, or by events outside the school's reasonable control. Nothing in these terms limits any right you have under the Data Privacy Act of 2012 or other law that cannot be limited by agreement.</p>
    </section>

    <section>
        <h3>20. Changes to these terms</h3>
        <p>The school may update these terms when the portal or its policies change. Each version has a version number, effective date, and last-updated date. The version you agreed to is recorded with your application; a new version will be shown to you when you are asked to agree again.</p>
    </section>

    <section>
        <h3>21. Contact and support</h3>
        <p>For questions about your application, examinations, or enrollment, contact the Registrar's Office of {{ $school }}{{ $legal['address'] ? ', ' . $legal['address'] : '' }}, and mention your application reference number. For questions about your personal information, see the privacy contact below.</p>
        @include('legal._contact')
    </section>
</article>
