# Public admission form: field requirement audit

Audit date: 2026-10-19. This matrix describes the public form, its server validation and persistence, the applicant review summary, and the authorized Registrar application detail view. “All five” means New Student, Transferee, Shiftee, Returnee, and Cross-Enrollee.

No approved DTC admissions policy establishing specific required education history, address components, or demographic requirements was found in the repository. Accordingly, education-history inputs and address components remain optional pending Registrar confirmation. This matrix records implementation behavior; it is not evidence of formally approved institutional policy. The current form's required gender field is retained because changing that legacy rule is outside this focused change; the Registrar should confirm its necessity and allowed values. The DTC Registrar's Office owns the policy confirmations listed below.

The admission identifiers and basic personal fields marked required below reflect the current application workflow and the scoped form rules, subject to formal Registrar confirmation where noted. A birthdate must also be no later than the application's current date, using Laravel's configured application timezone; malformed and future dates are rejected before persistence.

## Applicant and academic details

| Field | Requirement / applicant type | Frontend label and indicator | Laravel validation | Persisted | Review / Registrar detail |
|---|---|---|---|---|---|
| `academic_status` | Required; all five | Applicant Type * | `required`, allowlisted `Rule::in` for five types | `academic_status` | Yes / Yes |
| `program_id` | Required; all five | Intended Program * | `required`, `integer`, `exists:programs,id` | `program_id` | Program name / Yes |
| `submission_token` | Required technical anti-replay token; all five | Hidden, no applicant-facing label | required 64-char lowercase hex plus session token match | SHA-256 hash only | No / No |

## Personal information

| Field | Requirement / applicant type | Frontend label and indicator | Laravel validation | Persisted | Review / Registrar detail |
|---|---|---|---|---|---|
| `honorific` | Optional; all five | Title (optional, title text) | nullable allowlist: Mr., Ms., Mrs., Dr., Prof., Engr., Atty. | `honorific` | Included in full name / Yes |
| `first_name` | Required; all five | First Name * | required string, max 255 | `first_name` | Yes / Yes |
| `middle_name` | Optional; all five | Middle Name (Optional) | nullable string, max 255 | `middle_name` | Included in full name / Yes |
| `last_name` | Required; all five | Last Name * | required string, max 255 | `last_name` | Yes / Yes |
| `suffix` | Optional; all five | Suffix (optional, title text) | nullable allowlist: Jr., Sr., II, III, IV | `suffix` | Included in full name / Yes |
| `gender` | Required by current legacy rule; all five | Gender * | required; `male` or `female` | `gender` | Yes / Yes |
| `birthdate` | Required; all five | Date of Birth * | `required`, `date`, `before_or_equal:today` (friendly future-date validation message) | `birthdate` | Date and derived age / Yes |
| `birth_place` | Optional; all five | Place of Birth (Optional) | nullable string, max 255 | `birth_place` | Yes / Yes |
| `religion` | Optional; all five | Religion (Optional) | nullable string, max 255 | `religion` | Yes / Yes |
| `nationality` | Optional; all five | Nationality (Optional) | nullable string, max 255; defaults to Filipino on save | `nationality` | Yes / Yes |
| `lrn` | Optional; all five | LRN (Optional) | nullable string, max 255 | `lrn` | Yes / Yes |
| `marital_status` | Optional; all five | Marital Status (Optional) | nullable; single, married, divorced, or widowed | `marital_status` | Yes / Yes |
| `spouse_name` | Optional and only relevant when married; all five | Spouse Name (Optional), hidden when not married | nullable string, max 255 | `spouse_name` | Yes / Yes when married |

## Contact and address

| Field | Requirement / applicant type | Frontend label and indicator | Laravel validation | Persisted | Review / Registrar detail |
|---|---|---|---|---|---|
| `email` | Required; all five | Email Address * | required email, max 255 | `email` | Yes / Yes |
| `phone` | Optional; all five | Contact Number (Optional) | nullable string, max 50 | `phone` | Yes / Yes |
| `current_address` | Optional pending Registrar policy; all five | House No., Street (Optional) | nullable string, max 255 | `current_address` | Composed into address / Yes |
| `region` | Optional; all five | Location (Optional), cascading location picker | nullable string, max 255 | `region` | Composed into address / Yes |
| `province` | Optional; all five | Location (Optional), cascading location picker | nullable string, max 255 | `province` | Composed into address / Yes |
| `city` | Optional; all five | Location (Optional), cascading location picker | nullable string, max 255 | `city` | Composed into address / Yes |
| `barangay` | Optional; all five | Barangay (Optional), cascading location picker | nullable string, max 255 | `barangay` | Composed into address / Yes |
| `postal_code` | Optional; all five | Postal Code (Optional) | nullable string, max 20 | `postal_code` | Composed into address / Yes |
| `country` | Optional; all five | Country (Optional) | nullable string, max 255; defaults to Philippines on save | `country` | Composed into address / Yes |
| `_addr_region`, `_addr_province`, `_addr_city`, `_addr_barangay` | UI-only cascading controls; all five | Picker controls (not form fields for validation) | Disabled controls; server does not validate these | No; readable text values above are submitted | Readable values appear / Yes |

## Educational background

All education fields are currently optional because exact DTC requirements have not been verified. The type-specific fields are only validated and persisted for their applicable type. Inapplicable submitted values are excluded server-side and the UI disables hidden controls.

| Field | Requirement / applicant type | Frontend label and indicator | Laravel validation | Persisted | Review / Registrar detail |
|---|---|---|---|---|---|
| `education_level` | Optional; choices depend on applicant type | Highest or current education level | nullable; New Student: Senior High School or Other; Transferee/Shiftee/Returnee/Cross-Enrollee: College. Shared rules in ApplicationEducation. | `education_level` | Yes / Yes |
| `education_status` | Optional; all five | Completion status (Optional) | nullable; Graduated, Currently attending, Not completed | `education_status` | Yes / Yes |
| `most_recent_school` | Optional; all five | Most recent school attended (Optional) | nullable string, max 255 | `most_recent_school` | Yes / Yes |
| `school_year_completed` | Optional; all five | School year completed or last attended (Optional) | nullable string, max 30 | `school_year_completed` | Yes / Yes |
| `school_location` | Optional; all five | School location (Optional) | nullable string, max 255 | `school_location` | Yes / Yes |
| `shs_strand` | Optional; New Student only | Senior High School strand (Optional) | nullable string, max 100; excluded for other types | `shs_strand` | Yes when applicable / Yes |
| `previous_program` | Optional; Transferee, Shiftee, Returnee | Previous or current program (Optional) | nullable string, max 255; excluded for other types | `previous_program` | Yes when applicable / Yes |
| `last_year_level` | Optional; Transferee, Shiftee, Returnee, Cross-Enrollee | Last year level or semester attended (Optional) | nullable string, max 100; excluded for New Student | `last_year_level` | Yes when applicable / Yes |
| `home_institution` | Optional; Cross-Enrollee only | School or institution (Optional) | nullable string, max 255; excluded for other types | `home_institution` | Yes when applicable / Yes |
| `home_program` | Optional; Cross-Enrollee only | Current course or program (Optional) | nullable string, max 255; excluded for other types | `home_program` | Yes when applicable / Yes |

## Family, other details, and supporting documents

| Field | Requirement / applicant type | Frontend label and indicator | Laravel validation | Persisted | Review / Registrar detail |
|---|---|---|---|---|---|
| `father_name`, `father_occupation` | Optional; all five | Father's Name / Father's Occupation (Optional) | nullable string, max 255 | Same-named columns | Yes / Yes |
| `mother_name`, `mother_occupation` | Optional; all five | Mother's Name / Mother's Occupation (Optional) | nullable string, max 255 | Same-named columns | Yes / Yes |
| `parent_address` | Optional; all five | Parent's Address (Optional) | nullable string, max 255 | `parent_address` | Yes / Yes |
| `parent_contact` | Optional; all five | Parent's Contact (Optional) | nullable string, max 50 | `parent_contact` | Yes / Yes |
| `occupation` | Optional; all five | Occupation (Optional) | nullable string, max 255 | `occupation` | Yes / Yes |
| `disability` | Optional; all five | Disability (Optional) | nullable string, max 255 | `disability` | Yes / Yes |
| `pwd_id` | Optional; all five, shown when applicable | PWD ID Number (Optional) | nullable string, max 255 | `pwd_id` | Yes / Yes |
| `guardian_name`, `guardian_contact` | Not collected in current form; legacy nullable database fields | No frontend field | No submitted validation | Existing columns remain untouched | No / No |
| `documents.form_138` | Optional; New Student | Form 138 or Equivalent (Optional upload) | nullable file; pdf/jpg/jpeg/png, max 5120 KB | `ApplicationDocument` only if type is allowed | File list / Yes |
| `documents.good_moral_certificate` | Optional; New Student, Transferee | Certificate of Good Moral Character (Optional upload) | Same file rule | `ApplicationDocument` only if type is allowed | File list / Yes |
| `documents.birth_certificate` | Optional; New Student, Transferee | PSA Birth Certificate (Optional upload) | Same file rule | `ApplicationDocument` only if type is allowed | File list / Yes |
| `documents.transfer_credentials` | Optional; Transferee | Certificate of Transfer Credentials (Optional upload) | Same file rule | `ApplicationDocument` only if type is allowed | File list / Yes |
| `documents.transcript_of_records` | Optional; Transferee | Transcript of Records (Optional upload) | Same file rule | `ApplicationDocument` only if type is allowed | File list / Yes |
| `documents.marriage_certificate` | Optional and conditional on marital status = married; all five | Marriage Certificate (Optional, shown only when married) | Same file rule; rejected if provided when not married | `ApplicationDocument` only when married and type allows it | File list / Yes |

## Acknowledgment and audit fields

| Field | Requirement / applicant type | Frontend label and required indicator | Laravel validation | Persisted | Review / Registrar detail |
|---|---|---|---|---|---|
| `ack_enrollment` | Required acceptance; all five | Required checkbox: accuracy, Terms, Privacy Policy, and application-not-admission notice | `required`, `accepted` | `acknowledged_at` server timestamp | Review step contains acknowledgment / authorized Registrar view shows timestamp |
| `ack_privacy`, `acknowledgement` | Removed; were hidden and not authoritative | None | None | No | No |

`reviewed_by`, `reviewed_at`, `remarks`, `status`, generated reference, and confirmation token are system/review workflow fields, not applicant inputs. The applicant sees a submitted/preliminary-review state; submission is not represented as approval or enrollment.

## Items requiring Registrar confirmation

Owner for each item: DTC Registrar's Office. Until confirmed, the corresponding optionality and legacy gender behavior above remain unchanged.

1. Which education information is truly required for each of the five applicant types? Candidate fields are listed above, but none is currently mandatory.
2. Is gender required for application processing, and are the current `male`/`female` values sufficient for DTC policy?
3. Does DTC need a current residential address at submission? If yes, identify which parts (street, barangay, city, province, region, postal code, country) are mandatory and whether non-Philippine addresses must be supported.
4. Are any physical document lists or later enrollment submissions mandatory for Shiftee, Returnee, and Cross-Enrollee? Existing UI states are preserved; preliminary uploads remain optional.
5. Is a phone/contact number needed for application follow-up, or is email alone sufficient?

Do not turn any of these into required validation until the Registrar confirms the policy and the corresponding form label, validation, persistence, review, and staff display are updated together.
