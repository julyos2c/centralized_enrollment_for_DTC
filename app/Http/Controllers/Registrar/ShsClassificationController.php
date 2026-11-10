<?php

namespace App\Http\Controllers\Registrar;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ShsClassification;
use App\Models\ShsCurriculum;
use App\Services\Schools\SchoolImporter;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * SHS curricula and their tracks / strands (or elective clusters) offered
 * on the application form. Nothing is deleted: applications reference
 * these rows, so deactivating hides an option from new applicants only.
 */
class ShsClassificationController extends Controller
{
    public function index()
    {
        $curricula = ShsCurriculum::orderBy('sort_order')
            ->with(['classifications' => fn ($q) => $q->withCount('applications')])
            ->withCount('applications')
            ->get();

        return view('registrar.admissions.shs', ['curricula' => $curricula, 'offeringKeys' => SchoolImporter::OFFERINGS]);
    }

    public function storeCurriculum(Request $request)
    {
        $data = $this->validatedCurriculum($request);
        $base = Str::limit(Str::slug($data['name'], '_'), 34, '') ?: 'curriculum';
        $code = $base;
        for ($i = 2; ShsCurriculum::where('code', $code)->exists(); $i++) {
            $code = $base . '_' . $i;
        }
        $curriculum = ShsCurriculum::create($data + ['code' => $code]);
        AuditLog::record('admission.shs_curriculum.created', "Added SHS curriculum {$curriculum->name}.", ['curriculum_id' => $curriculum->id]);

        return redirect()->route('registrar.admissions.shs.index')->with('success', "{$curriculum->name} was added.");
    }

    public function updateCurriculum(Request $request, ShsCurriculum $curriculum)
    {
        $curriculum->update($this->validatedCurriculum($request));
        AuditLog::record('admission.shs_curriculum.updated', "Updated SHS curriculum {$curriculum->name}.", ['curriculum_id' => $curriculum->id]);

        return redirect()->route('registrar.admissions.shs.index')->with('success', "{$curriculum->name} was updated.");
    }

    public function storeClassification(Request $request, ShsCurriculum $curriculum)
    {
        $data = $this->validatedClassification($request);
        $base = Str::upper(Str::limit(Str::slug($data['name'], '-'), 30, '')) ?: 'OPTION';
        $code = $base;
        for ($i = 2; $curriculum->classifications()->where('code', $code)->exists(); $i++) {
            $code = $base . '-' . $i;
        }
        $classification = $curriculum->classifications()->create($data + ['code' => $code]);
        AuditLog::record('admission.shs_classification.created', "Added {$classification->label()} to {$curriculum->name}.", ['classification_id' => $classification->id]);

        return redirect()->route('registrar.admissions.shs.index')->with('success', "{$classification->name} was added.");
    }

    public function updateClassification(Request $request, ShsClassification $classification)
    {
        $classification->update($this->validatedClassification($request));
        AuditLog::record('admission.shs_classification.updated', "Updated {$classification->label()}.", ['classification_id' => $classification->id]);

        return redirect()->route('registrar.admissions.shs.index')->with('success', "{$classification->name} was updated.");
    }

    private function validatedCurriculum(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:500'],
            'first_graduation_year' => ['nullable', 'integer', 'between:1990,2100'],
            'last_graduation_year' => ['nullable', 'integer', 'between:1990,2100', 'gte:first_graduation_year'],
            'choice_label' => ['required', Rule::in(['Strand', 'Elective cluster', 'Specialization'])],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ], ['last_graduation_year.gte' => 'The last graduation year cannot be before the first.']);

        return $data + ['sort_order' => $data['sort_order'] ?? 0, 'is_active' => $request->boolean('is_active')];
    }

    private function validatedClassification(Request $request): array
    {
        $data = $request->validate([
            'track' => ['required', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:255'],
            'offering_key' => ['nullable', Rule::in(SchoolImporter::OFFERINGS)],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);

        return $data + ['sort_order' => $data['sort_order'] ?? 0, 'is_active' => $request->boolean('is_active')];
    }
}
