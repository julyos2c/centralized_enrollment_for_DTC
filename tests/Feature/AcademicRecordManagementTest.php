<?php

namespace Tests\Feature;

use App\Models\StudentProfile;
use App\Models\Enrollment;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\SeedsRoles;
use Tests\TestCase;

class AcademicRecordManagementTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    private function staff(string $role)
    {
        return $this->makeUserWithRole($role);
    }

    private function recordPayload(): array
    {
        return ['student_number' => 'GAP-1001', 'first_name' => 'Ana', 'last_name' => 'Santos',
            'academic_status' => 'active', 'email' => 'ana@example.test', 'enrolled_ay' => '2025-2026'];
    }

    public function test_school_year_order_is_enforced_on_creation_and_update(): void
    {
        $this->actingAs($this->staff('registrar'));
        $payload = $this->recordPayload();
        foreach (['2026-2025', '2026-2026'] as $year) {
            $this->post(route('registrar.students.store'), array_replace($payload, ['enrolled_ay' => $year]))
                ->assertSessionHasErrors('enrolled_ay');
        }
        $this->assertDatabaseMissing('student_profiles', ['student_number' => 'GAP-1001']);
        $this->post(route('registrar.students.store'), $payload)->assertSessionHasNoErrors();
        $record = StudentProfile::where('student_number', 'GAP-1001')->firstOrFail();
        $this->put(route('registrar.students.update', $record), array_replace($payload, ['enrolled_ay' => '2027-2026']))
            ->assertSessionHasErrors('enrolled_ay');
        $this->assertSame('2025-2026', $record->fresh()->enrolled_ay);
    }

    public function test_year_level_accepts_only_optional_whole_numbers_within_supported_range(): void
    {
        $this->actingAs($this->staff('registrar'));
        $payload = $this->recordPayload();
        foreach (['0', '5', '8', '1.5', 'first'] as $value) {
            $this->post(route('registrar.students.store'), array_replace($payload, [
                'student_number' => 'YEAR-'.str_replace(['.', ' '], '-', $value),
                'year_level' => $value,
            ]))->assertSessionHasErrors('year_level');
        }
        $this->assertSame(0, StudentProfile::where('student_number', 'like', 'YEAR-%')->count());

        foreach ([null, '1', '4'] as $index => $value) {
            $this->post(route('registrar.students.store'), array_replace($payload, [
                'student_number' => 'VALID-YEAR-'.$index,
                'year_level' => $value,
            ]))->assertSessionHasNoErrors();
        }
        $this->assertDatabaseHas('student_profiles', ['student_number' => 'VALID-YEAR-0', 'year_level' => null]);
        $this->assertDatabaseHas('student_profiles', ['student_number' => 'VALID-YEAR-1', 'year_level' => 1]);
        $this->assertDatabaseHas('student_profiles', ['student_number' => 'VALID-YEAR-2', 'year_level' => 4]);
    }

    public function test_stale_route_model_cannot_bypass_new_account_link_restrictions(): void
    {
        $this->actingAs($this->staff('registrar'));
        $payload = $this->recordPayload();
        $stale = StudentProfile::create($payload);
        $owner = $this->makeUserWithRole('student');
        StudentProfile::whereKey($stale->id)->update(['user_id' => $owner->id]);
        \Illuminate\Support\Facades\Route::bind('student', fn () => $stale);
        foreach ([['email', 'other@example.test'], ['academic_status', 'alumni']] as [$field, $value]) {
            $this->put(route('registrar.students.update', $stale), array_replace($payload, [$field => $value]))
                ->assertSessionHasErrors($field);
        }
        $record = $stale->fresh();
        $this->assertSame($owner->id, $record->user_id);
        $this->assertSame('active', $record->academic_status);
        $this->assertSame('ana@example.test', $record->email);
    }

    public function test_audit_failure_rolls_back_record_creation_and_update(): void
    {
        $this->actingAs($this->staff('registrar'));
        $this->withoutExceptionHandling();
        $payload = $this->recordPayload();
        $record = StudentProfile::create(array_replace($payload, ['student_number' => 'EXISTING-1001']));
        $event = 'eloquent.creating: '.\App\Models\AuditLog::class;
        \Illuminate\Support\Facades\Event::listen($event, function ($audit) {
            if (str_starts_with($audit->action, 'academic_record.')) throw new \RuntimeException('Simulated audit failure');
        });
        try {
            foreach (['create', 'update'] as $operation) {
                try {
                    if ($operation === 'create') $this->post(route('registrar.students.store'), $payload);
                    else $this->put(route('registrar.students.update', $record), array_replace($payload, ['student_number' => $record->student_number, 'first_name' => 'Changed']));
                    $this->fail('Expected audit failure');
                } catch (\RuntimeException $exception) {
                    $this->assertSame('Simulated audit failure', $exception->getMessage());
                }
            }
        } finally {
            \Illuminate\Support\Facades\Event::forget($event);
        }
        $this->assertDatabaseMissing('student_profiles', ['student_number' => 'GAP-1001']);
        $this->assertSame('Ana', $record->fresh()->first_name);
        $this->assertSame(0, \App\Models\AuditLog::where('action', 'like', 'academic_record.%')->count());
    }

    public function test_record_type_and_academic_status_must_agree(): void
    {
        $this->actingAs($this->staff('registrar'));
        foreach ([['alumni', 'active'], ['students', 'alumni']] as [$type, $status]) {
            $this->post(route('registrar.students.store'), [
                'student_number' => 'TYPE-CHECK', 'first_name' => 'Ana', 'last_name' => 'Santos',
                'record_type' => $type, 'academic_status' => $status,
            ])->assertSessionHasErrors('academic_status');
        }
        $this->assertDatabaseMissing('student_profiles', ['student_number' => 'TYPE-CHECK']);
    }

    public function test_alumni_graduation_fields_are_optional_but_must_agree_when_both_are_provided(): void
    {
        $this->actingAs($this->staff('registrar'));
        $payload = ['student_number' => 'ALUMNI-CHECK', 'first_name' => 'Ana', 'last_name' => 'Santos', 'record_type' => 'alumni', 'academic_status' => 'alumni'];
        $this->post(route('registrar.students.store'), $payload + ['graduation_year' => 2020, 'graduation_date' => '2021-04-01'])
            ->assertSessionHasErrors('graduation_date');
        $this->assertDatabaseMissing('student_profiles', ['student_number' => 'ALUMNI-CHECK']);
        $this->post(route('registrar.students.store'), $payload)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('student_profiles', ['student_number' => 'ALUMNI-CHECK', 'academic_status' => 'alumni', 'user_id' => null]);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_record_forms_render_the_correct_initial_fields_for_student_and_alumni(): void
    {
        $this->actingAs($this->staff('registrar'));
        foreach (['students', 'alumni'] as $type) {
            $response = $this->get(route('registrar.students.create', ['type' => $type]))->assertOk()
                ->assertSee('Personal details')->assertSee('Academic details')->assertSee('data-academic-record-form', false);
            $hiddenFields = $type === 'students' ? 'data-alumni-fields' : 'data-student-fields';
            $this->assertMatchesRegularExpression('/'.$hiddenFields.'\s+hidden disabled/', $response->getContent());
        }
    }

    public function test_registrar_can_create_account_free_academic_record(): void
    {
        $registrar = $this->staff('registrar');
        $this->actingAs($registrar)->post(route('registrar.students.store'), [
            'student_number' => 'DTC-1001', 'first_name' => 'Ana', 'last_name' => 'Santos', 'academic_status' => 'active',
        ])->assertRedirect();

        $record = StudentProfile::where('student_number', 'DTC-1001')->firstOrFail();
        $this->assertDatabaseHas('student_profiles', ['student_number' => 'DTC-1001', 'user_id' => null, 'first_name' => 'Ana']);
        $this->assertDatabaseCount('users', 1);
        $this->actingAs($registrar)->get(route('registrar.students.index'))->assertOk();
        $this->actingAs($registrar)->get(route('registrar.students.show', $record))
            ->assertOk()
            ->assertSee('record-academic-status', false)
            ->assertDontSee('Rows per page');
        $this->actingAs($registrar)->get(route('registrar.students.edit', $record))->assertOk();
    }

    public function test_academic_record_uses_the_portal_current_term_year_level(): void
    {
        $registrar = $this->staff('registrar');
        $student = $this->makeUserWithRole('student');
        Setting::set('current_school_year', '2026-2027');
        Setting::set('current_semester', '1st');
        $record = StudentProfile::create([
            'user_id' => $student->id,
            'student_number' => 'DTC-2002',
            'first_name' => 'Jose',
            'last_name' => 'Dela Cruz',
            'year_level' => 1,
            'academic_status' => 'active',
        ]);
        Enrollment::factory()->create([
            'user_id' => $student->id,
            'school_year' => '2025-2026',
            'semester' => 1,
            'year_level' => 4,
        ]);
        Enrollment::factory()->create([
            'user_id' => $student->id,
            'school_year' => '2026-2027',
            'semester' => 1,
            'year_level' => 3,
        ]);

        $this->actingAs($student)->get(route('portal.dashboard'))
            ->assertOk()
            ->assertSee('3rd Year');
        $this->actingAs($registrar)->get(route('registrar.students.show', $record))
            ->assertOk()
            ->assertSee('3rd Year')
            ->assertDontSee('1st Year');
    }

    public function test_cashier_cannot_open_academic_management(): void
    {
        $this->actingAs($this->staff('cashier'))->get(route('registrar.students.index'))->assertForbidden();
        $this->actingAs($this->staff('cashier'))->get(route('registrar.imports.index'))->assertForbidden();
    }

    public function test_csv_preview_does_not_write_until_confirmed_and_repeated_commit_is_blocked(): void
    {
        $registrar = $this->staff('registrar');
        $csv = "Student ID no.,First Name,Middle Name,Last Name,Suffix,Email,Program,Year Level,School Year\nDTC-2001,Jose,,Dela Cruz,,, ,2,2025-2026\n";
        $response = $this->actingAs($registrar)->post(route('registrar.imports.upload'), [
            'import_type' => 'students', 'file' => UploadedFile::fake()->createWithContent('students.csv', $csv),
        ]);
        $batch = \App\Models\AcademicImportBatch::firstOrFail();
        $response->assertRedirect(route('registrar.imports.show', $batch));
        $this->assertDatabaseMissing('student_profiles', ['student_number' => 'DTC-2001']);
        $this->actingAs($registrar)->get(route('registrar.imports.show', $batch))->assertOk();

        $this->actingAs($registrar)->post(route('registrar.imports.commit', $batch), ['confirm' => '1'])->assertRedirect();
        $this->assertDatabaseHas('student_profiles', ['student_number' => 'DTC-2001', 'user_id' => null]);
        $this->actingAs($registrar)->post(route('registrar.imports.commit', $batch), ['confirm' => '1'])->assertStatus(409);
        $this->assertSame(1, StudentProfile::where('student_number', 'DTC-2001')->count());
    }

    public function test_duplicate_student_numbers_in_upload_are_marked_invalid_and_existing_rows_are_not_overwritten(): void
    {
        StudentProfile::create(['student_number' => 'DTC-3001', 'first_name' => 'Existing', 'last_name' => 'Person', 'academic_status' => 'active', 'admission_type' => 'imported']);
        $registrar = $this->staff('registrar');
        $csv = "Student ID no.,First Name,Middle Name,Last Name,Suffix,Email,Program,Year Level,School Year\nDTC-3001,Changed,,Person,,,,,\nDTC-3002,New,,Person,,,,,\nDTC-3002,Duplicate,,Person,,,,,\n";
        $this->actingAs($registrar)->post(route('registrar.imports.upload'), [
            'import_type' => 'students', 'file' => UploadedFile::fake()->createWithContent('rows.csv', $csv),
        ])->assertRedirect();
        $batch = \App\Models\AcademicImportBatch::firstOrFail();
        $this->assertSame(2, $batch->valid_rows);
        $this->assertSame(1, $batch->invalid_rows);
        $this->actingAs($registrar)->post(route('registrar.imports.commit', $batch), ['confirm' => '1']);
        $this->assertDatabaseHas('student_profiles', ['student_number' => 'DTC-3001', 'first_name' => 'Existing']);
        $this->assertDatabaseHas('student_profiles', ['student_number' => 'DTC-3002', 'first_name' => 'New']);
    }

    public function test_template_download_is_header_only_and_xlsx_is_rejected(): void
    {
        $registrar = $this->staff('registrar');
        $response = $this->actingAs($registrar)->get(route('registrar.imports.template', 'alumni'));
        $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertSame(['Student ID no.', 'First Name', 'Middle Name', 'Last Name', 'Suffix', 'Email', 'Program Completed', 'Graduation Year', 'Graduation Date'], str_getcsv(trim($response->streamedContent())));

        $this->actingAs($registrar)->post(route('registrar.imports.upload'), [
            'import_type' => 'students', 'file' => UploadedFile::fake()->create('rows.xlsx', 2, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
        ])->assertSessionHasErrors('file');
    }

    public function test_invalid_headers_are_rejected_and_preview_can_be_cancelled(): void
    {
        $registrar = $this->staff('registrar');
        $this->actingAs($registrar)->post(route('registrar.imports.upload'), [
            'import_type' => 'students', 'file' => UploadedFile::fake()->createWithContent('wrong.csv', "Name,Student ID no.\nA,1\n"),
        ])->assertSessionHasErrors('file');

        $csv = "Student ID no.,First Name,Middle Name,Last Name,Suffix,Email,Program,Year Level,School Year\nDTC-4001,Lea,,Cruz,,,,,\n";
        $this->actingAs($registrar)->post(route('registrar.imports.upload'), [
            'import_type' => 'students', 'file' => UploadedFile::fake()->createWithContent('rows.csv', $csv),
        ])->assertRedirect();
        $batch = \App\Models\AcademicImportBatch::latest('id')->firstOrFail();
        $this->actingAs($registrar)->post(route('registrar.imports.cancel', $batch))->assertRedirect();
        $this->assertSame('cancelled', $batch->fresh()->status);
        $this->assertDatabaseMissing('student_profiles', ['student_number' => 'DTC-4001']);
    }
}
