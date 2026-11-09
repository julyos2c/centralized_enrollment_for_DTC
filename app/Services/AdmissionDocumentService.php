<?php

namespace App\Services;

use App\Models\AdmissionRequirement;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class AdmissionDocumentService
{
    /**
     * Store one upload on the private disk and make it the current document
     * for its requirement. The previous upload is kept and marked superseded.
     * Must be called inside the caller's transaction when other writes depend on it.
     */
    public function store(Application $application, AdmissionRequirement $requirement, UploadedFile $file): ApplicationDocument
    {
        $extension = strtolower($file->extension() ?: $file->getClientOriginalExtension());
        // Random storage name: the client filename is display-only.
        $path = $file->storeAs("applications/{$application->id}", Str::uuid() . '.' . $extension, 'local');
        if (!$path) {
            throw new RuntimeException('The uploaded file could not be stored.');
        }

        try {
            return DB::transaction(function () use ($application, $requirement, $file, $path) {
                ApplicationDocument::where('application_id', $application->id)
                    ->whereNull('superseded_at')
                    ->where(function ($query) use ($requirement) {
                        $query->where('admission_requirement_id', $requirement->id)
                            ->orWhere(function ($legacy) use ($requirement) {
                                $legacy->whereNull('admission_requirement_id')->where('document_type', $requirement->code);
                            });
                    })
                    ->lockForUpdate()
                    ->update(['superseded_at' => now()]);

                return ApplicationDocument::create([
                    'application_id' => $application->id,
                    'admission_requirement_id' => $requirement->id,
                    'document_type' => $requirement->code,
                    'file_path' => $path,
                    'original_name' => Str::limit($file->getClientOriginalName(), 250, ''),
                    'mime_type' => $file->getMimeType(),
                    'size_bytes' => $file->getSize(),
                    'status' => 'submitted',
                ]);
            });
        } catch (\Throwable $exception) {
            // Do not leave an orphaned file when the record could not be saved.
            Storage::disk('local')->delete($path);
            throw $exception;
        }
    }

    /**
     * Record a staff decision on the current upload. Returns false when the
     * document was replaced or already decided the same way (stale page).
     */
    public function review(ApplicationDocument $document, User $reviewer, string $decision, ?string $remarks): bool
    {
        return DB::transaction(function () use ($document, $reviewer, $decision, $remarks) {
            $locked = ApplicationDocument::whereKey($document->id)->lockForUpdate()->first();
            if (!$locked || $locked->superseded_at !== null) {
                return false;
            }
            if ($locked->status === $decision && $locked->review_remarks === $remarks) {
                return false;
            }

            $locked->update([
                'status' => $decision,
                'review_remarks' => $decision === 'rejected' ? $remarks : null,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
            ]);
            $document->setRawAttributes($locked->getAttributes(), true);

            return true;
        });
    }
}
