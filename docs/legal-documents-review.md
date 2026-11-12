# Terms and Privacy Notice: items for DTC management and DPO review

The Terms and Conditions (`resources/views/legal/terms.blade.php`) and the Privacy Notice (`resources/views/legal/privacy.blade.php`) are both version 1.0, last updated November 10, 2026. They describe what the portal actually does as of commit `feature/complete-system-enhancements`. No admission qualifications, passing scores, fees, deadlines, cancellation penalties, retention periods, or contact details were invented.

Both documents show **"Effective date: Pending approval"** until DTC sets `LEGAL_TERMS_EFFECTIVE_DATE` and `LEGAL_PRIVACY_EFFECTIVE_DATE` (YYYY-MM-DD) in the server `.env`, then runs `php artisan config:cache`.

## 1. Information DTC must supply

| Item | Where it goes | Current behaviour until supplied |
| --- | --- | --- |
| Data Protection Officer (or designated privacy contact): name/title, email, telephone, office address | Admin → Settings → General → **Data Privacy Contact** | Notice tells applicants to contact the Registrar's Office at the address in Settings |
| Institution address | Admin → Settings → General → Address | Address is omitted from the documents if blank |
| Retention schedule: how long records of applicants who are not admitted, cancel, or do not enroll are kept, and how they are disposed of | Privacy Notice section 8 (edit the view) | Section 8 says the schedule is not yet published |
| Legal basis for processing **sensitive** personal information needed for admission (age from date of birth, education records), under DPA sec. 13 | Privacy Notice section 3, note under the table | The note says the basis is being confirmed |
| Whether any processing occurs outside the Philippines (web hosting location; Cloudflare) and any agreements in place | Privacy Notice section 6 | Notice says some providers *may* process information outside the Philippines |
| Approval and effective dates for each version | `.env` keys above | "Pending approval" |
| Policy on minors (for example, whether a parent or guardian must co-sign) | Terms section 13, Privacy Notice section 10 | Applicants under 18 are asked to involve a parent or guardian; no co-signature is required by the system |
| Admission, examination, fee, and refund policies | Not part of these documents; publish separately | Terms state that these are set by the school and are not part of the terms |

## 2. Lawful basis per processing purpose (proposed; for DPO confirmation)

| Purpose | Data | Proposed basis (RA 10173) |
| --- | --- | --- |
| Receive and manage an admission application; issue a reference number and status | Application data, uploaded requirements | Sec. 12(b): steps requested by the data subject before entering a contract (enrollment) |
| Verify identity, information, and documents | Identity, education, requirements | Sec. 12(b); sec. 12(f) legitimate interest in admitting qualified applicants |
| Review requirements and notify results | Requirements, contact details | Sec. 12(b) |
| Schedule, administer, and record entrance examinations | Contact details, bookings, attendance, results | Sec. 12(b) |
| Send updates, reminders, and access links | Name, email, application details | Sec. 12(b) |
| Evaluate admission and decide | Application, requirements, exam results | Sec. 12(b); sec. 12(f) |
| Enrollment, payment verification, Certificate of Registration | Account, enrollment, payment records | Sec. 12(b): performance of the enrollment contract |
| Keep student and institutional records; regulatory reporting | Admission and enrollment records | Sec. 12(c) legal obligation (DPO to cite the applicable CHED/TESDA rules); sec. 12(f) |
| Authorized administrative reports | Application and exam records | Sec. 12(f) |
| Security, abuse prevention, audit logs, consent records | IP address, browser, actions | Sec. 12(f) |
| **Sensitive data needed for admission**: age, education records | Date of birth, school history | **To be decided by the DPO** under sec. 13. Options include consent (13(a)) or another applicable ground. If consent is chosen, add a required consent box (see section 3 below). |
| Optional sensitive data: religion, civil status and spouse's name, disability, PWD ID | As listed | Sec. 13(a) consent, collected by a separate checkbox only when one of these fields is filled |

## 3. How agreement, acknowledgment, and consent work on the form

- **Terms**: a required checkbox (`ack_enrollment`) recorded as an *agreement*.
- **Privacy Notice**: a separate required checkbox (`acknowledge_privacy`) recorded as an *acknowledgment*. The notice states that this is not blanket consent.
- **Sensitive-information consent**: a third checkbox (`consent_sensitive`). It appears and is required only when the applicant fills in religion, civil status, spouse's name, disability, or PWD ID. Applicants may leave those fields blank instead.
- No box is ticked automatically. Opening or closing a document never changes the form or the boxes.
- Each agreement, acknowledgment, and consent is stored in `policy_acknowledgments` with the document, version, time, IP address, and browser. Rows cannot be updated, so a new version creates new rows and older records are never overwritten.
- Withdrawing consent (Privacy Notice section 4) is handled manually by the Registrar or DPO. The system has no self-service withdrawal yet.
- **Saved progress (added November 12, 2026)**: each time the applicant selects Next, the answers in that section and earlier sections are sent to the server for checking. They are kept in the applicant's session (not in the applications table) so the form can be resumed. Files are not uploaded until final submission. The saved answers are deleted on submission and last no longer than the session (`SESSION_LIFETIME`, currently 120 minutes of inactivity). Privacy Notice section 11 describes this. Version 1.0 was revised in place because it has not been approved or put into effect yet.

## 4. Changing the documents

1. Edit the view text.
2. If applicants must accept the change, raise `version` in `config/legal.php` and update `last_updated`.
3. Set the new effective date in `.env`, then run `php artisan config:cache`.

Applications submitted before the change keep the version they accepted.
