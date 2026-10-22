<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Mail\ApplicationSubmitted;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\Program;
use App\Models\User;
use App\Services\NotificationService;
use App\Support\ApplicationTextNormalizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ApplicationController extends Controller
{
    /**
     * Document requirements for each applicant type.
     *
     * These are optional digital uploads used for preliminary screening.
     */
    private const DOCUMENTS_BY_TYPE = [
        'new_student' => [
            'form_138',
            'good_moral_certificate',
            'birth_certificate',
        ],

        'transferee' => [
            'transfer_credentials',
            'transcript_of_records',
            'good_moral_certificate',
            'birth_certificate',
        ],

        'shiftee' => [],

        'returnee' => [],

        'cross_enrollee' => [],
    ];

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
            'documentRequirements' => self::DOCUMENTS_BY_TYPE,
            'physicalRequirements' => self::PHYSICAL_REQUIREMENTS,
            'titleCaseFields' => ApplicationTextNormalizer::titleCaseFields(),
            'submissionToken' => $submissionToken,
        ]);
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

            'ack_enrollment' => ['required', 'accepted'],
            'education_level' => ['nullable', 'string', Rule::in(['Junior High School', 'Senior High School', 'College', 'Other'])],
            'education_status' => ['nullable', 'string', Rule::in(['Graduated', 'Currently attending', 'Not completed'])],
            'most_recent_school' => ['nullable', 'string', 'max:255'],
            'school_year_completed' => ['nullable', 'string', 'max:30'],
            'school_location' => ['nullable', 'string', 'max:255'],

            // -------------------------------------------------------------
            // Digital documents
            //
            // IMPORTANT:
            // All uploads are optional because they are for preliminary
            // screening only. Physical documents are still required later.
            // -------------------------------------------------------------
            'documents' => [
                'nullable',
                'array',
            ],

            'documents.form_138' => [
                'nullable',
                'file',
                'mimes:pdf,jpg,jpeg,png',
                'max:5120',
            ],

            'documents.good_moral_certificate' => [
                'nullable',
                'file',
                'mimes:pdf,jpg,jpeg,png',
                'max:5120',
            ],

            'documents.birth_certificate' => [
                'nullable',
                'file',
                'mimes:pdf,jpg,jpeg,png',
                'max:5120',
            ],

            'documents.marriage_certificate' => [
                'nullable',
                'file',
                'mimes:pdf,jpg,jpeg,png',
                'max:5120',
            ],

            'documents.transfer_credentials' => [
                'nullable',
                'file',
                'mimes:pdf,jpg,jpeg,png',
                'max:5120',
            ],

            'documents.transcript_of_records' => [
                'nullable',
                'file',
                'mimes:pdf,jpg,jpeg,png',
                'max:5120',
            ],
        ];

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

        $validated = $request->validate($rules, [
            'birthdate.before_or_equal' => 'Enter a date of birth that is today or earlier.',
            'region_code.in' => 'Select a valid region from the list.',
            'province_code.in' => 'Select a province that belongs to the chosen region.',
            'city_code.in' => 'Select a city or municipality that belongs to the chosen area.',
        ]);

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
        } else {
            $validated['city'] = $validated['international_location'] ?? null;
            $validated['province'] = null;
            $validated['region'] = null;
            $validated['region_code'] = null;
            $validated['province_code'] = null;
            $validated['city_code'] = null;
        }

        /*
         * Marriage certificate is only applicable when the applicant
         * declares that they are married.
         */
        if (
            ($validated['marital_status'] ?? null) !== 'married'
            && $request->hasFile('documents.marriage_certificate')
        ) {
            return back()
                ->withInput()
                ->withErrors([
                    'documents.marriage_certificate' =>
                        'A Marriage Certificate may only be submitted when marital status is Married.',
                ]);
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
         * Determine which document types are actually allowed
         * for the selected applicant type.
         */
        $academicStatus = $validated['academic_status'];

        $allowedDocuments = self::DOCUMENTS_BY_TYPE[$academicStatus] ?? [];

        /*
         * Marriage Certificate is conditional.
         */
        if (($validated['marital_status'] ?? null) === 'married') {
            $allowedDocuments[] = 'marriage_certificate';
        }

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
                $allowedDocuments
            ) {
                $confirmationToken = bin2hex(random_bytes(32));
                $application = Application::create([
                    'reference_no' => $referenceNo,
                    'submission_token_hash' => hash('sha256', $validated['submission_token']),
                    'confirmation_token_hash' => hash('sha256', $confirmationToken),
                    'confirmation_token_expires_at' => now()->addDays(7),

                    'program_id' => $validated['program_id'],

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
                ]);

                /*
                 * Store only documents that:
                 *
                 * 1. Were actually uploaded;
                 * 2. Are allowed for the selected applicant type.
                 */
                foreach ($allowedDocuments as $documentType) {
                    if (!$request->hasFile("documents.$documentType")) {
                        continue;
                    }

                    $file = $request->file("documents.$documentType");

                    /*
                     * Generate a random UUID filename.
                     * The applicant's original filename is never used
                     * as the stored filename.
                     */
                    $extension = $file->getClientOriginalExtension();

                    $filename = (string) Str::uuid() . '.' . $extension;

                    /*
                     * Private local storage:
                     *
                     * storage/app/applications/{application_id}/...
                     *
                     * These files are NOT publicly accessible.
                     */
                    $filePath = $file->storeAs(
                        "applications/{$application->id}",
                        $filename,
                        'local'
                    );

                    ApplicationDocument::create([
                        'application_id' => $application->id,
                        'document_type' => $documentType,
                        'file_path' => $filePath,
                        'original_name' => $file->getClientOriginalName(),
                        'mime_type' => $file->getMimeType(),
                        'size_bytes' => $file->getSize(),
                    ]);
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

}
