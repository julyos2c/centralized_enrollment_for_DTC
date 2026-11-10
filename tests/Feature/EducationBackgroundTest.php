<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Program;
use App\Models\School;
use App\Models\ShsClassification;
use App\Models\ShsCurriculum;
use App\Support\AcademicYears;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Tests\SeedsRoles;
use Tests\TestCase;

class EducationBackgroundTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        Mail::fake();
        config(['services.application_submission.per_ip_per_hour' => 100]);
    }

    public function test_migration_seeds_both_shs_curricula(): void
    {
        $k12 = ShsCurriculum::where('code', 'k12_2016')->firstOrFail();
        $strengthened = ShsCurriculum::where('code', 'strengthened_2025')->firstOrFail();

        $this->assertSame(2017, $k12->first_graduation_year);
        $this->assertSame(['ABM', 'HUMSS', 'STEM', 'GAS'], $k12->classifications()->where('track', 'Academic Track')->pluck('code')->all());
        $this->assertTrue($k12->classifications()->where('track', 'like', 'Technical-Vocational%')->exists());
        $this->assertTrue($k12->classifications()->where('code', 'SPORTS')->exists());
        $this->assertTrue($k12->classifications()->where('code', 'ARTS')->exists());

        $this->assertSame('Elective cluster', $strengthened->choice_label);
        $this->assertSame(4, $strengthened->classifications()->where('track', 'Academic Track')->count());
        $this->assertSame(10, $strengthened->classifications()->where('track', 'like', 'Technical-Professional%')->count());
        $this->assertFalse($strengthened->coversGraduationYear(2017));
        $this->assertTrue($k12->coversGraduationYear(2017));
    }

    public function test_school_search_endpoint(): void
    {
        $school = $this->school();
        School::create(['name' => 'Hidden Academy', 'deped_school_id' => '499998', 'is_active' => false]);

        $this->getJson(route('public.application.schools', ['q' => 'saint aug']))
            ->assertOk()->assertJsonPath('results.0.name', 'Saint Augustine Academy, Inc.')
            ->assertJsonPath('results.0.deped_school_id', '404297')
            ->assertJsonPath('results.0.city_code', '0702201000');
        $this->getJson(route('public.application.schools', ['q' => '404297']))->assertJsonPath('results.0.id', $school->id);
        $this->getJson(route('public.application.schools', ['q' => 'hidden']))->assertJsonCount(0, 'results');
        $this->getJson(route('public.application.schools', ['q' => 's']))->assertJsonCount(0, 'results');
        $this->getJson(route('public.application.schools', ['q' => '%']))->assertOk()->assertJsonCount(0, 'results');

        $this->getJson(route('public.application.shs-classifications'))
            ->assertOk()->assertJsonCount(2, 'curricula')->assertJsonPath('curricula.0.code', 'k12_2016');
    }

    public function test_listed_school_and_strand_are_stored_with_readable_text(): void
    {
        $school = $this->school();
        $stem = ShsClassification::where('code', 'STEM')->firstOrFail();

        $application = $this->submit([
            'school_id' => $school->id,
            'most_recent_school' => 'whatever the browser sent',
            'school_sector' => 'public',
            'school_region_code' => '0700000000',
            'school_province_code' => '0702200000',
            'school_city_code' => '0702201000',
            'school_barangay' => 'poblacion',
            'school_year_started' => '2015-2016',
            'school_year_completed' => '2017-2018',
            'education_status' => 'Graduated',
            'shs_curriculum_id' => $stem->shs_curriculum_id,
            'shs_strand_selection' => (string) $stem->id,
        ]);

        $this->assertSame($school->id, $application->school_id);
        $this->assertSame('Saint Augustine Academy, Inc.', $application->most_recent_school);
        $this->assertSame('404297', $application->school_deped_id);
        $this->assertSame('private', $application->school_sector, 'The school record decides the sector.');
        $this->assertSame('0702201000', $application->school_city_code);
        $this->assertSame('Poblacion', $application->school_barangay);
        $this->assertSame('Poblacion, Alcantara, Cebu', $application->school_location);
        $this->assertSame('Science, Technology, Engineering and Mathematics (STEM) (Academic Track)', $application->shs_strand);
        $this->assertSame($stem->id, $application->shs_classification_id);
        $this->assertSame('selected', $application->shs_strand_choice);
        $this->assertSame('2015-2016', $application->school_year_started);

        $this->actingAs($this->makeUserWithRole('registrar'))
            ->get(route('registrar.applications.show', $application))
            ->assertOk()->assertSee('DepEd School ID 404297')->assertSee('2015-2016')->assertSee('K to 12 SHS Curriculum');
    }

    public function test_unlisted_school_other_strand_other_year_and_other_status(): void
    {
        $curriculum = ShsCurriculum::where('code', 'k12_2016')->firstOrFail();
        $application = $this->submit([
            'most_recent_school' => 'sitio ilaya community high school',
            'school_sector' => 'public',
            'school_year_started' => '__other',
            'school_year_started_other' => '2016–2017',
            'school_year_completed' => '__other',
            'school_year_completed_other' => '2018–2019',
            'education_status' => 'Other',
            'education_status_other' => 'Alternative Learning System',
            'shs_curriculum_id' => $curriculum->id,
            'shs_strand_selection' => 'other',
            'shs_strand' => 'TVL – Cookery',
        ]);

        $this->assertNull($application->school_id);
        $this->assertSame('Sitio Ilaya Community High School', $application->most_recent_school);
        $this->assertSame('public', $application->school_sector);
        $this->assertSame('2016–2017', $application->school_year_started);
        $this->assertSame('2018–2019', $application->school_year_completed);
        $this->assertSame('Other', $application->education_status);
        $this->assertSame('Alternative Learning System', $application->education_status_other);
        $this->assertSame('other', $application->shs_strand_choice);
        $this->assertSame('TVL – Cookery', $application->shs_strand);

        $notSure = $this->submit(['shs_curriculum_id' => $curriculum->id, 'shs_strand_selection' => 'not_sure']);
        $this->assertSame('not_sure', $notSure->shs_strand_choice);
        $this->assertSame('Not sure (K to 12 SHS Curriculum)', $notSure->shs_strand);
    }

    public function test_other_choices_need_their_specification(): void
    {
        $curriculum = ShsCurriculum::where('code', 'k12_2016')->firstOrFail();
        $this->assertRejected(['education_status' => 'Other'], 'education_status_other');
        $this->assertRejected(['school_year_completed' => '__other'], 'school_year_completed_other');
        $this->assertRejected(['school_year_started' => '__other'], 'school_year_started_other');
        $this->assertRejected(['shs_curriculum_id' => $curriculum->id, 'shs_strand_selection' => 'other'], 'shs_strand');
    }

    public function test_years_status_and_curriculum_must_agree(): void
    {
        Carbon::setTestNow('2026-11-10 10:00:00');
        $strengthened = ShsCurriculum::where('code', 'strengthened_2025')->firstOrFail();
        $k12Strand = ShsClassification::where('code', 'GAS')->firstOrFail();

        $this->assertRejected(['school_year_started' => '2019-2020', 'school_year_completed' => '2017-2018'], 'school_year_started');
        $this->assertRejected(['education_status' => 'Currently attending', 'school_year_completed' => '2020-2021'], 'school_year_completed');
        $this->assertRejected(['education_status' => 'Graduated', 'school_year_completed' => '2027-2028'], 'school_year_completed');
        $this->assertRejected(['education_status' => 'Graduated', 'school_year_completed' => '2019-2020', 'shs_curriculum_id' => $strengthened->id], 'shs_curriculum_id');
        // A strand must belong to the chosen curriculum.
        $this->assertRejected(['shs_curriculum_id' => $strengthened->id, 'shs_strand_selection' => (string) $k12Strand->id], 'shs_strand_selection');

        $current = $this->submit(['education_status' => 'Currently attending', 'school_year_completed' => AcademicYears::current()]);
        $this->assertSame('2026-2027', $current->school_year_completed);
        Carbon::setTestNow();
    }

    public function test_other_applicant_types_keep_plain_text_and_ignore_shs_fields(): void
    {
        $school = $this->school();
        $application = $this->submit([
            'academic_status' => 'transferee',
            'education_level' => 'College',
            'most_recent_school' => 'cebu normal university',
            'school_location' => 'cebu city',
            'school_year_completed' => '2023-2024',
            'education_status' => 'Transferred',
            'school_id' => $school->id,
            'shs_curriculum_id' => ShsCurriculum::first()->id,
            'shs_strand_selection' => 'not_sure',
            'school_region_code' => '0700000000',
        ]);

        $this->assertSame('Cebu Normal University', $application->most_recent_school);
        $this->assertSame('Cebu City', $application->school_location);
        $this->assertSame('Transferred', $application->education_status);
        $this->assertNull($application->school_id);
        $this->assertNull($application->shs_curriculum_id);
        $this->assertNull($application->shs_strand_choice);
        $this->assertNull($application->school_region_code);
    }

    public function test_importer_adds_new_schools_fills_gaps_and_never_overwrites(): void
    {
        $edited = School::create(['name' => 'Registrar Edited Name', 'deped_school_id' => '404297', 'sector' => 'private', 'source' => 'manual']);
        $csv = storage_path('framework/testing-schools.csv');
        File::put($csv, implode("\n", [
            'school_id,school_name,region,province,municipality,program_offerings',
            '404297,Saint Augustine Academy Inc.,Region VII,Cebu,Alcantara,ABM|GAS',
            '400001,Danao Test Academy,Region VII,Bohol,Danao,STEM|TVL',
            '400002,Manila District School,NCR,Metro Manila,Malate,HUMSS',
            '12345,Bad ID School,Region VII,Cebu,Alcantara,GAS',
        ]));

        $this->artisan('schools:import', ['file' => $csv, '--source' => 'test'])->assertSuccessful();
        File::delete($csv);

        $edited->refresh();
        $this->assertSame('Registrar Edited Name', $edited->name, 'Existing names are kept.');
        $this->assertSame('0702201000', $edited->city_code, 'Missing location is filled in.');
        $this->assertSame(['ABM', 'GAS'], $edited->offerings);

        $danao = School::where('deped_school_id', '400001')->firstOrFail();
        $this->assertSame('0701218000', $danao->city_code);
        $this->assertSame('0701200000', $danao->province_code);
        $this->assertSame('1380600000', School::where('deped_school_id', '400002')->value('city_code'), 'Manila districts map to the City of Manila.');
        $this->assertDatabaseMissing('schools', ['name' => 'Bad ID School']);
        $this->assertSame(3, School::count());
    }

    public function test_staff_manage_schools_and_classifications(): void
    {
        $registrar = $this->makeUserWithRole('registrar');
        $this->actingAs($registrar)->get(route('registrar.admissions.schools.index'))->assertOk();
        $this->actingAs($registrar)->get(route('registrar.admissions.schools.create'))->assertOk()->assertSee('data-address-picker', false);

        $this->actingAs($registrar)->post(route('registrar.admissions.schools.store'), [
            'name' => 'Danao  National High School', 'deped_school_id' => '303030', 'sector' => 'public',
            'region_code' => '0700000000', 'province_code' => '0701200000', 'city_code' => '0701218000',
            'offerings' => ['STEM', 'GAS'], 'is_active' => '1',
        ])->assertRedirect();
        $school = School::where('deped_school_id', '303030')->firstOrFail();
        $this->assertSame('Danao National High School', $school->name);
        $this->assertSame('Danao, Bohol', $school->locationLabel());

        $this->actingAs($registrar)->post(route('registrar.admissions.schools.store'), [
            'name' => 'Wrong City', 'region_code' => '0700000000', 'province_code' => '0701200000', 'city_code' => '0730600000',
        ])->assertSessionHasErrors('city_code');
        $this->actingAs($registrar)->post(route('registrar.admissions.schools.store'), ['name' => 'Duplicate', 'deped_school_id' => '303030'])
            ->assertSessionHasErrors('deped_school_id');

        $this->actingAs($registrar)->post(route('registrar.admissions.schools.toggle', $school));
        $this->assertFalse($school->fresh()->is_active);

        $this->actingAs($registrar)->get(route('registrar.admissions.shs.index'))->assertOk()->assertSee('Strengthened SHS Curriculum');
        $k12 = ShsCurriculum::where('code', 'k12_2016')->firstOrFail();
        $this->actingAs($registrar)->post(route('registrar.admissions.shs.classifications.store', $k12), [
            'track' => 'Technical-Vocational-Livelihood (TVL) Track', 'name' => 'TVL – Maritime', 'offering_key' => 'TVL', 'is_active' => '1',
        ])->assertRedirect();
        $maritime = ShsClassification::where('name', 'TVL – Maritime')->firstOrFail();
        $this->actingAs($registrar)->put(route('registrar.admissions.shs.classifications.update', $maritime), [
            'track' => $maritime->track, 'name' => $maritime->name, 'is_active' => '0',
        ])->assertRedirect();
        $this->assertFalse($maritime->fresh()->is_active);
        $this->getJson(route('public.application.shs-classifications'))->assertJsonMissing(['name' => 'TVL – Maritime']);

        foreach (['cashier', 'student', 'new_applicant'] as $role) {
            $this->actingAs($this->makeUserWithRole($role))->get(route('registrar.admissions.schools.index'))->assertForbidden();
            $this->actingAs($this->makeUserWithRole($role))->get(route('registrar.admissions.shs.index'))->assertForbidden();
        }
    }

    private function school(): School
    {
        return School::create([
            'name' => 'Saint Augustine Academy, Inc.', 'deped_school_id' => '404297', 'sector' => 'private',
            'region_code' => '0700000000', 'province_code' => '0702200000', 'city_code' => '0702201000',
            'offerings' => ['ABM', 'GAS', 'TVL'], 'source' => 'test',
        ]);
    }

    private function payload(array $overrides): array
    {
        $this->get(route('public.application.create'))->assertOk();

        return array_merge([
            'submission_token' => $this->app['session.store']->get('public_application_submission_token'),
            'academic_status' => 'new_student', 'program_id' => Program::factory()->create()->id,
            'first_name' => 'Edu', 'last_name' => 'Applicant', 'gender' => 'female',
            'birthdate' => '2002-01-01', 'email' => uniqid('edu-', true) . '@example.test', 'ack_enrollment' => 'on',
        ], $overrides);
    }

    private function submit(array $overrides): Application
    {
        $payload = $this->payload($overrides);
        $this->post(route('public.application.store'), $payload)
            ->assertSessionHasNoErrors()->assertRedirect(route('public.application.success'));

        return Application::where('email', $payload['email'])->firstOrFail();
    }

    private function assertRejected(array $overrides, string $field): void
    {
        $payload = $this->payload($overrides);
        $this->from(route('public.application.create'))->post(route('public.application.store'), $payload)
            ->assertSessionHasErrors($field);
        $this->assertDatabaseMissing('applications', ['email' => $payload['email']]);
    }
}
