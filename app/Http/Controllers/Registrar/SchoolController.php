<?php

namespace App\Http\Controllers\Registrar;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\School;
use App\Support\AddressLookup;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * School reference list used by the application form's school lookup.
 * Schools are never deleted (applications reference them); deactivating
 * hides a school from the lookup while keeping existing records intact.
 */
class SchoolController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['active', 'inactive', 'no_location'])],
        ]);

        $schools = School::query()
            ->when($filters['search'] ?? null, fn ($q, $term) => $q->search($term))
            ->when(($filters['status'] ?? null) === 'active', fn ($q) => $q->where('is_active', true))
            ->when(($filters['status'] ?? null) === 'inactive', fn ($q) => $q->where('is_active', false))
            ->when(($filters['status'] ?? null) === 'no_location', fn ($q) => $q->whereNull('city_code'))
            ->when(!isset($filters['search']), fn ($q) => $q->orderBy('name'))
            ->withCount('applications')
            ->paginate(\App\Support\TablePagination::perPage(25))
            ->withQueryString();

        return view('registrar.admissions.schools.index', [
            'schools' => $schools,
            'totals' => [
                'all' => School::count(),
                'active' => School::where('is_active', true)->count(),
                'no_location' => School::whereNull('city_code')->count(),
            ],
        ]);
    }

    public function create()
    {
        return view('registrar.admissions.schools.form', ['school' => new School(['is_active' => true]), 'lookup' => AddressLookup::config()]);
    }

    public function store(Request $request)
    {
        $school = School::create($this->validated($request) + ['source' => 'manual']);
        AuditLog::record('admission.school.created', "Added school {$school->name}.", ['school_id' => $school->id]);

        return redirect()->route('registrar.admissions.schools.index', ['search' => $school->name])
            ->with('success', "{$school->name} was added to the school list.");
    }

    public function edit(School $school)
    {
        return view('registrar.admissions.schools.form', ['school' => $school, 'lookup' => AddressLookup::config()]);
    }

    public function update(Request $request, School $school)
    {
        $school->update($this->validated($request, $school));
        AuditLog::record('admission.school.updated', "Updated school {$school->name}.", ['school_id' => $school->id, 'changes' => array_keys($school->getChanges())]);

        return redirect()->route('registrar.admissions.schools.edit', $school)->with('success', 'School details saved.');
    }

    public function toggle(School $school)
    {
        $school->update(['is_active' => !$school->is_active]);
        AuditLog::record('admission.school.' . ($school->is_active ? 'activated' : 'deactivated'), ($school->is_active ? 'Activated' : 'Deactivated') . " school {$school->name}.", ['school_id' => $school->id]);

        return back()->with('success', $school->name . ($school->is_active ? ' is shown in the school lookup again.' : ' is hidden from the school lookup. Existing applications keep it.'));
    }

    private function validated(Request $request, ?School $school = null): array
    {
        $data = $request->validate(array_merge([
            'name' => ['required', 'string', 'max:255'],
            'deped_school_id' => ['nullable', 'regex:/^\d{6}$/', Rule::unique('schools', 'deped_school_id')->ignore($school?->id)],
            'sector' => ['nullable', Rule::in(array_keys(School::SECTORS))],
            'barangay' => ['nullable', 'string', 'max:255'],
            'street_address' => ['nullable', 'string', 'max:255'],
            'offerings' => ['nullable', 'array'],
            'offerings.*' => [Rule::in(\App\Services\Schools\SchoolImporter::OFFERINGS)],
        ], AddressLookup::rules($request->all(), 'region_code', 'province_code', 'city_code')), [
            'deped_school_id.regex' => 'A DepEd School ID has 6 digits.',
            'deped_school_id.unique' => 'Another school already uses this DepEd School ID.',
        ]);

        return [
            'name' => trim(preg_replace('/\s+/', ' ', $data['name'])),
            'deped_school_id' => $data['deped_school_id'] ?? null,
            'sector' => $data['sector'] ?? null,
            'region_code' => $data['region_code'] ?? null,
            'province_code' => $data['province_code'] ?? null,
            'city_code' => $data['city_code'] ?? null,
            'barangay' => $data['barangay'] ?? null,
            'street_address' => $data['street_address'] ?? null,
            'offerings' => array_values($data['offerings'] ?? []) ?: null,
            'is_active' => $request->boolean('is_active'),
        ];
    }
}
