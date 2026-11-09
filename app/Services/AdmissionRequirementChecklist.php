<?php

namespace App\Services;

use App\Models\AdmissionRequirement;
use App\Models\Application;
use Illuminate\Support\Collection;

/**
 * Builds the requirement checklist for one application: each applicable
 * active requirement paired with its latest upload and review status.
 * Uploads for requirements that were later deactivated stay visible to
 * staff so nothing already submitted disappears from review.
 */
class AdmissionRequirementChecklist
{
    /** Active requirements for a given applicant type, in display order. */
    public function requirementsFor(?string $applicantType, ?string $maritalStatus = null): Collection
    {
        return AdmissionRequirement::active()->ordered()->get()
            ->filter(fn (AdmissionRequirement $requirement) => $requirement->appliesTo($applicantType, $maritalStatus))
            ->values();
    }

    /**
     * @return Collection<int, array{requirement: AdmissionRequirement, document: ?\App\Models\ApplicationDocument, state: string, history: Collection}>
     */
    public function forApplication(Application $application, bool $includeInactiveUploads = false): Collection
    {
        $application->loadMissing(['documents.reviewer']);
        $documents = $application->documents->sortByDesc('id');

        $requirements = $this->requirementsFor($application->academic_status, $application->marital_status);

        if ($includeInactiveUploads) {
            $listedIds = $requirements->pluck('id')->all();
            $extraIds = $documents->pluck('admission_requirement_id')->filter()
                ->reject(fn ($id) => in_array($id, $listedIds, true))->unique();
            if ($extraIds->isNotEmpty()) {
                $requirements = $requirements->concat(AdmissionRequirement::whereIn('id', $extraIds)->ordered()->get());
            }
        }

        return $requirements->map(function (AdmissionRequirement $requirement) use ($documents) {
            $uploads = $documents->filter(fn ($doc) => $doc->admission_requirement_id === $requirement->id
                || ($doc->admission_requirement_id === null && $doc->document_type === $requirement->code));
            $current = $uploads->first(fn ($doc) => $doc->superseded_at === null);

            return [
                'requirement' => $requirement,
                'document' => $current,
                'state' => $current ? $current->status : 'missing',
                'history' => $uploads->reject(fn ($doc) => $current && $doc->id === $current->id)->values(),
            ];
        })->values();
    }

    /**
     * Summary counts for progress indicators.
     */
    public function summary(Collection $checklist): array
    {
        $required = $checklist->filter(fn ($item) => $item['requirement']->is_required);

        return [
            'total' => $checklist->count(),
            'required' => $required->count(),
            'uploaded' => $checklist->where('state', '!=', 'missing')->count(),
            'approved' => $checklist->where('state', 'approved')->count(),
            'rejected' => $checklist->where('state', 'rejected')->count(),
            'awaiting_review' => $checklist->where('state', 'submitted')->count(),
            'required_missing' => $required->where('state', 'missing')->count(),
            'required_approved' => $required->where('state', 'approved')->count(),
            'required_complete' => $required->every(fn ($item) => $item['state'] === 'approved'),
            'missing_names' => $required->where('state', 'missing')->map(fn ($item) => $item['requirement']->name)->values()->all(),
            'rejected_names' => $checklist->where('state', 'rejected')->map(fn ($item) => $item['requirement']->name)->values()->all(),
        ];
    }
}
