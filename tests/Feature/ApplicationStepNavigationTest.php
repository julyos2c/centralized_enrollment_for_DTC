<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Program;
use App\Services\ApplicationFormSteps;
use App\Services\PsgcLocationCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\SeedsRoles;
use Tests\TestCase;

/**
 * Section-by-section validation behind the application wizard's Next button
 * (POST /apply/steps/{step}) and the progress saved in the session.
 */
class ApplicationStepNavigationTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    private string $token;
    private Program $program;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        Mail::fake();
        config(['services.application_submission.per_ip_per_hour' => 100]);
        $this->program = Program::factory()->create();
        $this->openForm();
    }

    private function openForm(): void
    {
        $this->get(route('public.application.create'))->assertOk();
        $this->token = $this->app['session.store']->get('public_application_submission_token');
    }

    private function answers(array $overrides = []): array
    {
        return array_filter(array_merge([
            'submission_token' => $this->token,
            'academic_status' => 'new_student', 'program_id' => $this->program->id,
            'first_name' => 'Step', 'last_name' => 'Applicant', 'gender' => 'female', 'birthdate' => '2007-03-04',
            'email' => 'step.applicant@example.test', 'country' => 'Philippines',
        ], $overrides), fn ($value) => $value !== null);
    }

    private function step(int $step, array $overrides = [])
    {
        return $this->postJson(route('public.application.step', $step), $this->answers($overrides));
    }

    private function progress(): array
    {
        return $this->app['session.store']->get(ApplicationFormSteps::SESSION_KEY, []);
    }

    public function test_an_incomplete_section_blocks_moving_forward(): void
    {
        $this->step(1, ['academic_status' => null, 'program_id' => null])
            ->assertStatus(422)
            ->assertJsonPath('step', 1)
            ->assertJsonPath('completed', 0)
            ->assertJsonValidationErrors(['academic_status', 'program_id']);

        $this->step(2, ['gender' => null])
            ->assertStatus(422)->assertJsonPath('step', 2)
            ->assertJsonPath('errors.gender.0', 'Choose your gender.');

        $this->step(3, ['email' => 'not-an-email'])
            ->assertStatus(422)->assertJsonPath('step', 3)
            ->assertJsonPath('errors.email.0', 'Enter a valid email address, like name@example.com.');

        $this->assertSame(0, $this->progress()['completed'] ?? 0);
    }

    public function test_a_later_section_cannot_be_reached_by_a_direct_request(): void
    {
        // Personal information is missing, so section 4 cannot be completed.
        $this->step(4, ['first_name' => null, 'last_name' => null])
            ->assertStatus(422)
            ->assertJsonPath('step', 2)
            ->assertJsonPath('completed', 0)
            ->assertJsonPath('message', 'Complete "Personal information" first.')
            ->assertJsonValidationErrors(['first_name', 'last_name']);

        // Nothing is marked complete by skipping ahead, not even section 1.
        $this->assertSame(0, $this->progress()['completed']);
        $this->postJson(route('public.application.step', 6), $this->answers())->assertNotFound();
        $this->postJson(route('public.application.step', 0), $this->answers())->assertNotFound();
    }

    public function test_completed_sections_are_saved_in_the_session_and_restored_on_reload(): void
    {
        foreach ([1, 2, 3, 4, 5] as $step) {
            $this->step($step)->assertOk()->assertJsonPath('completed', $step)->assertJsonPath('next', $step + 1);
        }

        $progress = $this->progress();
        $this->assertSame(5, $progress['completed']);
        $this->assertSame('Step', $progress['data']['first_name']);
        $this->assertSame('step.applicant@example.test', $progress['data']['email']);
        $this->assertArrayNotHasKey('submission_token', $progress['data']);

        $html = $this->get(route('public.application.create'))->assertOk()->getContent();
        $this->assertStringContainsString('window.dtcApplicationProgress = {"completed":5', $html);
        $this->assertStringContainsString('"first_name":"Step"', $html);
        $this->assertStringContainsString('data-step-url="/apply/steps/:step"', $html);
    }

    public function test_saved_progress_belongs_to_the_session(): void
    {
        $this->step(1)->assertOk();
        $this->flushSession();

        $html = $this->get(route('public.application.create'))->getContent();
        $this->assertStringContainsString('window.dtcApplicationProgress = {"completed":0', $html);
        $this->assertStringNotContainsString('"first_name":"Step"', $html);

        // A form from another session (old token) cannot save progress.
        $this->postJson(route('public.application.step', 1), $this->answers(['submission_token' => str_repeat('a', 64)]))
            ->assertStatus(419)->assertJsonPath('expired', true);
    }

    public function test_editing_an_earlier_section_keeps_later_sections_only_while_they_still_pass(): void
    {
        foreach ([1, 2, 3, 4] as $step) {
            $this->step($step)->assertOk();
        }

        // A valid change to section 1 keeps sections 2–4 complete.
        $this->step(1, ['program_id' => Program::factory()->create()->id])->assertOk()->assertJsonPath('completed', 4);

        // Section 2 was emptied: section 1 still passes, but only section 1 stays complete.
        $this->step(1, ['first_name' => null])->assertOk()->assertJsonPath('completed', 1);
        $this->assertSame(1, $this->progress()['completed']);

        // Once section 2 is fixed, sections 3 and 4 count again because they still pass.
        $this->step(2)->assertOk()->assertJsonPath('completed', 4);
        // A section never completed is not credited.
        $this->assertSame(4, $this->progress()['reached']);
    }

    public function test_conditional_fields_are_required_when_chosen(): void
    {
        $this->step(4, ['school_choice' => '__not_listed'])
            ->assertStatus(422)->assertJsonPath('step', 4)
            ->assertJsonPath('errors.most_recent_school.0', 'Type the name of your Senior High School.');
        $this->step(4, ['school_choice' => '__outside', 'most_recent_school' => 'Cebu City National Science High School'])->assertOk();

        $this->step(4, ['school_choice' => 'listed'])->assertStatus(422)->assertJsonValidationErrors(['school_id']);
        $this->step(4, ['education_status' => 'Other'])->assertStatus(422)->assertJsonValidationErrors(['education_status_other']);
        $this->step(4, ['school_year_started' => '__other'])->assertStatus(422)->assertJsonValidationErrors(['school_year_started_other']);
        $this->step(4, ['shs_strand_selection' => 'other'])->assertStatus(422)->assertJsonValidationErrors(['shs_strand']);
        // Years that contradict each other are caught in the education section too.
        $this->step(4, ['school_year_started' => '2024-2025', 'school_year_completed' => '2020-2021'])
            ->assertStatus(422)->assertJsonValidationErrors(['school_year_started']);
    }

    public function test_a_started_address_must_reach_a_city(): void
    {
        $catalog = app(PsgcLocationCatalog::class);
        $province = collect($catalog->areas('0700000000'))->firstWhere('type', 'province');
        $city = $catalog->cities($province['code'])[0];

        $this->step(3, ['region_code' => '0700000000'])
            ->assertStatus(422)->assertJsonPath('step', 3)
            ->assertJsonValidationErrors(['city_code']);
        $this->step(3, ['region_code' => '0700000000', 'province_code' => $province['code'], 'city_code' => $city['code']])->assertOk();
        // The address stays optional, and other countries do not use the lists.
        $this->step(3)->assertOk();
        $this->step(3, ['country' => 'Japan', 'region_code' => '0700000000'])->assertOk();
    }

    public function test_files_are_not_uploaded_or_saved_by_the_step_check(): void
    {
        Storage::fake('local');
        foreach ([1, 2, 3, 4] as $step) {
            $this->step($step)->assertOk();
        }
        $this->post(route('public.application.step', 5), $this->answers([
            'documents' => ['psa_birth_certificate' => UploadedFile::fake()->create('birth.pdf', 10, 'application/pdf')],
        ]), ['Accept' => 'application/json'])->assertOk();

        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertArrayNotHasKey('documents', $this->progress()['data']);
        $this->assertSame(0, Application::count());
    }

    public function test_final_submission_validates_everything_and_clears_saved_progress(): void
    {
        foreach ([1, 2, 3, 4, 5] as $step) {
            $this->step($step)->assertOk();
        }

        // Required acknowledgments are checked at submission.
        $this->from(route('public.application.create'))
            ->post(route('public.application.store'), $this->answers())
            ->assertSessionHasErrors(['ack_enrollment', 'acknowledge_privacy']);
        $this->assertSame(0, Application::count());

        // Skipping the wizard does not skip validation.
        $this->openForm();
        $this->from(route('public.application.create'))
            ->post(route('public.application.store'), $this->answers(['first_name' => null, 'ack_enrollment' => '1', 'acknowledge_privacy' => '1']))
            ->assertSessionHasErrors(['first_name']);
        $html = $this->get(route('public.application.create'))->getContent();
        // The section with the error (2) and later sections are no longer complete.
        $this->assertStringContainsString('{"completed":1,"error_step":2', $html);

        $this->openForm();
        $this->post(route('public.application.store'), $this->answers(['ack_enrollment' => '1', 'acknowledge_privacy' => '1']))
            ->assertSessionHasNoErrors()->assertRedirect(route('public.application.success'));
        $this->assertSame(1, Application::count());
        $this->assertSame([], $this->progress());
    }
}
