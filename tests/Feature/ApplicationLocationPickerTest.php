<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Program;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\SeedsRoles;
use Tests\TestCase;

class ApplicationLocationPickerTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        config(['services.application_submission.per_ip_per_hour' => 100]);
        Mail::fake();
    }

    public function test_location_endpoints_return_a_consistent_searchable_hierarchy_including_independent_cities(): void
    {
        $this->getJson(route('public.application.locations.regions'))
            ->assertOk()
            ->assertJsonFragment(['code' => '1800000000', 'name' => 'Negros Island Region (NIR)']);

        $areas = $this->getJson(route('public.application.locations.areas', ['region' => '0700000000']))
            ->assertOk()->json();
        $this->assertContains(['code' => '0701200000', 'name' => 'Bohol', 'type' => 'province'], $areas);
        $this->assertContains(['code' => '0730600000', 'name' => 'City of Cebu', 'type' => 'city'], $areas);

        $this->getJson(route('public.application.locations.cities', ['province' => '0701200000']))
            ->assertOk()->assertJsonFragment(['code' => '0701218000', 'name' => 'Danao', 'type' => 'city']);
        $this->getJson(route('public.application.locations.areas', ['region' => '9999999999']))->assertNotFound();
    }

    public function test_valid_province_city_and_independent_city_codes_are_persisted_with_canonical_names(): void
    {
        $bohol = $this->submitLocation([
            'region_code' => '0700000000',
            'province_code' => '0701200000',
            'city_code' => '0701218000',
            'region' => 'tampered region',
            'province' => 'tampered province',
            'city' => 'tampered city',
            'postal_code' => '6004',
        ], 'bohol-location@example.test');

        $this->assertSame('Region VII (Central Visayas)', $bohol->region);
        $this->assertSame('Bohol', $bohol->province);
        $this->assertSame('Danao', $bohol->city);
        $this->assertSame('0700000000', $bohol->region_code);
        $this->assertSame('0701200000', $bohol->province_code);
        $this->assertSame('0701218000', $bohol->city_code);
        $this->assertSame('6004', $bohol->postal_code);

        $cebu = $this->submitLocation([
            'region_code' => '0700000000',
            'city_code' => '0730600000',
        ], 'independent-city@example.test');

        $this->assertSame('City of Cebu', $cebu->city);
        $this->assertNull($cebu->province);
        $this->assertNull($cebu->province_code);
        $this->assertSame('0730600000', $cebu->city_code);
    }

    public function test_invalid_codes_and_parent_child_combinations_are_rejected(): void
    {
        $this->assertLocationRejected([
            'region_code' => '9999999999',
            'province_code' => '0701200000',
            'city_code' => '0701218000',
        ], 'region_code');

        $this->assertLocationRejected([
            'region_code' => '0600000000',
            'province_code' => '0701200000',
            'city_code' => '0701218000',
        ], 'province_code');

        $this->assertLocationRejected([
            'region_code' => '0700000000',
            'province_code' => '0701200000',
            'city_code' => '0730600000',
        ], 'city_code');
    }

    public function test_location_is_optional_and_form_contains_labeled_manual_address_and_draft_fields(): void
    {
        $this->get(route('public.application.create'))
            ->assertOk()
            ->assertSee('<label for="region_text">Region</label>', false)
            ->assertSee('name="region" id="region_text"', false)
            ->assertSee('<label for="province_text">Province</label>', false)
            ->assertSee('name="province" id="province_text"', false)
            ->assertSee('<label for="city_text">City / Municipality</label>', false)
            ->assertSee('name="city" id="city_text"', false)
            ->assertSee('autocomplete="address-level1"', false)
            ->assertSee('autocomplete="address-level2"', false)
            ->assertDontSee('id="locPickerSearch"', false)
            ->assertSee('name="region_code"', false)
            ->assertSee('name="province_code"', false)
            ->assertSee('name="city_code"', false);

        $application = $this->submitLocation([], 'no-location@example.test');
        $this->assertNull($application->region_code);
        $this->assertNull($application->province_code);
        $this->assertNull($application->city_code);
    }

    public function test_submitted_labels_without_verified_codes_are_not_saved_for_philippine_addresses(): void
    {
        $application = $this->submitLocation([
            'region' => 'Forged region',
            'province' => 'Forged province',
            'city' => 'Forged city',
        ], 'unverified-labels@example.test');

        $this->assertNull($application->region);
        $this->assertNull($application->province);
        $this->assertNull($application->city);
    }

    public function test_international_applicants_can_provide_a_manual_city_or_province(): void
    {
        $application = $this->submitLocation([
            'country' => 'United States',
            'international_location' => 'San Diego, California',
            'region_code' => '0700000000',
            'province_code' => '0701200000',
            'city_code' => '0701218000',
        ], 'international-location@example.test');

        $this->assertSame('San Diego, California', $application->city);
        $this->assertNull($application->province);
        $this->assertNull($application->region);
        $this->assertNull($application->region_code);
        $this->assertNull($application->city_code);
    }

    private function submitLocation(array $location, string $email): Application
    {
        $program = Program::factory()->create();
        $this->get(route('public.application.create'));
        $payload = array_merge([
            'academic_status' => 'new_student',
            'program_id' => $program->id,
            'submission_token' => $this->app['session.store']->get('public_application_submission_token'),
            'first_name' => 'Location',
            'last_name' => 'Applicant',
            'gender' => 'female',
            'birthdate' => '2005-01-01',
            'email' => $email,
            'ack_enrollment' => 'on',
            'country' => 'Philippines',
        ], $location);

        $this->post(route('public.application.store'), $payload)
            ->assertRedirect(route('public.application.success'));

        return Application::where('email', $email)->firstOrFail();
    }

    private function assertLocationRejected(array $location, string $error): void
    {
        $program = Program::factory()->create();
        $email = uniqid('bad-location-', true) . '@example.test';
        $this->get(route('public.application.create'));
        $this->from(route('public.application.create'))->post(route('public.application.store'), array_merge([
            'academic_status' => 'new_student',
            'program_id' => $program->id,
            'submission_token' => $this->app['session.store']->get('public_application_submission_token'),
            'first_name' => 'Invalid',
            'last_name' => 'Location',
            'gender' => 'female',
            'birthdate' => '2005-01-01',
            'email' => $email,
            'ack_enrollment' => 'on',
        ], $location))->assertRedirect(route('public.application.create'))->assertSessionHasErrors($error);
        $this->assertDatabaseMissing('applications', ['email' => $email]);
    }
}
