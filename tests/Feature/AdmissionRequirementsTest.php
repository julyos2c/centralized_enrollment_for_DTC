<?php

namespace Tests\Feature;

use App\Mail\AdmissionUpdate;
use App\Models\AdmissionRequirement;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\Program;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\SeedsRoles;
use Tests\TestCase;

class AdmissionRequirementsTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        Storage::fake('local');
        Mail::fake();
    }

    public function test_migration_seeds_the_previously_hardcoded_requirements_as_optional(): void
    {
        $codes = AdmissionRequirement::active()->pluck('code')->all();
        foreach (['form_138', 'good_moral_certificate', 'birth_certificate', 'marriage_certificate', 'transfer_credentials', 'transcript_of_records'] as $code) {
            $this->assertContains($code, $codes);
        }
        $this->assertSame(0, AdmissionRequirement::where('is_required', true)->count());
        $this->assertFalse(AdmissionRequirement::where('code', 'id_photo')->value('is_active'));
    }

    public function test_application_form_renders_configured_requirements_and_stores_uploads_with_term(): void
    {
        Setting::set('current_school_year', '2026-2027');
        Setting::set('current_semester', '1st');
        AdmissionRequirement::where('code', 'id_photo')->update(['is_active' => true]);
        $program = Program::factory()->create();

        $this->get(route('public.application.create'))->assertOk()
            ->assertSee('name="documents[id_photo]"', false)
            ->assertSee('name="documents[form_138]"', false);

        $this->post(route('public.application.store'), $this->payload($program, [
            'documents' => [
                'form_138' => UploadedFile::fake()->create('card.pdf', 50, 'application/pdf'),
                'transcript_of_records' => UploadedFile::fake()->create('tor.pdf', 50, 'application/pdf'),
            ],
        ]))->assertRedirect(route('public.application.success'));

        $application = Application::firstOrFail();
        $this->assertSame('2026-2027', $application->school_year);
        $this->assertSame('1st', $application->semester);
        // Only requirements that apply to a new student are stored.
        $this->assertSame(['form_138'], $application->documents()->pluck('document_type')->all());
        $document = $application->documents()->first();
        $this->assertSame('submitted', $document->status);
        $this->assertSame(AdmissionRequirement::where('code', 'form_138')->value('id'), $document->admission_requirement_id);
        Storage::disk('local')->assertExists($document->file_path);

        // Submission grants this browser access to the admission portal.
        $this->get(route('public.admission.portal'))->assertOk()->assertSee($application->reference_no);
    }

    public function test_requirement_file_type_and_size_rules_are_enforced(): void
    {
        AdmissionRequirement::where('code', 'form_138')->update(['allowed_extensions' => 'pdf', 'max_size_kb' => 100]);
        $program = Program::factory()->create();
        $this->get(route('public.application.create'));

        $this->post(route('public.application.store'), $this->payload($program, [
            'documents' => ['form_138' => UploadedFile::fake()->create('card.png', 50, 'image/png')],
        ]))->assertSessionHasErrors('documents.form_138');

        $this->post(route('public.application.store'), $this->payload($program, [
            'documents' => ['form_138' => UploadedFile::fake()->create('card.pdf', 200, 'application/pdf')],
        ]))->assertSessionHasErrors('documents.form_138');

        $this->assertSame(0, Application::count());
    }

    public function test_portal_requires_session_or_signed_link(): void
    {
        $application = $this->makeApplication();

        $this->get(route('public.admission.portal'))->assertRedirect(route('public.application.status.form'));
        $this->get(route('public.admission.enter', $application))->assertForbidden();

        $signed = URL::temporarySignedRoute('public.admission.enter', now()->addHour(), ['application' => $application->id]);
        $this->get($signed)->assertRedirect(route('public.admission.portal'));
        $this->get(route('public.admission.portal'))->assertOk()->assertSee($application->reference_no);
    }

    public function test_access_link_is_emailed_only_when_details_match_and_response_is_generic(): void
    {
        $application = $this->makeApplication();

        $this->post(route('public.admission.link'), ['reference_no' => $application->reference_no, 'email' => 'wrong@example.test'])
            ->assertRedirect(route('public.application.status.form'))->assertSessionHas('success');
        Mail::assertNothingSent();

        $this->post(route('public.admission.link'), ['reference_no' => $application->reference_no, 'email' => $application->email])
            ->assertRedirect(route('public.application.status.form'))->assertSessionHas('success');
        Mail::assertSent(AdmissionUpdate::class, fn ($mail) => $mail->hasTo($application->email) && str_contains($mail->actionUrl, 'signature='));
    }

    public function test_applicant_cannot_view_another_applicants_document(): void
    {
        $mine = $this->makeApplication();
        $other = $this->makeApplication();
        $theirs = $this->storeDocument($other, 'form_138');

        $this->enterPortal($mine);
        $this->get(route('public.admission.document', $theirs))->assertNotFound();

        $own = $this->storeDocument($mine, 'form_138');
        $this->get(route('public.admission.document', $own))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_replacing_a_rejected_document_keeps_history_and_returns_revision_to_review(): void
    {
        $application = $this->makeApplication(['status' => 'revision_required']);
        $old = $this->storeDocument($application, 'form_138', ['status' => 'rejected', 'review_remarks' => 'Blurry']);
        $requirement = AdmissionRequirement::where('code', 'form_138')->firstOrFail();

        $this->enterPortal($application);
        $this->get(route('public.admission.portal'))->assertSee('Blurry')->assertSee('Replace file');

        $this->post(route('public.admission.upload', $requirement), ['file' => UploadedFile::fake()->create('clear.pdf', 40, 'application/pdf')])
            ->assertRedirect();

        $this->assertNotNull($old->fresh()->superseded_at);
        $current = $application->currentDocuments()->firstOrFail();
        $this->assertSame('submitted', $current->status);
        $this->assertSame('clear.pdf', $current->original_name);
        $this->assertSame('under_review', $application->fresh()->status);
        Storage::disk('local')->assertExists($old->file_path);
    }

    public function test_approved_document_cannot_be_replaced_and_wrong_requirement_is_rejected(): void
    {
        $application = $this->makeApplication();
        $this->storeDocument($application, 'form_138', ['status' => 'approved']);
        $this->enterPortal($application);

        $this->post(route('public.admission.upload', AdmissionRequirement::where('code', 'form_138')->first()), [
            'file' => UploadedFile::fake()->create('new.pdf', 40, 'application/pdf'),
        ])->assertSessionHas('error');
        $this->assertSame(1, $application->documents()->count());

        // Transcript of Records does not apply to a new student.
        $this->post(route('public.admission.upload', AdmissionRequirement::where('code', 'transcript_of_records')->first()), [
            'file' => UploadedFile::fake()->create('tor.pdf', 40, 'application/pdf'),
        ])->assertNotFound();
    }

    public function test_registrar_reviews_documents_and_applicant_is_notified(): void
    {
        $registrar = $this->makeUserWithRole('registrar');
        AdmissionRequirement::where('code', 'form_138')->update(['is_required' => true]);
        $application = $this->makeApplication(['status' => 'under_review']);
        $form = $this->storeDocument($application, 'form_138');
        $moral = $this->storeDocument($application, 'good_moral_certificate');

        $this->actingAs($registrar)->post(route('registrar.applications.documents.review', [$application, $form]), ['decision' => 'rejected'])
            ->assertSessionHasErrors('remarks');

        $this->actingAs($registrar)->post(route('registrar.applications.documents.review', [$application, $form]), ['decision' => 'rejected', 'remarks' => 'Cut off'])
            ->assertRedirect();
        $this->assertSame('rejected', $form->fresh()->status);
        Mail::assertSent(AdmissionUpdate::class, fn ($mail) => str_contains($mail->eventMessage, 'Cut off'));

        $this->actingAs($registrar)->post(route('registrar.applications.documents.approve-pending', $application))->assertRedirect();
        $this->assertSame('approved', $moral->fresh()->status);
        $this->assertSame('rejected', $form->fresh()->status, 'Bulk approval only touches documents awaiting review.');

        $this->actingAs($registrar)->post(route('registrar.applications.documents.review', [$application, $form]), ['decision' => 'approved'])->assertRedirect();
        Mail::assertSent(AdmissionUpdate::class, fn ($mail) => $mail->eventTitle === 'Required documents approved');
        $this->assertDatabaseHas('audit_logs', ['action' => 'admission.document.approved']);
    }

    public function test_required_documents_gate_approval(): void
    {
        $registrar = $this->makeUserWithRole('registrar');
        AdmissionRequirement::where('code', 'birth_certificate')->update(['is_required' => true]);
        $application = $this->makeApplication(['status' => 'under_review']);

        $this->actingAs($registrar)->post(route('registrar.applications.approve', $application))
            ->assertSessionHas('error', 'All required documents must be approved before this application can be approved.');
        $this->assertSame('under_review', $application->fresh()->status);

        $this->storeDocument($application, 'birth_certificate', ['status' => 'approved']);
        $this->actingAs($registrar)->post(route('registrar.applications.approve', $application));
        $this->assertSame('approved', $application->fresh()->status);
    }

    public function test_registrar_can_resume_review_of_a_revision_request(): void
    {
        $registrar = $this->makeUserWithRole('registrar');
        $application = $this->makeApplication(['status' => 'revision_required']);

        $this->actingAs($registrar)->get(route('registrar.applications.show', $application))->assertSee('Resume Review');
        $this->actingAs($registrar)->post(route('registrar.applications.resume-review', $application))->assertRedirect();
        $this->assertSame('under_review', $application->fresh()->status);
    }

    public function test_staff_manage_requirements_and_other_roles_cannot(): void
    {
        $registrar = $this->makeUserWithRole('registrar');
        $this->actingAs($registrar)->get(route('registrar.admissions.requirements.index'))->assertOk()->assertSee('PSA Birth Certificate');

        $this->actingAs($registrar)->post(route('registrar.admissions.requirements.store'), [
            'name' => 'Medical Certificate', 'applicant_types' => ['new_student'], 'allowed_extensions' => ['pdf', 'jpg'],
            'max_size_mb' => 2, 'is_required' => '1', 'is_active' => '1',
        ])->assertRedirect();
        $requirement = AdmissionRequirement::where('code', 'medical_certificate')->firstOrFail();
        $this->assertTrue($requirement->is_required);
        $this->assertSame(['pdf', 'jpg', 'jpeg'], $requirement->extensions());
        $this->assertSame(2048, $requirement->max_size_kb);

        $this->actingAs($registrar)->post(route('registrar.admissions.requirements.toggle', $requirement))->assertRedirect();
        $this->assertFalse($requirement->fresh()->is_active);

        foreach (['cashier', 'student', 'new_applicant'] as $role) {
            $this->actingAs($this->makeUserWithRole($role))->get(route('registrar.admissions.requirements.index'))->assertForbidden();
        }
    }

    private function payload(Program $program, array $overrides = []): array
    {
        return array_merge([
            'submission_token' => $this->app['session.store']->get('public_application_submission_token'),
            'academic_status' => 'new_student', 'program_id' => $program->id,
            'first_name' => 'Test', 'last_name' => 'Applicant', 'gender' => 'female',
            'birthdate' => '2005-01-01', 'email' => 'req-' . uniqid() . '@example.test', 'ack_enrollment' => 'on', 'acknowledge_privacy' => 'on', 'consent_sensitive' => 'on',
        ], $overrides);
    }

    private function makeApplication(array $attributes = []): Application
    {
        return Application::create(array_merge([
            'reference_no' => 'APP-REQ-' . strtoupper(uniqid()),
            'program_id' => Program::factory()->create()->id,
            'academic_status' => 'new_student',
            'first_name' => 'Test', 'last_name' => 'Applicant', 'gender' => 'female',
            'birthdate' => '2005-01-01',
            'email' => 'req-' . uniqid() . '@example.test',
            'status' => 'submitted',
        ], $attributes));
    }

    private function storeDocument(Application $application, string $code, array $attributes = []): ApplicationDocument
    {
        $path = "applications/{$application->id}/" . uniqid() . '.pdf';
        Storage::disk('local')->put($path, '%PDF-1.4 test');

        return ApplicationDocument::create(array_merge([
            'application_id' => $application->id,
            'admission_requirement_id' => AdmissionRequirement::where('code', $code)->value('id'),
            'document_type' => $code,
            'file_path' => $path,
            'original_name' => $code . '.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 13,
            'status' => 'submitted',
        ], $attributes));
    }

    private function enterPortal(Application $application): void
    {
        $this->get(URL::temporarySignedRoute('public.admission.enter', now()->addHour(), ['application' => $application->id]));
    }
}
