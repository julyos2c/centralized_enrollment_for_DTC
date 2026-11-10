<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Mail\ApplicationSubmitted;
use App\Models\AdmissionRequirement;
use App\Models\Application;
use App\Models\Program;
use App\Models\Setting;
use App\Models\User;
use App\Services\AdmissionDocumentService;
use App\Services\NotificationService;
use App\Support\ApplicationTextNormalizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ApplicationController extends Controller
{
    /**
     * Physical requirements shown as reminders.
     *
     * These are NOT stored as application documents.
     */
    private const PHYSICAL_REQUIREMENTS = [
        'new_student' => [
            'Form 138 or equivalent — original/required physical copy',
            'Certificate of Good Moral Character — original/required physical copy',
            'PSA Birth Certificate — required physical copy',
            'Marriage Certificate — if applicable',
            '2 copies recent 2×2 picture',
            '2 copies recent 1×1 picture',
            '2 pcs Long Brown Envelope',
        ],

        'transferee' => [
            'Certificate of Transfer Credentials',
            'Transcript of Records',
            'Certificate of Good Moral Character',
            'PSA Birth Certificate',
            'Marriage Certificate — if applicable',
            '2 copies recent 2×2 picture',
            '2 copies recent 1×1 picture',
            '2 pcs Long Brown Envelope',
        ],

        'shiftee' => [],

        'returnee' => [],

        'cross_enrollee' => [],
    ];

    /**
     * Display the public application form.
     */
    public function create(Request $request)
    {
        $programs = Program::orderBy('name')->get();
        $submissionToken = bin2hex(random_bytes(32));
        $request->session()->put('public_application_submission_token', $submissionToken);

        return view('public.application.create', [
            'programs' => $programs,
            'requirementsByType' => $this->requirementsByType(),
            'conditionalRequirements' => AdmissionRequirement::active()->ordered()->where('condition', 'married')->get(),
            'physicalRequirements' => self::PHYSICAL_REQUIREMENTS,
            'titleCaseFields' => ApplicationTextNormalizer::titleCaseFields(),
            'submissionToken' => $submissionToken,
            'addressLookup' => \App\Support\AddressLookup::config(),
            'educationLookup' => [
                'schools' => request()->getBaseUrl() . route('public.application.schools', [], false),
                'classifications' => request()->getBaseUrl() . route('public.application.shs-classifications', [], false),
            ],
            'educationOld' => $this->educationOldInput(),
            'schoolScope' => $this->schoolScope(),
            'legal' => \App\Support\LegalDocuments::context(),
            'academicYears' => \App\Support\AcademicYears::options(),
            'educationStatuses' => \App\Services\EducationBackground::STATUSES,
            'schoolSectors' => \App\Models\School::SECTORS,
        ]);
    }

    /**
     * The province whose schools applicants pick from (Bohol by default) and
     * its municipalities / cities from PSGC, for the school picker.
     */
    private function schoolScope(): ?array
    {
        $province = config('schools.province_code');
        $region = config('schools.region_code');
        if (!$province || !$region) {
            return null;
        }
        $catalog = app(\App\Services\PsgcLocationCatalog::class);
        $area = $catalog->area($province, $region);
        if (!$area) {
            return null;
        }
        $localities = collect($catalog->cities($province))
            ->map(fn ($city) => ['code' => $city['code'], 'name' => \App\Models\School::displayLocality($city['name'])])
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values()->all();

        return ['region_code' => $region, 'province_code' => $province, 'province_name' => $area['name'], 'localities' => $localities];
    }

    /** Previous answers to restore after a validation error (structured fields only). */
    private function educationOldInput(): array
    {
        $school = old('school_id') ? \App\Models\School::active()->find(old('school_id')) : null;

        return [
            'school' => $school?->toLookupArray(),
            'school_region_code' => old('school_region_code'),
            'school_province_code' => old('school_province_code'),
            'school_city_code' => old('school_city_code'),
            'school_barangay' => old('school_barangay'),
            'most_recent_school' => old('most_recent_school'),
            'school_choice' => old('school_choice'),
            'shs_curriculum_id' => old('shs_curriculum_id'),
            'shs_strand_selection' => old('shs_strand_selection'),
        ];
    }


    /**
     * Submit a public application.
     */
    public function store(Request $request)
    {
        $locations = app(\App\Services\PsgcLocationCatalog::class);
        $submissionToken = $request->input('submission_token');
        $sessionSubmissionToken = $request->session()->get('public_application_submission_token');
        if (
            !is_string($submissionToken)
            || !is_string($sessionSubmissionToken)
            || !hash_equals($sessionSubmissionToken, $submissionToken)
        ) {
            return back()->withInput()->withErrors([
                'application' => 'This form has expired or was already submitted. Please review the application status or start a new application.',
            ]);
        }

        // Normalize human-entered text before validation, review data, and storage.
        $request->merge(ApplicationTextNormalizer::normalizeFields($request->all()));

        $regionCodes = array_column($locations->regions(), 'code');
        $selectedRegion = (string) $request->input('region_code', '');
        $selectedProvince = (string) $request->input('province_code', '');
        $provinceCodes = array_column(array_values(array_filter(
            $locations->areas($selectedRegion),
            fn ($area) => $area['type'] === 'province'
        )), 'code');
        $cityOptions = $selectedProvince !== ''
            ? $locations->cities($selectedProvince)
            : array_values(array_filter(
                $locations->areas($selectedRegion),
                fn ($area) => $area['type'] === 'city'
            ));

        $rules = [
            'submission_token' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/'],
            'academic_status' => [
                'required',
                Rule::in([
                    'new_student',
                    'transferee',
                    'shiftee',
                    'returnee',
                    'cross_enrollee',
                ]),
            ],

            'program_id' => [
                'required',
                'integer',
                'exists:programs,id',
            ],

            'first_name' => [
                'required',
                'string',
                'max:255',
            ],

            'last_name' => [
                'required',
                'string',
                'max:255',
            ],

            'middle_name' => [
                'nullable',
                'string',
                'max:255',
            ],

            'honorific' => ['nullable', 'string', 'max:20'],
            'suffix' => ['nullable', 'string', 'max:20'],

            'gender' => [
                'required',
                Rule::in(['male', 'female']),
            ],

            'birthdate' => [
                'required',
                'date',
                'before_or_equal:today',
            ],

            'birth_place' => [
                'nullable',
                'string',
                'max:255',
            ],

            'religion' => [
                'nullable',
                'string',
                'max:255',
            ],

            'nationality' => [
                'nullable',
                'string',
                'max:255',
            ],

            'lrn' => [
                'nullable',
                'string',
                'max:255',
            ],

            'marital_status' => [
                'nullable',
                Rule::in([
                    'single',
                    'married',
                    'divorced',
                    'widowed',
                ]),
            ],

            'email' => [
                'required',
                'email',
                'max:255',
            ],

            'phone' => [
                'nullable',
                'string',
                'max:50',
            ],

            'current_address' => [
                'nullable',
                'string',
                'max:255',
            ],

            'city' => [
                'nullable',
                'string',
                'max:255',
            ],

            'province' => [
                'nullable',
                'string',
                'max:255',
            ],

            'region' => ['nullable', 'string', 'max:255'],
            'barangay' => ['nullable', 'string', 'max:255'],
            'region_code' => ['nullable', 'string', Rule::in($regionCodes)],
            'province_code' => ['nullable', 'string', Rule::in($provinceCodes)],
            'city_code' => ['nullable', 'string', Rule::in(array_column($cityOptions, 'code'))],

            'postal_code' => [
                'nullable',
                'string',
                'max:20',
            ],

            'international_location' => ['nullable', 'string', 'max:255'],

            'country' => [
                'nullable',
                'string',
                'max:255',
            ],

            'guardian_name' => ['nullable', 'string', 'max:255'],
            'guardian_contact' => ['nullable', 'string', 'max:50'],

            'father_name' => [
                'nullable',
                'string',
                'max:255',
            ],

            'father_occupation' => [
                'nullable',
                'string',
                'max:255',
            ],

            'mother_name' => [
                'nullable',
                'string',
                'max:255',
            ],

            'mother_occupation' => [
                'nullable',
                'string',
                'max:255',
            ],

            'parent_address' => [
                'nullable',
                'string',
                'max:255',
            ],

            'parent_contact' => [
                'nullable',
                'string',
                'max:50',
            ],

            'spouse_name' => [
                'nullable',
                'string',
                'max:255',
            ],

            'occupation' => [
                'nullable',
                'string',
                'max:255',
            ],

            'disability' => [
                'nullable',
                'string',
                'max:255',
            ],

            'pwd_id' => [
                'nullable',
                'string',
                'max:255',
            ],

            // Agreement to the Terms, acknowledgment of the Privacy Notice, and
            // consent for optional sensitive details are separate (config/legal.php).
            'ack_enrollment' => ['required', 'accepted'],
            'acknowledge_privacy' => ['required', 'accepted'],
            'consent_sensitive' => $this->providesSensitiveInformation($request) ? ['required', 'accepted'] : ['nullable'],
            'education_level' => ['nullable', 'string', Rule::in(\App\Support\ApplicationEducation::levelsFor(is_string($request->input('academic_status')) ? $request->input('academic_status') : null))],
            'education_status' => ['nullable', 'string', Rule::in(['Graduated', 'Currently attending', 'Not completed'])],
            'most_recent_school' => ['nullable', 'string', 'max:255'],
            'school_year_completed' => ['nullable', 'string', 'max:30'],
            'school_location' => ['nullable', 'string', 'max:255'],

            'documents' => [
                'nullable',
                'array',
            ],
        ];

        // Digital documents are configured by the school (Admissions →
        // Requirements). Uploads here are optional: anything not uploaded
        // now can be added later in the applicant's admission portal.
        $applicantType = is_string($request->input('academic_status')) ? $request->input('academic_status') : null;
        $maritalStatus = is_string($request->input('marital_status')) ? $request->input('marital_status') : null;
        $allRequirements = AdmissionRequirement::active()->ordered()->get();
        $allowedRequirements = $allRequirements
            ->filter(fn (AdmissionRequirement $requirement) => $requirement->appliesTo($applicantType, $maritalStatus))
            ->keyBy('code');
        foreach ($allRequirements as $requirement) {
            $rules['documents.' . $requirement->code] = $requirement->uploadRules();
        }

        $educationFieldsByType = [
            'shs_strand' => ['new_student'],
            'previous_program' => ['transferee', 'shiftee', 'returnee'],
            'last_year_level' => ['transferee', 'shiftee', 'returnee', 'cross_enrollee'],
            'home_institution' => ['cross_enrollee'],
            'home_program' => ['cross_enrollee'],
        ];
        $academicStatus = $request->input('academic_status');
        foreach ($educationFieldsByType as $field => $applicantTypes) {
            $maxLength = in_array($field, ['shs_strand', 'last_year_level'], true) ? 100 : 255;
            $rules[$field] = in_array($academicStatus, $applicantTypes, true)
                ? ['nullable', 'string', 'max:' . $maxLength]
                : ['exclude'];
        }

        $education = app(\App\Services\EducationBackground::class);
        $rules = array_merge($rules, $education->rules($request));

        $validated = $request->validate($rules, $education->messages() + [
            'ack_enrollment.required' => 'Please agree to the Terms and Conditions to submit your application.',
            'ack_enrollment.accepted' => 'Please agree to the Terms and Conditions to submit your application.',
            'acknowledge_privacy.required' => 'Please confirm that you have read the Privacy Notice.',
            'acknowledge_privacy.accepted' => 'Please confirm that you have read the Privacy Notice.',
            'consent_sensitive.required' => 'You filled in optional sensitive details (religion, civil status, disability, or PWD ID). Give your consent, or clear those fields.',
            'consent_sensitive.accepted' => 'You filled in optional sensitive details (religion, civil status, disability, or PWD ID). Give your consent, or clear those fields.',
            'education_level.in' => 'Choose an education level that matches your applicant type.',
            'birthdate.before_or_equal' => 'Enter a date of birth that is today or earlier.',
            'region_code.in' => 'Select a valid region from the list.',
            'province_code.in' => 'Select a province that belongs to the chosen region.',
            'city_code.in' => 'Select a city or municipality that belongs to the chosen area.',
        ]);

        // Structured education choices (school lookup, strand, years); also fills
        // the original text columns so existing views and reports stay correct.
        $educationColumns = $education->resolve($validated, $validated['academic_status']);

        // Never treat browser-submitted geographic labels as authoritative.
        if (strcasecmp(trim($validated['country'] ?? 'Philippines'), 'Philippines') === 0) {
            $validated['region'] = null;
            $validated['province'] = null;
            $validated['city'] = null;

            if (!empty($validated['region_code'])) {
                $validated['region'] = $locations->region($validated['region_code'])['name'];
                if (!empty($validated['province_code'])) {
                    $area = $locations->area($validated['province_code'], $validated['region_code']);
                    $validated['province'] = $area['name'];
                    if (!empty($validated['city_code'])) {
                        $validated['city'] = $locations->city(
                            $validated['city_code'],
                            $validated['province_code']
                        )['name'];
                    }
                } elseif (!empty($validated['city_code'])) {
                    $directCity = collect($locations->areas($validated['region_code']))
                        ->firstWhere('code', $validated['city_code']);
                    $validated['city'] = $directCity['name'];
                }
            }

            // Store the official barangay spelling when it is on the PSGC list;
            // otherwise keep what the applicant typed ("not listed" entries).
            if (!empty($validated['city_code']) && filled($validated['barangay'] ?? null)) {
                $validated['barangay'] = $locations->barangayName($validated['city_code'], $validated['barangay'])
                    ?? $validated['barangay'];
            }
        } else {
            $validated['city'] = $validated['international_location'] ?? null;
            $validated['province'] = null;
            $validated['region'] = null;
            $validated['region_code'] = null;
            $validated['province_code'] = null;
            $validated['city_code'] = null;
        }

        /*
         * Conditional requirements (e.g. the Marriage Certificate) only
         * apply when the applicant's answers match their condition.
         */
        foreach ($allRequirements as $requirement) {
            if ($requirement->condition && !$allowedRequirements->has($requirement->code) && $request->hasFile('documents.' . $requirement->code)) {
                return back()
                    ->withInput()
                    ->withErrors([
                        'documents.' . $requirement->code => $requirement->condition === 'married'
                            ? 'A Marriage Certificate may only be submitted when marital status is Married.'
                            : $requirement->name . ' does not apply to this application.',
                    ]);
            }
        }

        /*
         * Generate a unique applicant reference number.
         */
        do {
            $referenceNo = 'APP-' . now()->format('Y') . '-' . strtoupper(
                Str::random(6)
            );
        } while (
            Application::where('reference_no', $referenceNo)->exists()
        );

        /*
         * Create the application and its documents atomically.
         *
         * If anything fails, the database transaction rolls back.
         */
        try {
            $application = DB::transaction(function () use (
                $validated,
                $request,
                $referenceNo,
                $allowedRequirements,
                $educationColumns
            ) {
                $confirmationToken = bin2hex(random_bytes(32));
                $application = Application::create(array_merge([
                    'reference_no' => $referenceNo,
                    'submission_token_hash' => hash('sha256', $validated['submission_token']),
                    'confirmation_token_hash' => hash('sha256', $confirmationToken),
                    'confirmation_token_expires_at' => now()->addDays(7),

                    'program_id' => $validated['program_id'],
                    'school_year' => Setting::get('current_school_year'),
                    'semester' => Setting::get('current_semester'),

                    'academic_status' => $validated['academic_status'],

                    'first_name' => $validated['first_name'],
                    'last_name' => $validated['last_name'],
                    'middle_name' => $validated['middle_name'] ?? null,
                    'honorific' => $validated['honorific'] ?? null,
                    'suffix' => $validated['suffix'] ?? null,

                    'gender' => $validated['gender'],
                    'birthdate' => $validated['birthdate'],
                    'birth_place' => $validated['birth_place'] ?? null,

                    'religion' => $validated['religion'] ?? null,
                    'nationality' => $validated['nationality'] ?? 'Filipino',
                    'lrn' => $validated['lrn'] ?? null,
                    'marital_status' => $validated['marital_status'] ?? null,

                    'email' => $validated['email'],
                    'phone' => $validated['phone'] ?? null,

                    /*
                     * The old `address` column is intentionally left
                     * untouched. New applications use the structured
                     * address fields added in Step 2.
                     */
                    'current_address' => $validated['current_address'] ?? null,
                    'city' => $validated['city'] ?? null,
                    'province' => $validated['province'] ?? null,
                    'region' => $validated['region'] ?? null,
                    'region_code' => $validated['region_code'] ?? null,
                    'province_code' => $validated['province_code'] ?? null,
                    'city_code' => $validated['city_code'] ?? null,
                    'barangay' => $validated['barangay'] ?? null,
                    'postal_code' => $validated['postal_code'] ?? null,
                    'country' => $validated['country'] ?? 'Philippines',

                    'guardian_name' => $validated['guardian_name'] ?? null,
                    'guardian_contact' => $validated['guardian_contact'] ?? null,
                    'father_name' => $validated['father_name'] ?? null,
                    'father_occupation' => $validated['father_occupation'] ?? null,
                    'mother_name' => $validated['mother_name'] ?? null,
                    'mother_occupation' => $validated['mother_occupation'] ?? null,
                    'parent_address' => $validated['parent_address'] ?? null,
                    'parent_contact' => $validated['parent_contact'] ?? null,

                    'spouse_name' => $validated['spouse_name'] ?? null,

                    'occupation' => $validated['occupation'] ?? null,
                    'disability' => $validated['disability'] ?? null,
                    'pwd_id' => $validated['pwd_id'] ?? null,

                    'education_level' => $validated['education_level'] ?? null,
                    'education_status' => $validated['education_status'] ?? null,
                    'most_recent_school' => $validated['most_recent_school'] ?? null,
                    'school_year_completed' => $validated['school_year_completed'] ?? null,
                    'school_location' => $validated['school_location'] ?? null,
                    'shs_strand' => $validated['shs_strand'] ?? null,
                    'previous_program' => $validated['previous_program'] ?? null,
                    'last_year_level' => $validated['last_year_level'] ?? null,
                    'home_institution' => $validated['home_institution'] ?? null,
                    'home_program' => $validated['home_program'] ?? null,
                    'acknowledged_at' => now(),

                    'status' => 'submitted',
                ], $educationColumns));

                $this->recordPolicyAcknowledgments($application, $request);

                /*
                 * Store only documents that were uploaded and apply to the
                 * selected applicant type. Files go to private storage under
                 * random names (see AdmissionDocumentService).
                 */
                foreach ($allowedRequirements as $code => $requirement) {
                    if ($request->hasFile("documents.$code")) {
                        app(AdmissionDocumentService::class)->store($application, $requirement, $request->file("documents.$code"));
                    }
                }

                return [$application, $confirmationToken];
            });
            [$application, $confirmationToken] = $application;
            $request->session()->forget('public_application_submission_token');

            /*
             * Notify existing Registrar users.
             *
             * Notification failures should not undo a successfully
             * submitted application.
             */
            try {
                $registrars = User::role(['registrar', 'admin'])->get();

                foreach ($registrars as $registrar) {
                    NotificationService::send(
                        $registrar,
                        'New Application Submitted',
                        "Application {$application->reference_no} has been submitted by {$application->first_name} {$application->last_name}.",
                        'info',
                        route('registrar.applications.show', $application)
                    );
                }
            } catch (\Throwable $notificationException) {
                /*
                 * Deliberately ignored.
                 *
                 * The application itself has already been successfully
                 * created. Notification failure should not cause the
                 * applicant's submission to fail.
                 */
            }

            try {
                Mail::to($application->email)->send(new ApplicationSubmitted($application));
            } catch (\Throwable $mailException) {
                Log::warning('Application reference email could not be sent.', [
                    'application_id' => $application->id,
                ]);
            }

            $request->session()->put('application_confirmation_token', $confirmationToken);
            AdmissionPortalController::grantAccess($request, $application);

            return redirect()
                ->route('public.application.success')
                ->with('application_just_submitted', true)
                ->with('success', 'Your application has been submitted successfully.');

        } catch (\Throwable $exception) {
            /*
             * If database/storage processing fails, return the applicant
             * to the form with their entered information preserved.
             */
            return back()
                ->withInput()
                ->withErrors([
                    'application' =>
                        'We could not submit your application at this time. Please try again.',
                ]);
        }
    }

    /**
     * Display the application success/reference page.
     */
    public function success(Request $request)
    {
        $token = $request->session()->get('application_confirmation_token');
        abort_unless(is_string($token) && preg_match('/^[a-f0-9]{64}$/', $token), 404);

        $application = Application::with('pendingCancellationRequest')->where('confirmation_token_hash', hash('sha256', $token))
            ->where('confirmation_token_expires_at', '>', now())
            ->firstOrFail();

        return view('public.application.success', [
            'application' => $application,
            'physicalRequirements' =>
                self::PHYSICAL_REQUIREMENTS[$application->academic_status] ?? [],
        ]);
    }

    /**
     * Display the public application status lookup form.
     */
    public function statusForm()
    {
        return view('public.application.status');
    }

    /**
     * Look up an application using reference number + email.
     */
    public function status(Request $request)
    {
        $validated = $request->validate([
            'reference_no' => [
                'required',
                'string',
                'max:50',
            ],

            'email' => [
                'required',
                'email',
                'max:255',
            ],
        ]);

        $application = Application::with([
            'program',
            'documents',
            'user.studentProfile',
            'user.enrollments.payment',
        ])
            ->where('reference_no', $validated['reference_no'])
            ->where('email', $validated['email'])
            ->first();

        if (!$application) {
            return back()
                ->withInput()
                ->withErrors([
                    'reference_no' =>
                        'No application was found using the provided reference number and email address.',
                ]);
        }

        $enrollment = $application->getEnrollmentForProgress();

        return view('public.application.status', [
            'application' => $application,
            'enrollment' => $enrollment,
            'progressTimeline' => $application->getProgressTimeline($enrollment),
            'nextStep' => $application->getStatusNextStep($enrollment),
            'physicalRequirements' =>
                self::PHYSICAL_REQUIREMENTS[$application->academic_status] ?? [],
        ]);
    }

    /**
     * Active upload requirements grouped by applicant type for the wizard.
     * Conditional requirements are rendered separately.
     */
    private function requirementsByType(): array
    {
        $requirements = AdmissionRequirement::active()->ordered()->whereNull('condition')->get();
        $grouped = [];
        foreach (array_keys(AdmissionRequirement::APPLICANT_TYPES) as $type) {
            $grouped[$type] = $requirements->filter(fn ($requirement) => $requirement->appliesTo($type))->values();
        }

        return $grouped;
    }

    /** Whether any optional field holding sensitive personal information was filled. */
    private function providesSensitiveInformation(Request $request): bool
    {
        foreach (config('legal.sensitive_optional_fields', []) as $field) {
            if (filled($request->input($field))) {
                return true;
            }
        }

        return false;
    }

    /** Append-only record of the policy versions this application agreed to. */
    private function recordPolicyAcknowledgments(Application $application, Request $request): void
    {
        $documents = ['terms', 'privacy'];
        if ($request->boolean('consent_sensitive') && $this->providesSensitiveInformation($request)) {
            $documents[] = 'sensitive_consent';
        }
        foreach ($documents as $document) {
            \App\Models\PolicyAcknowledgment::create([
                'application_id' => $application->id,
                'document' => $document,
                'kind' => \App\Models\PolicyAcknowledgment::KINDS[$document],
                'version' => config("legal.{$document}.version"),
                'acknowledged_at' => now(),
                'ip_address' => $request->ip(),
                'user_agent' => \Illuminate\Support\Str::limit((string) $request->userAgent(), 250, ''),
            ]);
        }
    }
}
