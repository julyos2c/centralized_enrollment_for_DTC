<?php

namespace App\Services;

use App\Models\AcademicImportBatch;
use App\Models\AuditLog;
use App\Models\Program;
use App\Models\StudentProfile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AcademicRecordImportService
{
    public static function maxKilobytes(): int { return max(1, (int) config('academic_imports.max_kilobytes', 5120)); }
    public static function maxRows(): int { return max(1, (int) config('academic_imports.max_rows', 1000)); }

    public function headers(string $type): array
    {
        return $type === 'alumni'
            ? ['Student Number', 'First Name', 'Middle Name', 'Last Name', 'Suffix', 'Email', 'Program Completed', 'Graduation Year', 'Graduation Date']
            : ['Student Number', 'First Name', 'Middle Name', 'Last Name', 'Suffix', 'Email', 'Program', 'Year Level', 'School Year'];
    }

    public function upload(UploadedFile $file, string $type, int $actorId): AcademicImportBatch
    {
        if (! in_array($type, ['students', 'alumni'], true)) {
            throw ValidationException::withMessages(['import_type' => 'Select Current Students or Alumni.']);
        }
        if (strtolower($file->getClientOriginalExtension()) !== 'csv' || $file->getSize() > self::maxKilobytes() * 1024 || ! in_array($file->getMimeType(), ['text/plain', 'text/csv', 'application/csv', 'application/vnd.ms-excel'], true)) {
            throw ValidationException::withMessages(['file' => 'Upload a CSV file no larger than 5 MB. XLSX is not available in this installation.']);
        }

        $path = $file->storeAs('private/academic-imports', Str::uuid().'.csv', 'local');
        try {
            $rows = $this->parse(Storage::disk('local')->path($path), $type);
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);
            throw $e;
        }
        $summary = $this->validateRows($rows, $type);
        $reportPath = 'private/academic-imports/'.Str::uuid().'.json';
        Storage::disk('local')->put($reportPath, json_encode($summary['issues'], JSON_UNESCAPED_SLASHES));

        $batch = AcademicImportBatch::create([
            'reference' => strtoupper(Str::random(10)),
            'import_type' => $type,
            'status' => 'previewed',
            'imported_by' => $actorId,
            'source_path' => $path,
            'source_sha256' => hash_file('sha256', Storage::disk('local')->path($path)),
            'report_path' => $reportPath,
            'total_rows' => count($rows),
            'valid_rows' => $summary['valid'],
            'invalid_rows' => $summary['invalid'],
            'existing_rows' => $summary['existing'],
            'expires_at' => now()->addHours(max(1, (int) config('academic_imports.preview_ttl_hours', 24))),
            'previewed_at' => now(),
        ]);
        $this->audit($actorId, 'academic_import.previewed', $batch, ['total' => count($rows), 'valid' => $summary['valid'], 'invalid' => $summary['invalid'], 'existing' => $summary['existing']]);
        return $batch;
    }

    public function previewRows(AcademicImportBatch $batch): array
    {
        $rows = $this->parse(Storage::disk('local')->path($batch->source_path), $batch->import_type);
        return $this->validateRows($rows, $batch->import_type)['rows'];
    }

    public function commit(AcademicImportBatch $batch, int $actorId): AcademicImportBatch
    {
        return DB::transaction(function () use ($batch, $actorId) {
            $batch = AcademicImportBatch::whereKey($batch->id)->lockForUpdate()->firstOrFail();
            abort_unless($batch->imported_by === $actorId || auth()->user()->hasAnyRole(['registrar', 'admin']), 403);
            abort_unless($batch->status === 'previewed' && $batch->expires_at && $batch->expires_at->isFuture(), 409, 'This preview has expired or was already processed.');
            $source = Storage::disk('local')->path($batch->source_path);
            abort_unless(is_file($source) && hash_file('sha256', $source) === $batch->source_sha256, 409, 'The uploaded file is no longer available. Upload it again.');

            $rows = $this->parse($source, $batch->import_type);
            $check = $this->validateRows($rows, $batch->import_type);
            $created = $skipped = $failed = 0;
            foreach ($check['rows'] as $entry) {
                if (! $entry['valid'] || $entry['existing']) { $skipped++; continue; }
                try {
                    $profile = StudentProfile::create($entry['data']);
                    $batch->studentProfiles()->attach($profile->id);
                    $created++;
                } catch (\Illuminate\Database\QueryException $e) {
                    // A concurrent import may claim the unique student number after preview.
                    if (StudentProfile::where('student_number', $entry['data']['student_number'])->exists()) {
                        $skipped++;
                        continue;
                    }
                    throw $e;
                }
            }
            $sourcePath = $batch->source_path;
            $batch->update([
                'status' => $failed ? 'partial' : 'completed',
                'created_rows' => $created,
                'skipped_rows' => $skipped,
                'failed_rows' => $failed,
                'completed_at' => now(),
                'source_path' => '',
            ]);
            $this->audit($actorId, 'academic_import.completed', $batch, ['created' => $created, 'skipped' => $skipped, 'failed' => $failed]);
            Storage::disk('local')->delete($sourcePath);
            return $batch->fresh();
        });
    }

    private function parse(string $path, string $type): array
    {
        $handle = @fopen($path, 'rb');
        if (! $handle) throw ValidationException::withMessages(['file' => 'The CSV file could not be read.']);
        $header = fgetcsv($handle);
        if (isset($header[0])) $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
        if (! is_array($header) || $header !== $this->headers($type)) { fclose($handle); throw ValidationException::withMessages(['file' => 'The CSV header does not match the selected template.']); }
        $rows = [];
        while (($values = fgetcsv($handle)) !== false) {
            if (count($values) === 1 && trim((string) $values[0]) === '') continue;
            if (count($values) !== count($header)) { $rows[] = ['_malformed' => true]; }
            else $rows[] = array_combine($header, array_map(static fn ($value) => trim((string) $value), $values));
            if (count($rows) > self::maxRows()) { fclose($handle); throw ValidationException::withMessages(['file' => 'The file exceeds the '.number_format(self::maxRows()).' row limit.']); }
        }
        fclose($handle);
        if (! $rows) throw ValidationException::withMessages(['file' => 'The CSV contains no data rows.']);
        return $rows;
    }

    private function validateRows(array $rows, string $type): array
    {
        $valid = $invalid = $existing = 0; $seen = []; $issues = []; $result = [];
        foreach ($rows as $index => $row) {
            $line = $index + 2; $errors = [];
            if (isset($row['_malformed'])) $errors[] = 'Row has an incorrect number of columns.';
            $number = $row['Student Number'] ?? '';
            $first = $row['First Name'] ?? '';
            $last = $row['Last Name'] ?? '';
            if ($number === '') $errors[] = 'Student Number is required.';
            if ($first === '') $errors[] = 'First Name is required.';
            if ($last === '') $errors[] = 'Last Name is required.';
            if (isset($seen[$number]) && $number !== '') $errors[] = 'Student Number is duplicated in this file.';
            if ($number !== '') $seen[$number] = true;
            $email = $row['Email'] ?? '';
            if ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email is not valid.';
            $programLabel = $row[$type === 'alumni' ? 'Program Completed' : 'Program'] ?? '';
            $program = null;
            if ($programLabel !== '') {
                $matches = Program::where('code', $programLabel)->orWhere('name', $programLabel)->get();
                if ($matches->count() !== 1) $errors[] = 'Program must exactly match one program name or code.';
                else $program = $matches->first();
            }
            $yearLevel = $row['Year Level'] ?? '';
            if ($yearLevel !== '' && (! ctype_digit($yearLevel) || (int) $yearLevel < 1 || (int) $yearLevel > 8)) $errors[] = 'Year Level must be between 1 and 8.';
            $schoolYear = $row['School Year'] ?? '';
            if ($schoolYear !== '' && (! preg_match('/^(\d{4})-(\d{4})$/', $schoolYear, $period) || (int) $period[2] !== (int) $period[1] + 1)) $errors[] = 'School Year must use consecutive years, such as 2025-2026.';
            $gradYear = $row['Graduation Year'] ?? '';
            if ($type === 'alumni' && $gradYear !== '' && (! ctype_digit($gradYear) || (int) $gradYear < 1900 || (int) $gradYear > ((int) date('Y') + 1))) $errors[] = 'Graduation Year is invalid.';
            $gradDate = $row['Graduation Date'] ?? '';
            if ($gradDate !== '' && (! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $gradDate, $dateParts) || ! checkdate((int) $dateParts[2], (int) $dateParts[3], (int) $dateParts[1]))) $errors[] = 'Graduation Date must be a real date in YYYY-MM-DD format.';
            $isExisting = $number !== '' && StudentProfile::where('student_number', $number)->exists();
            if ($isExisting) $existing++;
            if ($errors) { $invalid++; foreach ($errors as $error) $issues[] = ['row' => $line, 'message' => $error]; }
            else $valid++;
            $result[] = [
                'line' => $line, 'valid' => ! $errors, 'existing' => $isExisting, 'errors' => $errors,
                'data' => [
                    'user_id' => null, 'student_number' => $number, 'admission_type' => 'imported',
                    'first_name' => $first, 'middle_name' => $row['Middle Name'] ?? null, 'last_name' => $last,
                    'suffix' => $row['Suffix'] ?? null, 'email' => $email ?: null, 'program_id' => $program ? $program->id : null,
                    'year_level' => $yearLevel !== '' ? (int) $yearLevel : null,
                    'enrolled_ay' => ($row['School Year'] ?? '') ?: null,
                    'graduation_year' => $gradYear !== '' ? (int) $gradYear : null,
                    'graduation_date' => $gradDate ?: null,
                    'academic_status' => $type === 'alumni' ? 'alumni' : 'active',
                ],
            ];
        }
        return compact('valid', 'invalid', 'existing', 'issues') + ['rows' => $result];
    }

    private function audit(int $actorId, string $action, AcademicImportBatch $batch, array $counts): void
    {
        AuditLog::create(['user_id' => $actorId, 'action' => $action, 'description' => 'Academic import '.$batch->reference, 'ip_address' => request()->ip(), 'user_agent' => request()->userAgent(), 'metadata' => ['reference' => $batch->reference] + $counts]);
    }
}
