<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Program;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SeedsRoles;
use Tests\TestCase;

class PublicApplicationStatusPageTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_status_lookup_form_uses_admission_page_layout_and_preserves_lookup_fields(): void
    {
        $this->from(route('public.application.status.form'))
            ->post(route('public.application.status'), [
                'reference_no' => 'APP-2026-ABC123',
                'email' => 'not-found@example.test',
            ])
            ->assertRedirect(route('public.application.status.form'))
            ->assertSessionHasErrors('reference_no');

        $this->get(route('public.application.status.form'))
            ->assertOk()
            ->assertSee('class="app-wizard-page status-lookup-page"', false)
            ->assertSee('Lookup details')
            ->assertSee('Both details must match the application record.')
            ->assertSee('Check your application status')
            ->assertDontSee('Application status</span>', false)
            ->assertSee('APP-2026-ABC123')
            ->assertSee('not-found@example.test')
            ->assertSee('Start an application');
    }

    public function test_matching_reference_and_email_show_guidance_and_do_not_expose_unrelated_details(): void
    {
        $program = Program::factory()->create(['name' => 'BS Criminology']);
        $application = Application::create([
            'reference_no' => 'APP-2026-STATUS1',
            'program_id' => $program->id,
            'academic_status' => 'new_student',
            'first_name' => 'Jamie',
            'last_name' => 'Applicant',
            'gender' => 'female',
            'birthdate' => '2005-01-01',
            'email' => 'jamie@example.test',
            'status' => 'submitted',
            'remarks' => 'Internal text is not shown for this status.',
        ]);

        $this->post(route('public.application.status'), [
            'reference_no' => $application->reference_no,
            'email' => $application->email,
        ])
            ->assertOk()
            ->assertSee('Application status')
            ->assertSee('class="status-result-badge is-submitted"', false)
            ->assertSee('Application details')
            ->assertSee('APP-2026-STATUS1')
            ->assertSee('Jamie Applicant')
            ->assertSee('BS Criminology')
            ->assertSee('Next step')
            ->assertSee('awaiting initial review')
            ->assertDontSee('Internal text is not shown');
    }

    public function test_public_status_displays_escaped_remarks_and_no_invented_resubmission_path(): void
    {
        $application = $this->makeApplication('revision_required', "Please clarify your school history.\n<script>alert('xss')</script>");

        $this->post(route('public.application.status'), [
            'reference_no' => $application->reference_no,
            'email' => $application->email,
        ])
            ->assertOk()
            ->assertSee('Registrar remarks')
            ->assertSee('Please clarify your school history.')
            ->assertSee('&lt;script&gt;alert(&#039;xss&#039;)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert', false)
            ->assertSee('This status page cannot receive corrected documents.')
            ->assertDontSee('Upload corrected documents');
    }

    public function test_rejected_status_shows_remarks_and_contact_registrar_guidance(): void
    {
        $application = $this->makeApplication('rejected', 'Review the stated admission decision.');

        $this->post(route('public.application.status'), [
            'reference_no' => $application->reference_no,
            'email' => $application->email,
        ])
            ->assertOk()
            ->assertSee('Application not approved')
            ->assertSee('Review the stated admission decision.')
            ->assertSee('contact the Registrar')
            ->assertSee('This page does not offer an appeal or resubmission process.');
    }

    public function test_approved_application_does_not_claim_enrollment_is_complete_without_real_records(): void
    {
        $application = $this->makeApplication('approved');

        $this->post(route('public.application.status'), [
            'reference_no' => $application->reference_no,
            'email' => $application->email,
        ])
            ->assertOk()
            ->assertSee('approval alone does not mean enrollment or payment is complete')
            ->assertSee('eligible to begin enrollment')
            ->assertSee('Request an activation link')
            ->assertSee('Enrollment and payment')
            ->assertDontSee('Enrollment and payment complete');
    }

    public function test_under_review_status_explains_that_no_applicant_action_is_currently_needed(): void
    {
        $application = $this->makeApplication('under_review');

        $this->post(route('public.application.status'), [
            'reference_no' => $application->reference_no,
            'email' => $application->email,
        ])
            ->assertOk()
            ->assertSee('Under Review')
            ->assertSee('currently being reviewed by the Registrar')
            ->assertSee('Monitor this status and follow any instructions from the Registrar.');
    }

    private function makeApplication(string $status, ?string $remarks = null): Application
    {
        $program = Program::factory()->create();

        return Application::create([
            'reference_no' => 'APP-2026-' . strtoupper(substr(md5(uniqid('', true)), 0, 8)),
            'program_id' => $program->id,
            'academic_status' => 'new_student',
            'first_name' => 'Status',
            'last_name' => 'Applicant',
            'gender' => 'female',
            'birthdate' => '2005-01-01',
            'email' => 'status-' . strtolower(substr(md5(uniqid('', true)), 0, 8)) . '@example.test',
            'status' => $status,
            'remarks' => $remarks,
            'reviewed_at' => in_array($status, ['approved', 'rejected', 'revision_required', 'under_review'], true) ? now() : null,
        ]);
    }
}
