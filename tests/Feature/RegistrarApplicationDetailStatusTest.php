<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Program;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\SeedsRoles;
use Tests\TestCase;

class RegistrarApplicationDetailStatusTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_submitted_application_shows_only_start_review_action(): void
    {
        $registrar = $this->makeUserWithRole('registrar');
        $application = $this->makeApplication('submitted');

        $response = $this->actingAs($registrar)
            ->get(route('registrar.applications.show', $application));

        $response->assertOk()
            ->assertSee('data-dtc-confirm-subject="Test Applicant"', false)
            ->assertSee('Submitted')
            ->assertSee('Start Review')
            ->assertDontSee('This application has been rejected.')
            ->assertDontSee(route('registrar.applications.approve', $application))
            ->assertDontSee(route('registrar.applications.revision', $application))
            ->assertDontSee(route('registrar.applications.reject', $application));
    }

    public function test_registrar_detail_formats_legacy_name_values_without_rewriting_them(): void
    {
        $registrar = $this->makeUserWithRole('registrar');
        $application = $this->makeApplication('submitted');
        DB::table('applications')->where('id', $application->id)->update([
            'first_name' => 'jUAN',
            'middle_name' => 'aNGEL',
            'last_name' => 'DELA CRUZ',
        ]);

        $this->actingAs($registrar)
            ->get(route('registrar.applications.show', $application))
            ->assertOk()
            ->assertSee('Juan')
            ->assertSee('Angel')
            ->assertSee('Dela Cruz');

        $this->assertDatabaseHas('applications', [
            'id' => $application->id,
            'first_name' => 'jUAN',
            'middle_name' => 'aNGEL',
            'last_name' => 'DELA CRUZ',
        ]);
    }

    public function test_under_review_application_shows_permitted_review_actions(): void
    {
        $registrar = $this->makeUserWithRole('registrar');
        $application = $this->makeApplication('under_review');

        $response = $this->actingAs($registrar)
            ->get(route('registrar.applications.show', $application));

        $response->assertOk()
            ->assertSee('Under Review')
            ->assertSee('Approve application?')
            ->assertSee('Request Revision')
            ->assertSee('Reject Application')
            ->assertSee(route('registrar.applications.approve', $application))
            ->assertSee(route('registrar.applications.revision', $application))
            ->assertSee(route('registrar.applications.reject', $application))
            ->assertDontSee('This application has been rejected.');
    }

    public function test_revision_required_application_shows_revision_state_without_review_actions(): void
    {
        $registrar = $this->makeUserWithRole('registrar');
        $application = $this->makeApplication('revision_required', [
            'remarks' => 'Please provide a clearer copy of the birth certificate.',
            'reviewed_at' => now(),
            'reviewed_by' => $registrar->id,
        ]);

        $response = $this->actingAs($registrar)
            ->get(route('registrar.applications.show', $application));

        $response->assertOk()
            ->assertSee('Revision Required')
            ->assertSee('Please provide a clearer copy of the birth certificate.')
            ->assertSee('This application requires revision.')
            ->assertDontSee('This application has been rejected.')
            ->assertDontSee(route('registrar.applications.approve', $application))
            ->assertDontSee(route('registrar.applications.revision', $application))
            ->assertDontSee(route('registrar.applications.reject', $application));
    }

    public function test_approved_and_rejected_applications_show_their_respective_messages(): void
    {
        $registrar = $this->makeUserWithRole('registrar');
        $approvedUser = User::factory()->create();
        $approved = $this->makeApplication('approved', ['user_id' => $approvedUser->id]);
        $rejected = $this->makeApplication('rejected', [
            'remarks' => 'Required admission documents were not provided.',
            'reviewed_at' => now(),
            'reviewed_by' => $registrar->id,
        ]);

        $this->actingAs($registrar)
            ->get(route('registrar.applications.show', $approved))
            ->assertOk()
            ->assertSee('This application has been approved. No further action is available.')
            ->assertDontSee('This application has been rejected.');

        $this->actingAs($registrar)
            ->get(route('registrar.applications.show', $rejected))
            ->assertOk()
            ->assertSee('This application has been rejected. Review the Registrar remarks above for the reason.')
            ->assertSee('Required admission documents were not provided.');
    }

    public function test_application_detail_authorization_and_invalid_transition_protection_remain_intact(): void
    {
        $application = $this->makeApplication('submitted');

        $this->get(route('registrar.applications.show', $application))
            ->assertRedirect(route('login'));

        $student = $this->makeUserWithRole('student');
        $this->actingAs($student)
            ->get(route('registrar.applications.show', $application))
            ->assertForbidden();

        $registrar = $this->makeUserWithRole('registrar');
        $this->actingAs($registrar)
            ->post(route('registrar.applications.approve', $application))
            ->assertRedirect(route('registrar.applications.show', $application));
        $this->assertSame('submitted', $application->fresh()->status);

        $this->post(route('registrar.applications.revision', $application), ['remarks' => 'Not used'])
            ->assertRedirect(route('registrar.applications.show', $application));
        $this->post(route('registrar.applications.reject', $application), ['remarks' => 'Not used'])
            ->assertRedirect(route('registrar.applications.show', $application));
        $this->assertSame('submitted', $application->fresh()->status);
    }

    private function makeApplication(string $status, array $attributes = []): Application
    {
        $program = Program::factory()->create();

        return Application::create(array_merge([
            'reference_no' => 'APP-DETAIL-' . uniqid(),
            'program_id' => $program->id,
            'academic_status' => 'new_student',
            'first_name' => 'Test',
            'last_name' => 'Applicant',
            'gender' => 'female',
            'birthdate' => '2005-01-01',
            'email' => 'detail-' . uniqid() . '@example.test',
            'status' => $status,
        ], $attributes));
    }
}
