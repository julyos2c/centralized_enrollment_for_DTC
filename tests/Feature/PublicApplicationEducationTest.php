<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Program;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Tests\SeedsRoles;
use Tests\TestCase;

class PublicApplicationEducationTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        config(['services.application_submission.per_ip_per_hour' => 100]);
        Mail::fake();
    }

    public function test_education_fields_are_persisted_for_each_applicant_type_and_irrelevant_values_are_discarded(): void
    {
        $program = Program::factory()->create();
        $cases = [
            'new_student' => [
                'shs_strand' => 'HUMSS',
                'previous_program' => 'stale previous program',
                'home_institution' => 'stale home institution',
            ],
            'transferee' => [
                'previous_program' => 'BS Information Systems',
                'last_year_level' => '2nd year, 1st semester',
                'shs_strand' => 'stale strand',
                'home_institution' => 'stale home institution',
            ],
            'shiftee' => [
                'previous_program' => 'BS Information Systems',
                'last_year_level' => '1st year, 2nd semester',
                'shs_strand' => 'stale strand',
            ],
            'returnee' => [
                'previous_program' => 'BS Information Systems',
                'last_year_level' => '3rd year',
                'home_program' => 'stale home program',
            ],
            'cross_enrollee' => [
                'home_institution' => 'Danao State College',
                'home_program' => 'BS Information Systems',
                'last_year_level' => '2nd year',
                'previous_program' => 'stale previous program',
            ],
        ];

        foreach ($cases as $type => $specificFields) {
            $email = $type . '@example.test';
            $this->get(route('public.application.create'))->assertOk();
            $this->post(route('public.application.store'), array_merge([
                'academic_status' => $type,
                'program_id' => $program->id,
                'submission_token' => $this->app['session.store']->get('public_application_submission_token'),
                'first_name' => 'Test',
                'last_name' => ucfirst($type),
                'gender' => 'female',
                'birthdate' => '2005-01-01',
                'email' => $email,
                'honorific' => 'Dr.',
                'suffix' => 'Jr.',
                'region_code' => '0700000000',
                'province_code' => '0701200000',
                'city_code' => '0701218000',
                'barangay' => 'Poblacion',
                'ack_enrollment' => 'on',
                'education_level' => 'Senior High School',
                'education_status' => 'Graduated',
                'most_recent_school' => 'Danao National High School',
                'school_year_completed' => '2024-2025',
                'school_location' => 'Danao, Bohol',
            ], $specificFields))
                ->assertRedirect(route('public.application.success'));

            $application = Application::where('email', $email)->firstOrFail();
            $this->assertSame('Senior High School', $application->education_level);
            $this->assertSame('Graduated', $application->education_status);
            $this->assertSame('Danao National High School', $application->most_recent_school);
            $this->assertSame('Dr.', $application->honorific);
            $this->assertSame('Jr.', $application->suffix);
            $this->assertSame('Region VII (Central Visayas)', $application->region);
            $this->assertSame('Bohol', $application->province);
            $this->assertSame('Danao', $application->city);
            $this->assertSame('Poblacion', $application->barangay);
            $this->assertNotNull($application->acknowledged_at, 'The acknowledgment must be auditable.');
            $this->get(route('public.application.success'))
                ->assertOk()
                ->assertSee('Educational background submitted')
                ->assertSee('Danao National High School');

            foreach (['shs_strand', 'previous_program', 'last_year_level', 'home_institution', 'home_program'] as $field) {
                $expected = $specificFields[$field] ?? null;
                $isAllowed = match ($field) {
                    'shs_strand' => $type === 'new_student',
                    'previous_program' => in_array($type, ['transferee', 'shiftee', 'returnee'], true),
                    'last_year_level' => in_array($type, ['transferee', 'shiftee', 'returnee', 'cross_enrollee'], true),
                    'home_institution', 'home_program' => $type === 'cross_enrollee',
                };
                $this->assertSame($isAllowed ? $expected : null, $application->{$field}, $type . ' ' . $field);
            }
        }
    }

    public function test_applicant_and_family_names_are_normalized_before_storage_and_rendered_in_confirmation(): void
    {
        $program = Program::factory()->create();
        $this->get(route('public.application.create'))
            ->assertOk()
            ->assertSee(asset('js/dtc-name-normalizer.js'), false)
            ->assertSee(asset('js/application-wizard.js'), false);

        $this->post(route('public.application.store'), [
            'submission_token' => $this->app['session.store']->get('public_application_submission_token'),
            'academic_status' => 'new_student',
            'program_id' => $program->id,
            'first_name' => 'jUAN',
            'middle_name' => '  aNGEL   pAUL  ',
            'last_name' => 'DELA   CRUZ',
            'suffix' => 'III',
            'father_name' => "O'CONNOR",
            'mother_name' => 'ANNE-MARIE',
            'gender' => 'male',
            'birthdate' => '2005-01-01',
            'email' => 'MiXeD@example.test',
            'ack_enrollment' => 'on',
        ])->assertRedirect(route('public.application.success'));

        $application = Application::where('email', 'MiXeD@example.test')->firstOrFail();
        $stored = DB::table('applications')->where('id', $application->id)->first();
        $this->assertSame('Juan', $stored->first_name);
        $this->assertSame('Angel Paul', $stored->middle_name);
        $this->assertSame('Dela Cruz', $stored->last_name);
        $this->assertSame("O'Connor", $stored->father_name);
        $this->assertSame('Anne-Marie', $stored->mother_name);
        $this->assertSame('MiXeD@example.test', $stored->email, 'Email casing must remain untouched.');
        $this->assertSame('III', $stored->suffix);

        $this->get(route('public.application.success'))
            ->assertOk()
            ->assertSee('Juan Angel Paul Dela Cruz III');
    }

    public function test_education_details_are_optional_for_all_five_types_and_submission_requires_the_acknowledgment(): void
    {
        $program = Program::factory()->create();
        foreach (['new_student', 'transferee', 'shiftee', 'returnee', 'cross_enrollee'] as $type) {
            $payload = [
                'academic_status' => $type,
                'program_id' => $program->id,
                'first_name' => 'Optional',
                'last_name' => ucfirst($type),
                'gender' => 'male',
                'birthdate' => '2005-01-01',
                'email' => $type . '-optional-education@example.test',
                'ack_enrollment' => 'on',
            ];

            $this->get(route('public.application.create'))->assertOk();
            $payload['submission_token'] = $this->app['session.store']->get('public_application_submission_token');
            $this->post(route('public.application.store'), $payload)
                ->assertRedirect(route('public.application.success'));
            $this->assertNull(Application::where('email', $payload['email'])->firstOrFail()->most_recent_school);
        }

        $payload = [
            'academic_status' => 'new_student',
            'program_id' => $program->id,
            'first_name' => 'Missing',
            'last_name' => 'Acknowledgment',
            'gender' => 'male',
            'birthdate' => '2005-01-01',
            'email' => 'missing-ack@example.test',
        ];
        unset($payload['ack_enrollment']);
        $this->get(route('public.application.create'))->assertOk();
        $payload['submission_token'] = $this->app['session.store']->get('public_application_submission_token');
        $this->from(route('public.application.create'))
            ->post(route('public.application.store'), $payload)
            ->assertRedirect(route('public.application.create'))
            ->assertSessionHasErrors('ack_enrollment');
        $this->assertDatabaseMissing('applications', ['email' => $payload['email']]);
    }

    public function test_applicant_type_specific_education_fields_are_validated_only_when_relevant(): void
    {
        $program = Program::factory()->create();
        $payload = [
            'academic_status' => 'transferee',
            'program_id' => $program->id,
            'first_name' => 'Field',
            'last_name' => 'Rules',
            'gender' => 'female',
            'birthdate' => '2005-01-01',
            'email' => 'field-rules@example.test',
            'ack_enrollment' => 'on',
            'previous_program' => str_repeat('x', 256),
            'shs_strand' => str_repeat('x', 500),
        ];

        $this->get(route('public.application.create'))->assertOk();
        $payload['submission_token'] = $this->app['session.store']->get('public_application_submission_token');
        $this->from(route('public.application.create'))
            ->post(route('public.application.store'), $payload)
            ->assertRedirect(route('public.application.create'))
            ->assertSessionHasErrors('previous_program');
        $this->assertDatabaseMissing('applications', ['email' => $payload['email']]);

        $payload['email'] = 'irrelevant-field@example.test';
        $payload['previous_program'] = 'Previous program';
        $this->post(route('public.application.store'), $payload)
            ->assertRedirect(route('public.application.success'));
        $this->assertNull(Application::where('email', $payload['email'])->firstOrFail()->shs_strand);
    }

    public function test_required_applicant_fields_are_enforced_for_all_five_types(): void
    {
        $program = Program::factory()->create();
        $requiredFields = ['academic_status', 'program_id', 'first_name', 'last_name', 'gender', 'birthdate', 'email', 'ack_enrollment'];

        foreach (['new_student', 'transferee', 'shiftee', 'returnee', 'cross_enrollee'] as $type) {
            foreach ($requiredFields as $missingField) {
                $payload = [
                    'academic_status' => $type,
                    'program_id' => $program->id,
                    'first_name' => 'Required',
                    'last_name' => 'Field',
                    'gender' => 'female',
                    'birthdate' => '2005-01-01',
                    'email' => $type . '-' . $missingField . '@example.test',
                    'ack_enrollment' => 'on',
                ];
                unset($payload[$missingField]);

                $this->get(route('public.application.create'))->assertOk();
                $payload['submission_token'] = $this->app['session.store']->get('public_application_submission_token');
                $this->from(route('public.application.create'))
                    ->post(route('public.application.store'), $payload)
                    ->assertRedirect(route('public.application.create'))
                    ->assertSessionHasErrors($missingField);
            }
        }
    }

    public function test_admission_form_renders_the_six_step_sections_and_education_inputs(): void
    {
        $this->get(route('public.application.create'))
            ->assertOk()
            ->assertSee('Online Admission Application')
            ->assertSee('Educational Background')
            ->assertSee('most_recent_school')
            ->assertSee('home_institution')
            ->assertSee('Family &amp; Supporting Documents', false)
            ->assertSee('Most recent school attended <span class="opt">(Optional)</span>', false)
            ->assertSee('confirm that the information I provided is accurate')
            ->assertSee('Review Your Application')
            ->assertSee('wiz-step-label">Education', false);
    }

    public function test_replaying_a_successful_submission_token_does_not_create_a_second_application(): void
    {
        $program = Program::factory()->create();
        $this->get(route('public.application.create'))->assertOk();
        $token = $this->app['session.store']->get('public_application_submission_token');
        $payload = [
            'submission_token' => $token,
            'academic_status' => 'new_student',
            'program_id' => $program->id,
            'first_name' => 'One',
            'last_name' => 'Submission',
            'gender' => 'female',
            'birthdate' => '2005-01-01',
            'email' => 'one-submission@example.test',
            'ack_enrollment' => 'on',
        ];

        $this->post(route('public.application.store'), $payload)
            ->assertRedirect(route('public.application.success'));

        $this->withSession(['public_application_submission_token' => $token])
            ->from(route('public.application.create'))
            ->post(route('public.application.store'), $payload)
            ->assertRedirect(route('public.application.create'))
            ->assertSessionHasErrors('application');

        $this->assertSame(1, Application::where('email', $payload['email'])->count());
    }
}
