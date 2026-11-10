<?php

namespace App\Services;

use App\Models\School;
use App\Models\ShsClassification;
use App\Models\ShsCurriculum;
use App\Support\AcademicYears;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Validation and storage rules for the application form's structured
 * education details (school lookup, school location, SHS curriculum and
 * strand, school years, completion status).
 *
 * Everything is additive: the long-standing text columns (most_recent_school,
 * school_location, shs_strand, school_year_completed, education_status) are
 * still accepted as plain text and are also filled from the structured
 * choices, so older clients, records, reports, and views keep working.
 */
class EducationBackground
{
    public const OTHER = '__other';

    /** Stored value => label. "Currently attending" / "Not completed" are the original values. */
    public const STATUSES = [
        'Graduated' => 'Graduated',
        'Currently attending' => 'Currently enrolled',
        'Transferred' => 'Transferred to another school',
        'Not completed' => 'Did not complete',
        'Other' => 'Other',
    ];

    public function __construct(private PsgcLocationCatalog $locations)
    {
    }

    public function rules(Request $request): array
    {
        $newStudent = $request->input('academic_status') === 'new_student';
        $only = fn (array $rules) => $newStudent ? $rules : ['exclude'];

        $location = \App\Support\AddressLookup::rules($request->all(), 'school_region_code', 'school_province_code', 'school_city_code');

        $curriculumId = $request->input('shs_curriculum_id');
        $classificationIds = is_numeric($curriculumId)
            ? ShsClassification::active()->where('shs_curriculum_id', $curriculumId)->pluck('id')->map(fn ($id) => (string) $id)->all()
            : [];

        return [
            'education_status' => ['nullable', 'string', Rule::in(array_keys(self::STATUSES))],
            'education_status_other' => ['nullable', 'required_if:education_status,Other', 'string', 'max:255'],
            'school_year_started' => ['nullable', 'string', 'max:30'],
            'school_year_started_other' => ['nullable', 'required_if:school_year_started,' . self::OTHER, 'string', 'max:30'],
            'school_year_completed_other' => ['nullable', 'required_if:school_year_completed,' . self::OTHER, 'string', 'max:30'],

            'school_id' => $only(['nullable', 'integer', Rule::exists('schools', 'id')->where(function ($query) {
                $query->where('is_active', true);
                if (config('schools.province_code')) {
                    $query->where('province_code', config('schools.province_code'));
                }
            })]),
            'school_sector' => $only(['nullable', Rule::in(array_keys(School::SECTORS))]),
            'school_region_code' => $only($location['school_region_code']),
            'school_province_code' => $only($location['school_province_code']),
            'school_city_code' => $only($location['school_city_code']),
            'school_barangay' => $only(['nullable', 'string', 'max:255']),
            'school_street' => $only(['nullable', 'string', 'max:255']),

            'shs_curriculum_id' => $only(['nullable', 'integer', Rule::exists('shs_curricula', 'id')->where('is_active', true)]),
            'shs_strand_selection' => $only(['nullable', 'string', Rule::in(array_merge($classificationIds, ['other', 'not_sure']))]),
        ];
    }

    public function messages(): array
    {
        return [
            'education_status_other.required_if' => 'Describe your completion status.',
            'school_year_started_other.required_if' => 'Type the school year you started.',
            'school_year_completed_other.required_if' => 'Type the school year you graduated or last attended.',
            'school_id.exists' => 'Choose a school from the list, or choose "My school is not listed".',
            'school_province_code.in' => 'Select a province that belongs to the chosen region.',
            'school_city_code.in' => 'Select a city or municipality that belongs to the chosen area.',
            'shs_strand_selection.in' => 'Choose a strand from the list for the selected curriculum.',
        ];
    }

    /**
     * Resolve "Other" choices, check that years and status agree, and return
     * the columns to store. Throws a ValidationException on inconsistencies.
     */
    public function resolve(array $validated, string $academicStatus): array
    {
        $out = [];

        foreach (['school_year_started', 'school_year_completed'] as $field) {
            if (($validated[$field] ?? null) === self::OTHER) {
                $validated[$field] = $validated[$field . '_other'] ?? null;
            }
            $out[$field] = $validated[$field] ?? null;
        }
        $out['education_status'] = $validated['education_status'] ?? null;
        $out['education_status_other'] = $out['education_status'] === 'Other' ? ($validated['education_status_other'] ?? null) : null;

        $this->assertYearsAgree($out['school_year_started'], $out['school_year_completed'], $out['education_status']);

        if ($academicStatus !== 'new_student') {
            return $out;
        }

        // Previous school: a listed school wins over typed text.
        $school = !empty($validated['school_id']) ? School::find($validated['school_id']) : null;
        $out['school_id'] = $school?->id;
        $out['school_deped_id'] = $school?->deped_school_id;
        $out['school_sector'] = $school ? $school->sector : ($validated['school_sector'] ?? null);
        if ($school) {
            $out['most_recent_school'] = $school->name;
        }

        // School location (PSGC codes are authoritative, like the home address).
        $out += $this->schoolLocation($validated);

        // Curriculum and strand / cluster.
        $selection = $validated['shs_strand_selection'] ?? null;
        $curriculum = !empty($validated['shs_curriculum_id']) ? ShsCurriculum::find($validated['shs_curriculum_id']) : null;
        $out['shs_curriculum_id'] = $curriculum?->id;
        $out['shs_classification_id'] = null;
        $out['shs_strand_choice'] = null;
        if (is_numeric($selection)) {
            $classification = ShsClassification::find($selection);
            $out['shs_classification_id'] = $classification->id;
            $out['shs_strand_choice'] = 'selected';
            $out['shs_strand'] = $classification->label();
        } elseif ($selection === 'other') {
            if (blank($validated['shs_strand'] ?? null)) {
                throw ValidationException::withMessages(['shs_strand' => 'Type your strand or specialization.']);
            }
            $out['shs_strand_choice'] = 'other';
        } elseif ($selection === 'not_sure') {
            $out['shs_strand_choice'] = 'not_sure';
            $out['shs_strand'] = 'Not sure' . ($curriculum ? " ({$curriculum->name})" : '');
        }

        if ($curriculum && $out['education_status'] === 'Graduated'
            && !$curriculum->coversGraduationYear(AcademicYears::startYear($out['school_year_completed']))) {
            throw ValidationException::withMessages([
                'shs_curriculum_id' => "The {$curriculum->name} applies to " . strtolower($curriculum->yearsLabel()) . '. Check your curriculum or school year.',
            ]);
        }

        return $out;
    }

    private function schoolLocation(array $validated): array
    {
        $region = $validated['school_region_code'] ?? null;
        if (!$region) {
            return [
                'school_region_code' => null, 'school_province_code' => null, 'school_city_code' => null,
                'school_barangay' => $validated['school_barangay'] ?? null,
                'school_street' => $validated['school_street'] ?? null,
            ];
        }

        $province = $validated['school_province_code'] ?? null;
        $cityCode = $validated['school_city_code'] ?? null;
        $provinceName = $province ? ($this->locations->area($province, $region)['name'] ?? null) : null;
        $cityName = null;
        if ($cityCode) {
            $cityName = $province
                ? ($this->locations->city($cityCode, $province)['name'] ?? null)
                : (collect($this->locations->areas($region))->firstWhere('code', $cityCode)['name'] ?? null);
        }
        $barangay = $validated['school_barangay'] ?? null;
        if ($cityCode && filled($barangay)) {
            $barangay = $this->locations->barangayName($cityCode, $barangay) ?? $barangay;
        }
        $regionName = $this->locations->region($region)['name'] ?? null;

        return [
            'school_region_code' => $region,
            'school_province_code' => $province,
            'school_city_code' => $cityCode,
            'school_barangay' => $barangay,
            'school_street' => $validated['school_street'] ?? null,
            // Keep the readable text column in step with the structured location.
            'school_location' => implode(', ', array_filter([
                $validated['school_street'] ?? null, $barangay, $cityName, $provinceName ?? ($cityName ? null : $regionName),
            ])) ?: null,
        ];
    }

    private function assertYearsAgree(?string $started, ?string $completed, ?string $status): void
    {
        $start = AcademicYears::startYear($started);
        $end = AcademicYears::startYear($completed);
        $current = AcademicYears::startYear(AcademicYears::current());

        if ($start !== null && $end !== null && $start > $end) {
            throw ValidationException::withMessages(['school_year_started' => 'The school year you started must be on or before the year you graduated or last attended.']);
        }
        if ($end === null || $status === null) {
            return;
        }
        if ($status === 'Currently attending' && $end < $current) {
            throw ValidationException::withMessages(['school_year_completed' => 'You are currently enrolled, so choose the current school year (' . AcademicYears::current() . ').']);
        }
        if (in_array($status, ['Graduated', 'Transferred', 'Not completed'], true) && $end > $current) {
            throw ValidationException::withMessages(['school_year_completed' => 'That school year has not started yet. Choose the year you graduated or last attended.']);
        }
    }
}
