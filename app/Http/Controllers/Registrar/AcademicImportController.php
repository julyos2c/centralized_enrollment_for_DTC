<?php

namespace App\Http\Controllers\Registrar;

use App\Http\Controllers\Controller;
use App\Models\AcademicImportBatch;
use App\Services\AcademicRecordImportService;
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
        return view('registrar.imports.show', compact('batch', 'rows'));
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
