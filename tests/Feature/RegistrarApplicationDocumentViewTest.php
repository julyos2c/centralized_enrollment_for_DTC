<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\Program;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\SeedsRoles;
use Tests\TestCase;

class RegistrarApplicationDocumentViewTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        Storage::fake('local');
    }

    private function application(): Application
    {
        return Application::create(['reference_no' => 'APP-'.Str::random(12), 'program_id' => Program::factory()->create()->id,
            'academic_status' => 'new_student', 'first_name' => 'Test', 'last_name' => 'Applicant',
            'gender' => 'female', 'birthdate' => '2005-01-01', 'email' => Str::random(10).'@example.test', 'status' => 'submitted']);
    }

    private function document(Application $application, string $content): ApplicationDocument
    {
        $path = 'documents/'.Str::random(12).'.pdf';
        Storage::disk('local')->put($path, $content);
        return ApplicationDocument::create(['application_id' => $application->id, 'document_type' => 'birth_certificate',
            'file_path' => $path, 'original_name' => 'Birth certificate.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => strlen($content)]);
    }

    public function test_registrar_can_preview_inline_and_still_download_the_same_file(): void
    {
        $application = $this->application();
        $content = "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF";
        $document = $this->document($application, $content);
        $this->actingAs($this->makeUserWithRole('registrar'));
        $preview = $this->get(route('registrar.applications.documents.view', [$application, $document]));
        $preview->assertOk()
            ->assertSee('Back to details')
            ->assertSee(route('registrar.applications.show', $application), false)
            ->assertSee('raw=1', false);
        $rawPreview = $this->get(route('registrar.applications.documents.view', [$application, $document]).'?raw=1');
        $rawPreview->assertOk()->assertHeader('Content-Type', 'application/pdf')->assertHeader('X-Content-Type-Options', 'nosniff');
        $download = $this->get(route('registrar.applications.documents.show', [$application, $document]))->assertOk();
        $this->assertStringStartsWith('attachment;', $download->headers->get('Content-Disposition'));
        $this->get(route('registrar.applications.show', $application))->assertOk()
            ->assertSee(route('registrar.applications.documents.view', [$application, $document]), false);
    }

    public function test_preview_rejects_other_applications_missing_files_and_unsafe_content(): void
    {
        $application = $this->application();
        $document = $this->document($application, '<html><script>alert(1)</script></html>');
        $this->actingAs($this->makeUserWithRole('registrar'));
        $this->get(route('registrar.applications.documents.view', [$application, $document]))->assertStatus(415);
        $this->get(route('registrar.applications.documents.view', [$this->application(), $document]))->assertNotFound();
        Storage::disk('local')->delete($document->file_path);
        $this->get(route('registrar.applications.documents.view', [$application, $document]))->assertNotFound();
    }

    public function test_students_cannot_preview_registrar_application_documents(): void
    {
        $application = $this->application();
        $document = $this->document($application, '%PDF-1.4');
        $this->actingAs($this->makeUserWithRole('student'))
            ->get(route('registrar.applications.documents.view', [$application, $document]))->assertForbidden();
    }
}
