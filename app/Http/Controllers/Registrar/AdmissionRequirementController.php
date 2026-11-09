<?php

namespace App\Http\Controllers\Registrar;

use App\Http\Controllers\Controller;
use App\Models\AdmissionRequirement;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Requirements are never deleted once created: uploads reference them.
 * Deactivating hides a requirement from new uploads while keeping every
 * submitted file and its review history.
 */
class AdmissionRequirementController extends Controller
{
    public function index()
    {
        $requirements = AdmissionRequirement::ordered()
            ->withCount([
                'documents as current_uploads_count' => fn ($query) => $query->whereNull('superseded_at'),
                'documents as awaiting_review_count' => fn ($query) => $query->whereNull('superseded_at')->where('status', 'submitted'),
            ])
            ->get();

        return view('registrar.admissions.requirements', compact('requirements'));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $base = Str::slug($data['name'], '_') ?: 'requirement';
        $code = Str::limit($base, 50, '');
        $suffix = 2;
        while (AdmissionRequirement::where('code', $code)->exists()) {
            $code = Str::limit($base, 46, '') . '_' . $suffix++;
        }

        $requirement = AdmissionRequirement::create($data + ['code' => $code]);
        AuditLog::record('admission.requirement.created', "Created admission requirement {$requirement->name}.", ['requirement_id' => $requirement->id]);

        return redirect()->route('registrar.admissions.requirements.index')->with('success', "{$requirement->name} was added.");
    }

    public function update(Request $request, AdmissionRequirement $requirement)
    {
        $requirement->update($this->validated($request));
        AuditLog::record('admission.requirement.updated', "Updated admission requirement {$requirement->name}.", ['requirement_id' => $requirement->id, 'changes' => array_keys($requirement->getChanges())]);

        return redirect()->route('registrar.admissions.requirements.index')->with('success', "{$requirement->name} was updated.");
    }

    public function toggle(AdmissionRequirement $requirement)
    {
        $requirement->update(['is_active' => !$requirement->is_active]);
        AuditLog::record('admission.requirement.' . ($requirement->is_active ? 'activated' : 'deactivated'), ($requirement->is_active ? 'Activated' : 'Deactivated') . " admission requirement {$requirement->name}.", ['requirement_id' => $requirement->id]);

        return redirect()->route('registrar.admissions.requirements.index')
            ->with('success', $requirement->name . ($requirement->is_active ? ' is now active.' : ' was deactivated. Files already submitted for it remain on record.'));
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:500'],
            'applicant_types' => ['nullable', 'array'],
            'applicant_types.*' => [Rule::in(array_keys(AdmissionRequirement::APPLICANT_TYPES))],
            'condition' => ['nullable', Rule::in(array_keys(AdmissionRequirement::CONDITIONS))],
            'allowed_extensions' => ['required', 'array', 'min:1'],
            'allowed_extensions.*' => [Rule::in(AdmissionRequirement::SUPPORTED_EXTENSIONS)],
            'max_size_mb' => ['required', 'numeric', 'min:0.1', 'max:' . AdmissionRequirement::MAX_SIZE_LIMIT_KB / 1024],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_required' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $types = array_values(array_unique($data['applicant_types'] ?? []));
        $extensions = array_values(array_unique($data['allowed_extensions']));
        // "jpg" and "jpeg" are the same format; accept both whenever one is chosen.
        if (array_intersect(['jpg', 'jpeg'], $extensions)) {
            $extensions = array_values(array_unique(array_merge($extensions, ['jpg', 'jpeg'])));
        }

        return [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            // Every type selected is the same as "all types".
            'applicant_types' => count($types) === 0 || count($types) === count(AdmissionRequirement::APPLICANT_TYPES) ? null : $types,
            'condition' => $data['condition'] ?? null,
            'allowed_extensions' => implode(',', $extensions),
            'max_size_kb' => (int) round($data['max_size_mb'] * 1024),
            'sort_order' => $data['sort_order'] ?? 0,
            'is_required' => $request->boolean('is_required'),
            'is_active' => $request->boolean('is_active'),
        ];
    }
}
