<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Program;
use App\Support\ApplicationTextNormalizer;
use App\Support\NameNormalizer;
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

    private function personalFormXPath(string $html): \DOMXPath
    {
        $document = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML($html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        return new \DOMXPath($document);
    }

    public function test_personal_details_start_collapsed_without_disabling_fields_or_hiding_required_fields(): void
    {
        $response = $this->get(route('public.application.create'))->assertOk();
        $xpath = $this->personalFormXPath($response->getContent());
        $this->assertSame('false', $xpath->query('//*[@id="personalDetailsToggle"]')->item(0)->getAttribute('aria-expanded'));
        $this->assertTrue($xpath->query('//*[@id="personalDetailsContent"]')->item(0)->hasAttribute('hidden'));
        $this->assertSame(0, $xpath->query('//*[@id="personalDetailsContent"]//*[@required or @disabled]')->length);
        $this->assertSame(4, $xpath->query('//*[@id="step-2"]//*[@required]')->length);
        $names = [];
        foreach ($xpath->query('//*[@id="step-2"]//input | //*[@id="step-2"]//select') as $field) {
            $names[] = $field->getAttribute('name');
        }
        $this->assertSame(['honorific', 'first_name', 'last_name', 'suffix', 'middle_name'], array_slice($names, 0, 5));
        $this->assertSame('Filipino', $xpath->query('//*[@id="nationality"]')->item(0)->getAttribute('value'));
        $this->assertSame(6, $xpath->query('//*[@id="personalDetailsContent"]//input | //*[@id="personalDetailsContent"]//select')->length);
    }

    public function test_personal_details_open_for_returned_values_but_not_the_default_nationality(): void
    {
        foreach ([
            [['nationality' => 'Filipino'], 'false'],
            [['nationality' => 'Canadian'], 'true'],
            [['birth_place' => 'Danao'], 'true'],
            [['marital_status' => 'married', 'spouse_name' => 'Test Spouse'], 'true'],
        ] as [$oldInput, $expanded]) {
            $response = $this->withSession(['_old_input' => $oldInput])->get(route('public.application.create'))->assertOk();
            $xpath = $this->personalFormXPath($response->getContent());
            $this->assertSame($expanded, $xpath->query('//*[@id="personalDetailsToggle"]')->item(0)->getAttribute('aria-expanded'));
            $this->assertSame($expanded === 'false', $xpath->query('//*[@id="personalDetailsContent"]')->item(0)->hasAttribute('hidden'));
        }
    }

    public function test_optional_personal_validation_errors_reopen_the_disclosure(): void
    {
        $program = Program::factory()->create();
        $this->get(route('public.application.create'))->assertOk();
        $this->from(route('public.application.create'))->post(route('public.application.store'), [
            'submission_token' => $this->app['session.store']->get('public_application_submission_token'),
            'academic_status' => 'new_student', 'program_id' => $program->id,
            'first_name' => 'Test', 'last_name' => 'Applicant', 'gender' => 'female',
            'birthdate' => '2005-01-01', 'email' => 'optional-error@example.test', 'ack_enrollment' => 'on',
            'marital_status' => 'invalid-status',
        ])->assertRedirect(route('public.application.create'))->assertSessionHasErrors('marital_status');
        $response = $this->get(route('public.application.create'))->assertOk();
        $xpath = $this->personalFormXPath($response->getContent());
        $this->assertSame('true', $xpath->query('//*[@id="personalDetailsToggle"]')->item(0)->getAttribute('aria-expanded'));
        $this->assertFalse($xpath->query('//*[@id="personalDetailsContent"]')->item(0)->hasAttribute('hidden'));
        $this->assertGreaterThan(0, $xpath->query('//*[@id="personalDetailsContent"]//*[contains(@class,"wiz-err-msg")]')->length);
        Mail::assertNothingSent();
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
                'honorific' => 'Dr. Emerita',
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
            $this->assertSame('Dr. Emerita', $application->honorific);
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
                if ($expected !== null && ApplicationTextNormalizer::isTitleCaseField($field)) {
                    $expected = NameNormalizer::normalize($expected);
                }
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
            ->assertSee(asset('js/application-wizard.js'), false)
            ->assertSee('dtcApplicationTitleCaseFields', false)
            ->assertSee('international_location', false)
            ->assertSee('aria-label="Application steps"', false)
            ->assertDontSee('id="wizardBar"', false)
            ->assertDontSee('id="wizCurrentStep"', false)
            ->assertSee('aria-controls="screeningNoticeContent"', false)
            ->assertSee('aria-expanded="false"', false)
            ->assertSee('Application notice')
            ->assertSee('type="text" name="honorific"', false)
            ->assertSee('type="text" name="suffix"', false)
            ->assertDontSee('Optional name details')
            ->assertSee('id="identity-details-heading"', false)
            ->assertSee('id="personal-additional-heading"', false);

        $this->post(route('public.application.store'), [
            'submission_token' => $this->app['session.store']->get('public_application_submission_token'),
            'academic_status' => 'new_student',
            'program_id' => $program->id,
            'first_name' => 'jUAN',
            'middle_name' => '  aNGEL   pAUL  ',
            'last_name' => 'DELA   CRUZ',
            'honorific' => 'Atty. Emerita',
            'suffix' => 'Esq.',
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
        $this->assertSame('Atty. Emerita', $stored->honorific);
        $this->assertSame("O'Connor", $stored->father_name);
        $this->assertSame('Anne-Marie', $stored->mother_name);
        $this->assertSame('MiXeD@example.test', $stored->email, 'Email casing must remain untouched.');
        $this->assertSame('Esq.', $stored->suffix);

        $this->get(route('public.application.success'))
            ->assertOk()
            ->assertSee('Atty. Emerita Juan Angel Paul Dela Cruz Esq.');
    }

    public function test_application_human_text_is_title_cased_while_special_format_fields_are_preserved(): void
    {
        $program = Program::factory()->create();
        $this->get(route('public.application.create'))->assertOk();

        $payload = [
            'submission_token' => $this->app['session.store']->get('public_application_submission_token'),
            'academic_status' => 'cross_enrollee',
            'program_id' => $program->id,
            'first_name' => 'jUAN',
            'last_name' => 'DELA CRUZ',
            'gender' => 'male',
            'birthdate' => '2005-01-01',
            'email' => ' MiXeD.Email@example.test ',
            'phone' => ' +63 912-345-6789 ',
            'current_address' => '  purok 2   remedios danao bohol, near st. john clinic.  ',
            'international_location' => 'REMedios danao bohol',
            'country' => '  UNITED states of AMERICA  ',
            'barangay' => 'poblacion 2',
            'postal_code' => ' 06004 ',
            'religion' => 'ROMAN catholic',
            'nationality' => 'fILIPINO',
            'lrn' => ' 001234567890 ',
            'pwd_id' => 'pwd-00Ab-19',
            'most_recent_school' => 'danao national high school',
            'school_location' => 'remedios, bohol',
            'school_year_completed' => ' 2024–2025 ',
            'home_institution' => 'danao community college',
            'home_program' => 'bs information systems',
            'last_year_level' => ' 2ND year, 1st SEMESTER ',
            'father_occupation' => 'self-employed FARMER',
            'parent_address' => '  purok 2   poblacion, danao. ',
            'occupation' => 'part-time student',
            'disability' => 'visual impairment',
            'ack_enrollment' => 'on',
        ];

        $this->post(route('public.application.store'), $payload)
            ->assertRedirect(route('public.application.success'));

        $application = Application::where('first_name', 'Juan')->firstOrFail();
        $stored = DB::table('applications')->where('id', $application->id)->first();

        $this->assertSame('Purok 2 Remedios Danao Bohol, Near St. John Clinic.', $stored->current_address);
        $this->assertSame('Remedios Danao Bohol', $stored->city);
        $this->assertSame('United States Of America', $stored->country);
        $this->assertSame('Poblacion 2', $stored->barangay);
        $this->assertSame('Roman Catholic', $stored->religion);
        $this->assertSame('Filipino', $stored->nationality);
        $this->assertSame('Danao National High School', $stored->most_recent_school);
        $this->assertSame('Remedios, Bohol', $stored->school_location);
        $this->assertSame('Danao Community College', $stored->home_institution);
        $this->assertSame('Bs Information Systems', $stored->home_program);
        $this->assertSame('2nd Year, 1st Semester', $stored->last_year_level);
        $this->assertSame('Self-Employed Farmer', $stored->father_occupation);
        $this->assertSame('Purok 2 Poblacion, Danao.', $stored->parent_address);
        $this->assertSame('Part-Time Student', $stored->occupation);
        $this->assertSame('Visual Impairment', $stored->disability);

        $this->assertSame('MiXeD.Email@example.test', $stored->email);
        $this->assertSame('+63 912-345-6789', $stored->phone);
        $this->assertSame('06004', $stored->postal_code);
        $this->assertSame('001234567890', $stored->lrn);
        $this->assertSame('pwd-00Ab-19', $stored->pwd_id);
        $this->assertSame('2024–2025', $stored->school_year_completed);

        $registrar = $this->makeUserWithRole('registrar');
        $this->actingAs($registrar)
            ->get(route('registrar.applications.show', $application))
            ->assertOk()
            ->assertSee('Purok 2 Remedios Danao Bohol, Near St. John Clinic.')
            ->assertSee('Danao National High School')
            ->assertSee('Danao Community College');
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
            ->assertSee('id="educationHeading"', false)
            ->assertSee('id="educationDescription"', false)
            ->assertSee('All other fields are optional and can be left blank.')
            ->assertDontSee('(Optional)')
            ->assertSee('By clicking <strong>Continue</strong>, you confirm accurate details', false)
            ->assertSee('type="hidden" id="ack_enrollment" name="ack_enrollment" value=""', false)
            ->assertSee('Review Your Application')
            ->assertSee('wiz-step-label">Education', false);
    }

    public function test_applicant_can_submit_guardian_details_without_parent_details(): void
    {
        $program = Program::factory()->create();
        $this->get(route('public.application.create'))->assertOk()
            ->assertSee('No parent details? Add a guardian instead.')
            ->assertSee('name="guardian_name"', false);
        $payload = [
            'submission_token' => $this->app['session.store']->get('public_application_submission_token'),
            'academic_status' => 'new_student', 'program_id' => $program->id,
            'first_name' => 'Juan', 'last_name' => 'Cruz', 'gender' => 'male',
            'birthdate' => '2005-01-01', 'email' => 'guardian@example.test',
            'ack_enrollment' => 'on', 'guardian_name' => '  mARIA rEYES ',
            'guardian_contact' => ' 09171234567 ',
        ];
        $this->post(route('public.application.store'), $payload)
            ->assertSessionHasNoErrors()->assertRedirect(route('public.application.success'));
        $application = Application::where('email', $payload['email'])->firstOrFail();
        $this->assertSame('Maria Reyes', $application->guardian_name);
        $this->assertSame('09171234567', $application->guardian_contact);
        $this->assertNull($application->father_name);
        $this->assertNull($application->mother_name);
        $registrar = $this->makeUserWithRole('registrar');
        $this->actingAs($registrar)->get(route('registrar.applications.show', $application))
            ->assertOk()->assertSee('Maria Reyes')->assertSee('09171234567');
    }

    public function test_invalid_guardian_details_are_rejected_and_preserved_for_correction(): void
    {
        $program = Program::factory()->create();
        $this->get(route('public.application.create'))->assertOk();
        $this->from(route('public.application.create'))->post(route('public.application.store'), [
            'submission_token' => $this->app['session.store']->get('public_application_submission_token'),
            'academic_status' => 'new_student', 'program_id' => $program->id,
            'first_name' => 'Juan', 'last_name' => 'Cruz', 'gender' => 'male',
            'birthdate' => '2005-01-01', 'email' => 'invalid-guardian@example.test',
            'ack_enrollment' => 'on', 'guardian_name' => str_repeat('a', 256),
            'guardian_contact' => str_repeat('1', 51),
        ])->assertSessionHasErrors(['guardian_name', 'guardian_contact']);
        $response = $this->get(route('public.application.create'))->assertOk();
        $xpath = $this->personalFormXPath($response->getContent());
        $this->assertTrue($xpath->query('//*[@id="guardianDetails"]')->item(0)->hasAttribute('open'));
        $this->assertSame(str_repeat('1', 51), $xpath->query('//*[@id="guardian_contact"]')->item(0)->getAttribute('value'));
        $this->assertFalse(Application::where('email', 'invalid-guardian@example.test')->exists());
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
