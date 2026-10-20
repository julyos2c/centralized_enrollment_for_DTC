<?php

namespace Database\Seeders;

use App\Models\CourseSubject;
use App\Models\Program;
use App\Models\Setting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * Academic configuration from APPROVED institutional records only.
 *
 * Reads database/seeders/data/academic-configuration.json. When that file does
 * not exist this seeder does nothing — the application never invents an
 * academic term, program, or curriculum. See database/seeders/data/README.md
 * for the file format.
 *
 * Safety: the whole file is validated before anything is written, and it is
 * applied in one transaction. Existing programs, subjects, and term settings
 * are never renamed, updated, or deleted; differences are only reported.
 */
class AcademicConfigurationSeeder extends Seeder
{
    /** Overridable for tests; defaults to the approved data file. */
    public static ?string $path = null;

    public const SEMESTERS = ['1st', '2nd', 'Summer'];

    public function run(): void
    {
        $path = static::$path ?? database_path('seeders/data/academic-configuration.json');

        if (! is_file($path)) {
            $this->command?->info('Academic configuration: no approved data file (' . basename($path) . '); nothing changed.');
            return;
        }

        $data = json_decode((string) file_get_contents($path), true);
        if (! is_array($data)) {
            throw new RuntimeException('Academic configuration file is not valid JSON; nothing was changed.');
        }

        $this->validate($data);
        $report = DB::transaction(fn () => $this->apply($data));

        foreach ($report as $line) {
            $this->command?->line('  ' . $line);
        }
    }

    private function validate(array $data): void
    {
        $validator = Validator::make($data, [
            'approved_by' => ['required', 'string', 'max:255'],
            'approved_on' => ['required', 'date_format:Y-m-d'],
            'source' => ['required', 'string', 'max:500'],
            'term' => ['nullable', 'array'],
            'term.school_year' => ['required_with:term', 'string', 'regex:/^\d{4}-\d{4}$/'],
            'term.semester' => ['required_with:term', Rule::in(self::SEMESTERS)],
            'programs' => ['nullable', 'array'],
            'programs.*.code' => ['required', 'string', 'max:50', 'distinct'],
            'programs.*.name' => ['required', 'string', 'max:255'],
            'programs.*.subjects' => ['nullable', 'array'],
            'programs.*.subjects.*.subject_code' => ['required', 'string', 'max:50'],
            'programs.*.subjects.*.subject_name' => ['required', 'string', 'max:255'],
            'programs.*.subjects.*.units' => ['required', 'numeric', 'min:0.5', 'max:12'],
            'programs.*.subjects.*.year_level' => ['required', 'integer', 'between:1,5'],
            'programs.*.subjects.*.semester' => ['required', 'integer', Rule::in([1, 2, 3])],
        ]);

        $validator->after(function ($v) use ($data) {
            if (isset($data['term']['school_year'])
                && preg_match('/^(\d{4})-(\d{4})$/', $data['term']['school_year'], $m)
                && (int) $m[2] !== (int) $m[1] + 1) {
                $v->errors()->add('term.school_year', 'The school year must span consecutive years, e.g. 2026-2027.');
            }
            foreach ($data['programs'] ?? [] as $i => $program) {
                $codes = array_map(fn ($s) => strtolower((string) ($s['subject_code'] ?? '')), $program['subjects'] ?? []);
                if (count($codes) !== count(array_unique($codes))) {
                    $v->errors()->add("programs.$i.subjects", 'Subject codes must be unique within a program.');
                }
            }
        });

        if ($validator->fails()) {
            throw new RuntimeException("Academic configuration file is invalid; nothing was changed:\n- "
                . implode("\n- ", $validator->errors()->all()));
        }
    }

    private function apply(array $data): array
    {
        $report = ["Source: {$data['source']} (approved by {$data['approved_by']} on {$data['approved_on']})"];

        if (! empty($data['term'])) {
            foreach (['current_school_year' => $data['term']['school_year'], 'current_semester' => $data['term']['semester']] as $key => $value) {
                $existing = Setting::where('key', $key)->first();
                if (! $existing || trim((string) $existing->value) === '') {
                    Setting::updateOrCreate(['key' => $key], ['value' => $value]);
                    $report[] = "Set $key = $value";
                } elseif ($existing->value !== $value) {
                    $report[] = "Kept existing $key = {$existing->value} (file has $value; change it in Settings if intended)";
                }
            }
        }

        foreach ($data['programs'] ?? [] as $item) {
            $program = Program::firstOrCreate(['code' => $item['code']], ['name' => $item['name']]);
            $report[] = $program->wasRecentlyCreated
                ? "Created program {$program->code}"
                : "Program {$program->code} exists" . ($program->name !== $item['name'] ? " (kept name \"{$program->name}\")" : '');

            $added = 0;
            foreach ($item['subjects'] ?? [] as $s) {
                $subject = CourseSubject::where('program_id', $program->id)->where('subject_code', $s['subject_code'])->first();
                if ($subject) {
                    $differs = $subject->subject_name !== $s['subject_name']
                        || (float) $subject->units !== (float) $s['units']
                        || (int) $subject->year_level !== (int) $s['year_level']
                        || (int) $subject->semester !== (int) $s['semester'];
                    if ($differs) {
                        $report[] = "  Kept existing {$program->code} {$s['subject_code']} (differs from file; not overwritten)";
                    }
                    continue;
                }
                CourseSubject::create([
                    'program_id' => $program->id,
                    'subject_code' => $s['subject_code'],
                    'subject_name' => $s['subject_name'],
                    'units' => $s['units'],
                    'year_level' => $s['year_level'],
                    'semester' => $s['semester'],
                ]);
                $added++;
            }
            $report[] = "  {$program->code}: $added subject(s) added";
        }

        return $report;
    }
}
