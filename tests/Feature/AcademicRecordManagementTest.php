<?php

namespace Tests\Feature;

use App\Models\StudentProfile;
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
        $this->actingAs($registrar)->get(route('registrar.students.show', $record))->assertOk();
        $this->actingAs($registrar)->get(route('registrar.students.edit', $record))->assertOk();
    }

    public function test_cashier_cannot_open_academic_management(): void
    {
        $this->actingAs($this->staff('cashier'))->get(route('registrar.students.index'))->assertForbidden();
        $this->actingAs($this->staff('cashier'))->get(route('registrar.imports.index'))->assertForbidden();
    }

    public function test_csv_preview_does_not_write_until_confirmed_and_repeated_commit_is_blocked(): void
    {
        $registrar = $this->staff('registrar');
        $csv = "Student Number,First Name,Middle Name,Last Name,Suffix,Email,Program,Year Level,School Year\nDTC-2001,Jose,,Dela Cruz,,, ,2,2025-2026\n";
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
        $csv = "Student Number,First Name,Middle Name,Last Name,Suffix,Email,Program,Year Level,School Year\nDTC-3001,Changed,,Person,,,,,\nDTC-3002,New,,Person,,,,,\nDTC-3002,Duplicate,,Person,,,,,\n";
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
        $this->assertSame(['Student Number', 'First Name', 'Middle Name', 'Last Name', 'Suffix', 'Email', 'Program Completed', 'Graduation Year', 'Graduation Date'], str_getcsv(trim($response->streamedContent())));

        $this->actingAs($registrar)->post(route('registrar.imports.upload'), [
            'import_type' => 'students', 'file' => UploadedFile::fake()->create('rows.xlsx', 2, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
        ])->assertSessionHasErrors('file');
    }

    public function test_invalid_headers_are_rejected_and_preview_can_be_cancelled(): void
    {
        $registrar = $this->staff('registrar');
        $this->actingAs($registrar)->post(route('registrar.imports.upload'), [
            'import_type' => 'students', 'file' => UploadedFile::fake()->createWithContent('wrong.csv', "Name,Student Number\nA,1\n"),
        ])->assertSessionHasErrors('file');

        $csv = "Student Number,First Name,Middle Name,Last Name,Suffix,Email,Program,Year Level,School Year\nDTC-4001,Lea,,Cruz,,,,,\n";
        $this->actingAs($registrar)->post(route('registrar.imports.upload'), [
            'import_type' => 'students', 'file' => UploadedFile::fake()->createWithContent('rows.csv', $csv),
        ])->assertRedirect();
        $batch = \App\Models\AcademicImportBatch::latest('id')->firstOrFail();
        $this->actingAs($registrar)->post(route('registrar.imports.cancel', $batch))->assertRedirect();
        $this->assertSame('cancelled', $batch->fresh()->status);
        $this->assertDatabaseMissing('student_profiles', ['student_number' => 'DTC-4001']);
    }
}
