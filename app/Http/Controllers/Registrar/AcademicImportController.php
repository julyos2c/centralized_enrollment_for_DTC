<?php

namespace App\Http\Controllers\Registrar;

use App\Http\Controllers\Controller;
use App\Models\AcademicImportBatch;
use App\Models\StudentProfile;
use App\Services\AcademicRecordImportService;
use App\Services\PortalAccountProvisioningService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AcademicImportController extends Controller
{
    public function index()
    {
        return view('registrar.imports.index', ['batches' => AcademicImportBatch::with('importer')->latest()->paginate(20)]);
    }

    public function template(string $type, AcademicRecordImportService $service): StreamedResponse
    {
        abort_unless(in_array($type, ['students', 'alumni'], true), 404);
        return response()->streamDownload(function () use ($service, $type) {
            $out = fopen('php://output', 'w'); fputcsv($out, $service->headers($type)); fclose($out);
        }, $type.'-template.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function upload(Request $request, AcademicRecordImportService $service)
    {
        $request->validate(['import_type' => ['required', 'in:students,alumni'], 'file' => ['required', 'file', 'max:'.AcademicRecordImportService::maxKilobytes()]]);
        $batch = $service->upload($request->file('file'), $request->string('import_type')->toString(), $request->user()->id);
        return redirect()->route('registrar.imports.show', $batch)->with('success', 'Preview is ready. Review the rows before confirming.');
    }

    public function show(AcademicImportBatch $batch, AcademicRecordImportService $service)
    {
        $this->authorizeBatch($batch);
        $rows = $batch->status === 'previewed' ? $service->previewRows($batch) : [];
        $importedProfiles = $batch->status === 'completed' || $batch->status === 'partial'
            ? $batch->studentProfiles()->with(['portalInvitation', 'user'])->orderBy('last_name')->orderBy('student_number')->get()
            : collect();
        $emails = $importedProfiles->pluck('email')->filter()->unique()->values();
        $duplicateEmails = $emails->isEmpty() ? collect() : StudentProfile::query()
            ->select('email')->selectRaw('COUNT(*) as records_count')->whereIn('email', $emails)
            ->groupBy('email')->havingRaw('COUNT(*) > 1')->pluck('email')
            ->mapWithKeys(static fn ($email) => [mb_strtolower($email) => true]);
        $accountEmails = $emails->isEmpty() ? collect() : \App\Models\User::whereIn('email', $emails)->pluck('email')
            ->mapWithKeys(static fn ($email) => [mb_strtolower($email) => true]);
        return view('registrar.imports.show', compact('batch', 'rows', 'importedProfiles', 'duplicateEmails', 'accountEmails'));
    }

    public function provisionSelected(Request $request, AcademicImportBatch $batch, PortalAccountProvisioningService $provisioning)
    {
        $this->authorizeBatch($batch);
        abort_unless(in_array($batch->status, ['completed', 'partial'], true), 409);
        $data = $request->validate([
            'confirm' => ['required', 'accepted'],
            'role' => ['prohibited'],
            'student_profile_ids' => ['required', 'array', 'min:1', 'max:100'],
            'student_profile_ids.*' => ['required', 'integer', 'distinct'],
        ]);
        $profiles = $batch->studentProfiles()->whereIn('student_profiles.id', $data['student_profile_ids'])->get();
        abort_unless($profiles->count() === count($data['student_profile_ids']), 422, 'One or more selected records are not part of this import.');

        $counts = ['sent' => 0, 'failed' => 0, 'skipped' => 0];
        foreach ($profiles as $profile) {
            if (! filter_var($profile->email, FILTER_VALIDATE_EMAIL) || ! $profile->student_number
                || ! $profile->first_name || ! $profile->last_name
                || StudentProfile::where('email', $profile->email)->where('id', '!=', $profile->id)->exists()) {
                $counts['skipped']++;
                continue;
            }
            try {
                $existingInvitation = $profile->portalInvitation;
                $staleSending = $existingInvitation && $existingInvitation->status === 'sending'
                    && (! $existingInvitation->updated_at || $existingInvitation->updated_at->lte(
                        now()->subMinutes(max(1, (int) config('portal_accounts.sending_lease_minutes', 15)))
                    ));
                if ($existingInvitation && (in_array($existingInvitation->status, ['delivery_failed', 'expired', 'revoked'], true) || $staleSending)
                    && $profile->user && $profile->user->status === 'pending') {
                    $invitation = $provisioning->resend($existingInvitation, $request->user());
                } else {
                    $invitation = $provisioning->provision($profile, $request->user());
                }
                $counts[$invitation->status === 'pending' ? 'sent' : 'failed']++;
            } catch (\Illuminate\Validation\ValidationException $exception) {
                $counts['skipped']++;
            }
        }

        \App\Models\AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'portal_account.bulk_provisioning_completed',
            'description' => 'Academic import portal provisioning batch processed.',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'metadata' => ['batch_id' => $batch->id, 'selected' => $profiles->count()] + $counts,
        ]);

        return redirect()->route('registrar.imports.show', $batch)->with('provisioning_results', $counts);
    }

    public function commit(Request $request, AcademicImportBatch $batch, AcademicRecordImportService $service)
    {
        $this->authorizeBatch($batch);
        $request->validate(['confirm' => ['accepted']]);
        $service->commit($batch, $request->user()->id);
        return redirect()->route('registrar.imports.show', $batch)->with('success', 'Import processing completed.');
    }

    public function cancel(Request $request, AcademicImportBatch $batch)
    {
        $this->authorizeBatch($batch);
        abort_unless($batch->status === 'previewed', 409);
        Storage::disk('local')->delete($batch->source_path);
        $batch->update(['status' => 'cancelled', 'source_path' => '', 'completed_at' => now()]);
        \App\Models\AuditLog::create(['user_id' => $request->user()->id, 'action' => 'academic_import.cancelled', 'description' => 'Cancelled academic import '.$batch->reference, 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent(), 'metadata' => ['reference' => $batch->reference]]);
        return redirect()->route('registrar.imports.show', $batch)->with('success', 'Preview cancelled. No academic records were changed.');
    }

    public function report(AcademicImportBatch $batch)
    {
        $this->authorizeBatch($batch);
        abort_unless($batch->report_path && Storage::disk('local')->exists($batch->report_path), 404);
        $issues = json_decode(Storage::disk('local')->get($batch->report_path), true) ?: [];
        return response()->streamDownload(function () use ($issues) {
            $out = fopen('php://output', 'w'); fputcsv($out, ['Row', 'Issue']);
            foreach ($issues as $issue) {
                $message = (string) $issue['message'];
                if (preg_match('/^[=+\-@]/', $message)) $message = "'".$message;
                fputcsv($out, [$issue['row'], $message]);
            }
            fclose($out);
        }, 'import-'.$batch->reference.'-issues.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function authorizeBatch(AcademicImportBatch $batch): void
    {
        abort_unless($batch->imported_by === auth()->id() || auth()->user()->hasAnyRole(['registrar', 'admin']), 403);
    }
}
