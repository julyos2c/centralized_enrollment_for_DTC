<?php

namespace App\Http\Controllers\Registrar;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\AuditLog;
use App\Services\AdmissionDocumentService;
use App\Services\AdmissionNotifier;
use App\Services\AdmissionRequirementChecklist;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ApplicationDocumentReviewController extends Controller
{
    public function __construct(
        private AdmissionDocumentService $documents,
        private AdmissionNotifier $notifier,
        private AdmissionRequirementChecklist $checklist
    ) {
    }

    public function review(Request $request, Application $application, ApplicationDocument $document)
    {
        abort_unless($document->application_id === $application->id, 404);
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approved', 'rejected'])],
            'remarks' => ['nullable', 'required_if:decision,rejected', 'string', 'max:500'],
        ], ['remarks.required_if' => 'Explain why the document is rejected so the applicant can correct it.']);

        if (in_array($application->status, ['rejected', 'cancelled'], true)) {
            return back()->with('error', 'Documents of a closed application can no longer be reviewed.');
        }

        $wasComplete = $this->requiredComplete($application);
        if (!$this->documents->review($document, $request->user(), $data['decision'], $data['remarks'] ?? null)) {
            return back()->with('warning', 'This document was replaced or already has that decision. The page was refreshed with the latest upload.');
        }

        $document->loadMissing('requirement');
        $name = $document->requirement->name ?? ucwords(str_replace('_', ' ', $document->document_type));
        AuditLog::record('admission.document.' . $data['decision'], ucfirst($data['decision']) . " {$name} for application {$application->reference_no}.", ['application_id' => $application->id, 'document_id' => $document->id]);

        $application->unsetRelation('documents');
        $application->load('user');
        if ($data['decision'] === 'rejected') {
            $this->notifier->applicant($application, 'Document needs correction',
                "Your {$name} was not accepted. Reason: {$data['remarks']} Please upload a corrected copy in your admission portal.", 'warning');
        } elseif (!$wasComplete && $this->requiredComplete($application)) {
            // One email when every required document is approved, not one per document.
            $this->notifier->applicant($application, 'Required documents approved',
                'All of your required admission documents have been reviewed and approved.', 'success');
        }

        return redirect()->to(route('registrar.applications.show', $application) . '#requirements')
            ->with('success', "{$name} marked as {$data['decision']}.");
    }

    /** Approve every current upload that is still awaiting review. */
    public function approvePending(Request $request, Application $application)
    {
        if (in_array($application->status, ['rejected', 'cancelled'], true)) {
            return back()->with('error', 'Documents of a closed application can no longer be reviewed.');
        }

        $wasComplete = $this->requiredComplete($application);
        $count = 0;
        foreach ($application->currentDocuments()->where('status', 'submitted')->get() as $document) {
            $count += $this->documents->review($document, $request->user(), 'approved', null) ? 1 : 0;
        }
        if ($count === 0) {
            return back()->with('warning', 'There are no documents awaiting review.');
        }
        AuditLog::record('admission.document.bulk_approved', "Approved {$count} document(s) for application {$application->reference_no}.", ['application_id' => $application->id]);

        $application->unsetRelation('documents');
        $application->load('user');
        if (!$wasComplete && $this->requiredComplete($application)) {
            $this->notifier->applicant($application, 'Required documents approved',
                'All of your required admission documents have been reviewed and approved.', 'success');
        }

        return redirect()->to(route('registrar.applications.show', $application) . '#requirements')
            ->with('success', "{$count} document(s) approved.");
    }

    private function requiredComplete(Application $application): bool
    {
        $summary = $this->checklist->summary($this->checklist->forApplication($application->fresh()));

        return $summary['required'] > 0 && $summary['required_complete'];
    }
}
