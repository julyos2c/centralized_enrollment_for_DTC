<?php

namespace App\Services;

use App\Models\AdmissionRequirement;
use App\Support\ApplicationEducation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The public application form's sections and their server-side rules.
 *
 * The same rules serve the final submission (all sections at once) and the
 * section-by-section check behind the wizard's Next button, which validates
 * a section together with every section before it. A request for a later
 * section therefore cannot succeed while an earlier one is incomplete.
 */
class ApplicationFormSteps
{
    /** Sections that hold applicant data; step 6 is the review and submission. */
    public const DATA_STEPS = 5;
    public const REVIEW_STEP = 6;

    public const TITLES = [
        1 => 'Admission',
        2 => 'Personal information',
        3 => 'Contact and address',
        4 => 'Education',
        5 => 'Family and documents',
        6 => 'Review and submit',
    ];

    /** Form fields by section (top-level names; documents.* belong to step 5). */
    public const FIELDS = [
        1 => ['academic_status', 'program_id'],
        2 => ['honorific', 'first_name', 'middle_name', 'last_name', 'suffix', 'gender', 'birthdate', 'birth_place',
            'religion', 'nationality', 'lrn', 'marital_status', 'spouse_name'],
        3 => ['email', 'phone', 'current_address', 'country', 'region', 'province', 'city', 'region_code', 'province_code',
            'city_code', 'barangay', 'postal_code', 'international_location'],
        4 => ['education_level', 'education_status', 'education_status_other', 'most_recent_school', 'school_choice',
            'school_id', 'school_sector', 'school_region_code', 'school_province_code', 'school_city_code',
            'school_barangay', 'school_street', 'school_location', 'school_year_started', 'school_year_started_other',
            'school_year_completed', 'school_year_completed_other', 'shs_curriculum_id', 'shs_strand_selection',
            'shs_strand', 'previous_program', 'last_year_level', 'home_institution', 'home_program'],
        5 => ['father_name', 'father_occupation', 'mother_name', 'mother_occupation', 'parent_address', 'parent_contact',
            'guardian_name', 'guardian_contact', 'occupation', 'disability', 'pwd_id', 'documents'],
        6 => ['ack_enrollment', 'acknowledge_privacy', 'consent_sensitive'],
    ];

    /** Values for school_choice set by the school picker (education-background.js). */
    public const SCHOOL_CHOICES = ['listed', '__not_listed', '__outside'];

    /** Session key holding the applicant's saved progress (not tied to an application record). */
    public const SESSION_KEY = 'public_application_progress';

    public function __construct(
        private PsgcLocationCatalog $locations,
        private EducationBackground $education,
    ) {
    }

    /** Step that a field or error key (e.g. "documents.form_138") belongs to; null if none. */
    public static function stepFor(string $key): ?int
    {
        $field = explode('.', $key)[0];
        foreach (self::FIELDS as $step => $fields) {
            if (in_array($field, $fields, true)) {
                return $step;
            }
        }

        return null;
    }

    /** Every rule for a complete application (used by the final submission). */
    public function rules(Request $request): array
    {
        $regionCodes = array_column($this->locations->regions(), 'code');
        $selectedRegion = (string) $request->input('region_code', '');
        $selectedProvince = (string) $request->input('province_code', '');
        $provinceCodes = array_column(array_values(array_filter(
            $this->locations->areas($selectedRegion),
            fn ($area) => $area['type'] === 'province'
        )), 'code');
        $cityOptions = $selectedProvince !== ''
            ? $this->locations->cities($selectedProvince)
            : array_values(array_filter(
                $this->locations->areas($selectedRegion),
                fn ($area) => $area['type'] === 'city'
            ));
        $inPhilippines = strcasecmp(trim((string) ($request->input('country') ?: 'Philippines')), 'Philippines') === 0;
        $academicStatus = is_string($request->input('academic_status')) ? $request->input('academic_status') : null;

        $rules = [
            // Not part of any section: checked against the session on every request.
            'submission_token' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/'],
            'academic_status' =>['required', Rule::in(['new_student', 'transferee', 'shiftee', 'returnee', 'cross_enrollee'])],
            'program_id' => ['required', 'integer', 'exists:programs,id'],

            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'honorific' => ['nullable', 'string', 'max:20'],
            'suffix' => ['nullable', 'string', 'max:20'],
            'gender' => ['required', Rule::in(['male', 'female'])],
            'birthdate' => ['required', 'date', 'before_or_equal:today'],
            'birth_place' => ['nullable', 'string', 'max:255'],
            'religion' => ['nullable', 'string', 'max:255'],
            'nationality' => ['nullable', 'string', 'max:255'],
            'lrn' => ['nullable', 'string', 'max:255'],
            'marital_status' => ['nullable', Rule::in(['single', 'married', 'divorced', 'widowed'])],
            'spouse_name' => ['nullable', 'string', 'max:255'],

            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'current_address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'province' => ['nullable', 'string', 'max:255'],
            'region' => ['nullable', 'string', 'max:255'],
            'barangay' => ['nullable', 'string', 'max:255'],
            'region_code' => ['nullable', 'string', Rule::in($regionCodes)],
            'province_code' => ['nullable', 'string', Rule::in($provinceCodes)],
            // The address is optional, but one that was started must reach a city or municipality.
            'city_code' => [
                Rule::requiredIf($inPhilippines && filled($request->input('region_code'))),
                'nullable', 'string', Rule::in(array_column($cityOptions, 'code')),
            ],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'international_location' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],

            'guardian_name' => ['nullable', 'string', 'max:255'],
            'guardian_contact' => ['nullable', 'string', 'max:50'],
            'father_name' => ['nullable', 'string', 'max:255'],
            'father_occupation' => ['nullable', 'string', 'max:255'],
            'mother_name' => ['nullable', 'string', 'max:255'],
            'mother_occupation' => ['nullable', 'string', 'max:255'],
            'parent_address' => ['nullable', 'string', 'max:255'],
            'parent_contact' => ['nullable', 'string', 'max:50'],
            'occupation' => ['nullable', 'string', 'max:255'],
            'disability' => ['nullable', 'string', 'max:255'],
            'pwd_id' => ['nullable', 'string', 'max:255'],

            // Agreement to the Terms, acknowledgment of the Privacy Notice, and
            // consent for optional sensitive details are separate (config/legal.php).
            'ack_enrollment' => ['required', 'accepted'],
            'acknowledge_privacy' => ['required', 'accepted'],
            'consent_sensitive' => $this->providesSensitiveInformation($request) ? ['required', 'accepted'] : ['nullable'],

            'education_level' => ['nullable', 'string', Rule::in(ApplicationEducation::levelsFor($academicStatus))],
            'most_recent_school' => ['nullable', 'string', 'max:255'],
            'school_year_completed' => ['nullable', 'string', 'max:30'],
            'school_location' => ['nullable', 'string', 'max:255'],

            'documents' => ['nullable', 'array'],
        ];

        // Digital documents are configured by the school (Admissions →
        // Requirements). Uploads here are optional: anything not uploaded
        // now can be added later in the applicant's admission portal.
        foreach (AdmissionRequirement::active()->ordered()->get() as $requirement) {
            $rules['documents.' . $requirement->code] = $requirement->uploadRules();
        }

        $educationFieldsByType = [
            'shs_strand' => ['new_student'],
            'previous_program' => ['transferee', 'shiftee', 'returnee'],
            'last_year_level' => ['transferee', 'shiftee', 'returnee', 'cross_enrollee'],
            'home_institution' => ['cross_enrollee'],
            'home_program' => ['cross_enrollee'],
        ];
        foreach ($educationFieldsByType as $field => $applicantTypes) {
            $maxLength = in_array($field, ['shs_strand', 'last_year_level'], true) ? 100 : 255;
            $rules[$field] = in_array($academicStatus, $applicantTypes, true)
                ? ['nullable', 'string', 'max:' . $maxLength]
                : ['exclude'];
        }

        $rules = array_merge($rules, $this->education->rules($request));

        // A school picked from the list must be sent with it; one that is not
        // listed (or outside the province) must be named.
        if ($academicStatus === 'new_student') {
            $choice = $request->input('school_choice');
            $rules['school_choice'] = ['nullable', 'string', Rule::in(self::SCHOOL_CHOICES)];
            $rules['most_recent_school'] = [
                Rule::requiredIf(in_array($choice, ['__not_listed', '__outside'], true)),
                'nullable', 'string', 'max:255',
            ];
            $rules['school_id'] = array_merge([Rule::requiredIf($choice === 'listed')], $rules['school_id']);
        } else {
            $rules['school_choice'] = ['exclude'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return $this->education->messages() + [
            'ack_enrollment.required' => 'Please agree to the Terms and Conditions to submit your application.',
            'ack_enrollment.accepted' => 'Please agree to the Terms and Conditions to submit your application.',
            'acknowledge_privacy.required' => 'Please confirm that you have read the Privacy Notice.',
            'acknowledge_privacy.accepted' => 'Please confirm that you have read the Privacy Notice.',
            'consent_sensitive.required' => 'You filled in optional sensitive details (religion, civil status, disability, or PWD ID). Give your consent, or clear those fields.',
            'consent_sensitive.accepted' => 'You filled in optional sensitive details (religion, civil status, disability, or PWD ID). Give your consent, or clear those fields.',
            'academic_status.required' => 'Choose your applicant type.',
            'program_id.required' => 'Choose the program you are applying for.',
            'first_name.required' => 'Enter your first name.',
            'last_name.required' => 'Enter your last name.',
            'gender.required' => 'Choose your gender.',
            'birthdate.required' => 'Enter your date of birth.',
            'email.required' => 'Enter your email address.',
            'email.email' => 'Enter a valid email address, like name@example.com.',
            'education_level.in' => 'Choose an education level that matches your applicant type.',
            'birthdate.before_or_equal' => 'Enter a date of birth that is today or earlier.',
            'region_code.in' => 'Select a valid region from the list.',
            'province_code.in' => 'Select a province that belongs to the chosen region.',
            'city_code.in' => 'Select a city or municipality that belongs to the chosen area.',
            'city_code.required' => 'Choose your city or municipality, or clear the region if you prefer not to give your address.',
            'most_recent_school.required' => 'Type the name of your Senior High School.',
            'school_id.required' => 'Choose your school from the list again, or choose "My school is not listed".',
            'school_choice.in' => 'Choose your school from the list, or choose "My school is not listed".',
        ];
    }

    /**
     * Validate sections 1 to $through with the submitted answers (text only;
     * files are checked on final submission), one section at a time.
     * Returns the validated data of those sections.
     *
     * @throws StepValidationException naming the first section that fails
     */
    public function validateThrough(Request $request, int $through): array
    {
        $rules = $this->rules($request);
        $validated = [];
        for ($step = 1; $step <= $through; $step++) {
            $stepRules = array_filter($rules, fn ($rule, $key) => self::stepFor($key) === $step, ARRAY_FILTER_USE_BOTH);
            $validator = Validator::make($request->input(), $stepRules, $this->messages());
            if ($validator->fails()) {
                throw new StepValidationException($step, $validator->errors()->toArray());
            }
            $validated += $validator->validated();

            if ($step === 4) {
                try {
                    $this->education->resolve($validated, (string) ($validated['academic_status'] ?? ''));
                } catch (ValidationException $e) {
                    throw new StepValidationException(4, $e->errors());
                }
            }
        }

        return $validated;
    }

    /**
     * Highest section, from 1, that is complete with the submitted data: the
     * requested section must pass (with all earlier ones), and sections the
     * applicant had already completed are kept while they still pass.
     *
     * @throws StepValidationException when the requested section (or an earlier one) fails
     */
    public function completeThrough(Request $request, int $step, int $previouslyCompleted): array
    {
        $validated = $this->validateThrough($request, $step);
        $completed = $step;
        for ($next = $step + 1; $next <= min($previouslyCompleted, self::DATA_STEPS); $next++) {
            try {
                $validated = $this->validateThrough($request, $next);
                $completed = $next;
            } catch (StepValidationException) {
                break;
            }
        }

        return [$completed, $validated];
    }

    /** Text answers of sections 1 to $through, as saved in the session draft. */
    public static function draftData(array $validated, int $through): array
    {
        $fields = array_merge(...array_map(fn ($step) => self::FIELDS[$step], range(1, max(1, $through))));

        return collect($validated)
            ->only(array_diff($fields, ['documents']))
            ->filter(fn ($value) => is_scalar($value) || $value === null)
            ->map(fn ($value) => $value === null ? '' : (string) $value)
            ->all();
    }

    /** Whether any optional field holding sensitive personal information was filled. */
    public function providesSensitiveInformation(Request $request): bool
    {
        foreach (config('legal.sensitive_optional_fields', []) as $field) {
            if (filled($request->input($field))) {
                return true;
            }
        }

        return false;
    }
}
