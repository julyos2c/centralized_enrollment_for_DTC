<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Program;
use App\Services\Psgc\PostalCodeMatcher;
use App\Services\PsgcLocationCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\SeedsRoles;
use Tests\TestCase;

class PsgcAddressDataTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    private string $storageRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        Mail::fake();
        config(['services.application_submission.per_ip_per_hour' => 100]);

        // Isolate storage/app/psgc so tests never touch the real storage copy.
        $this->storageRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'psgc-test-' . bin2hex(random_bytes(4));
        File::ensureDirectoryExists($this->storageRoot . '/app');
        $this->app->useStoragePath($this->storageRoot);
        PsgcLocationCatalog::flush();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->storageRoot);
        PsgcLocationCatalog::flush();
        parent::tearDown();
    }

    public function test_city_endpoint_returns_barangays_and_postal_codes(): void
    {
        $this->getJson(route('public.application.locations.city', ['city' => '0701218000']))
            ->assertOk()
            ->assertJsonFragment(['code' => '0701218009', 'name' => 'Poblacion'])
            ->assertJsonPath('postal_codes.0.code', '6344')
            ->assertHeader('X-PSGC-Version', app(PsgcLocationCatalog::class)->version());

        $this->getJson(route('public.application.locations.city', ['city' => '9999999999']))->assertNotFound();
        $this->get('/apply/locations/cities/not-a-code')->assertNotFound();
    }

    public function test_manila_lists_district_barangays_and_several_postal_codes(): void
    {
        $data = $this->getJson(route('public.application.locations.city', ['city' => '1380600000']))->assertOk()->json();

        $this->assertGreaterThan(800, count($data['barangays']));
        $this->assertSame('Barangay 1 (Tondo I/II)', $data['barangays'][0]['name']);
        $this->assertGreaterThan(5, count($data['postal_codes']));

        // NCR has no provinces: its cities come straight from the region.
        $areas = $this->getJson(route('public.application.locations.areas', ['region' => '1300000000']))->json();
        $this->assertSame(['city'], array_values(array_unique(array_column($areas, 'type'))));
    }

    public function test_responses_are_cacheable_and_revalidate_by_version(): void
    {
        $response = $this->get(route('public.application.locations.regions'))->assertOk();
        $this->assertStringContainsString('public', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('max-age=' . config('psgc.http_max_age'), $response->headers->get('Cache-Control'));
        $etag = $response->headers->get('ETag');
        $this->assertNotEmpty($etag);

        $this->get(route('public.application.locations.regions'), ['If-None-Match' => $etag])->assertStatus(304);
    }

    public function test_form_receives_host_independent_lookup_paths(): void
    {
        $html = $this->get(route('public.application.create'))->assertOk()->getContent();
        preg_match('/data-lookup="([^"]+)"/', $html, $match);
        $config = json_decode(html_entity_decode($match[1]), true);

        $this->assertSame('/apply/locations/regions', $config['regions']);
        $this->assertSame('/apply/locations/cities/:code', $config['city']);
        $this->assertSame(app(PsgcLocationCatalog::class)->version(), $config['version']);
        foreach (['regions', 'areas', 'cities', 'city'] as $key) {
            $this->assertStringNotContainsString('http', $config[$key]);
        }
    }

    public function test_submitted_barangay_is_stored_with_its_official_spelling(): void
    {
        $listed = $this->submit(['barangay' => '  poblacion '], 'listed-barangay@example.test');
        $this->assertSame('Poblacion', $listed->barangay);
        $this->assertSame('Danao', $listed->city);

        $manual = $this->submit(['barangay' => 'Sitio Bagong Lipunan'], 'manual-barangay@example.test');
        $this->assertSame('Sitio Bagong Lipunan', $manual->barangay);
    }

    public function test_independent_city_is_saved_without_a_province(): void
    {
        $application = $this->submit([
            'province_code' => '',
            'city_code' => '0730600000',
            'barangay' => 'lahug',
            'postal_code' => '6000',
        ], 'cebu-city@example.test');

        $this->assertSame('City of Cebu', $application->city);
        $this->assertNull($application->province);
        $this->assertSame('Lahug', $application->barangay);
        $this->assertSame('6000', $application->postal_code);
    }

    public function test_existing_applications_without_codes_still_display(): void
    {
        $legacy = Application::create([
            'reference_no' => 'APP-LEGACY-1', 'program_id' => Program::factory()->create()->id,
            'academic_status' => 'new_student', 'first_name' => 'Legacy', 'last_name' => 'Record',
            'gender' => 'male', 'birthdate' => '2005-01-01', 'email' => 'legacy@example.test',
            'status' => 'submitted', 'barangay' => 'Danao, Bohol', 'postal_code' => '6329',
        ]);

        $this->actingAs($this->makeUserWithRole('registrar'))
            ->get(route('registrar.applications.show', $legacy))
            ->assertOk()->assertSee('Danao, Bohol')->assertSee('6329');
    }

    public function test_sync_installs_a_validated_copy_that_the_catalog_prefers(): void
    {
        $source = $this->fixtureSource();
        config(['psgc.minimum_counts' => ['regions' => 1, 'provinces' => 1, 'cities' => 1, 'barangays' => 1]]);

        $this->artisan('psgc:sync', ['--source' => $source])->assertSuccessful();

        $installed = PsgcLocationCatalog::storagePath();
        $this->assertFileExists($installed . '/meta.json');
        $this->assertFileExists($installed . '/barangays/0700000000.json');

        PsgcLocationCatalog::flush();
        $catalog = app(PsgcLocationCatalog::class);
        $this->assertSame($installed, $catalog->dataPath());
        $this->assertSame([['code' => '0701218023', 'name' => 'Poblacion']], $catalog->barangays('0701218000'));
        $this->assertSame([['code' => '6344', 'area' => '']], $catalog->postalCodes('0701218000'));

        File::deleteDirectory($source);
    }

    public function test_failed_or_incomplete_sync_keeps_the_current_data(): void
    {
        $bundledVersion = app(PsgcLocationCatalog::class)->version();

        // Unreachable source: nothing is written.
        Http::fake(['*' => Http::response('Service Unavailable', 503)]);
        $this->artisan('psgc:sync', ['--source' => 'https://psgc.invalid/data'])->assertFailed();
        $this->assertDirectoryDoesNotExist(PsgcLocationCatalog::storagePath());

        // A source far below the expected size is rejected.
        $source = $this->fixtureSource();
        $this->artisan('psgc:sync', ['--source' => $source])->assertFailed();
        $this->assertDirectoryDoesNotExist(PsgcLocationCatalog::storagePath());

        PsgcLocationCatalog::flush();
        $this->assertSame($bundledVersion, app(PsgcLocationCatalog::class)->version());
        File::deleteDirectory($source);
    }

    public function test_incomplete_or_damaged_storage_copy_falls_back_to_bundled_data(): void
    {
        $storage = PsgcLocationCatalog::storagePath();
        File::ensureDirectoryExists($storage);
        File::put($storage . '/regions.json', '[]');

        PsgcLocationCatalog::flush();
        $catalog = app(PsgcLocationCatalog::class);
        $this->assertSame(PsgcLocationCatalog::bundledPath(), $catalog->dataPath());
        $this->assertCount(18, $catalog->regions());

        // A complete-looking copy whose files are corrupt also falls back.
        foreach (['regions', 'provinces', 'cities', 'postal'] as $file) {
            File::put($storage . "/{$file}.json", '{not json');
        }
        File::put($storage . '/meta.json', json_encode(['version' => 'broken']));
        PsgcLocationCatalog::flush();
        $this->assertCount(18, app(PsgcLocationCatalog::class)->regions());
        $this->assertNotEmpty(app(PsgcLocationCatalog::class)->cities('0701200000'));
    }

    public function test_postal_matcher_handles_spelling_variants_and_shared_codes(): void
    {
        $provinces = [['psgc_code' => '0101300000', 'name' => 'La Union', 'region_code' => '0100000000']];
        $cities = [
            ['psgc_code' => '0101308000', 'name' => 'Naguilian', 'province_code' => '0101300000', 'region_code' => '0100000000'],
            ['psgc_code' => '0101318000', 'name' => 'Sudipen', 'province_code' => '0101300000', 'region_code' => '0100000000'],
            ['psgc_code' => '0101320000', 'name' => 'City of San Fernando', 'province_code' => '0101300000', 'region_code' => '0100000000'],
        ];
        $postal = (new PostalCodeMatcher())->match([
            '2511' => ['area' => 'Naguillan', 'city' => '', 'province' => 'La Union'],
            '2520' => ['area' => 'Sudepen', 'city' => '', 'province' => 'La Union'],
            '2500' => ['area' => 'San Fernando City', 'city' => '', 'province' => 'La Union'],
            '9999' => ['area' => 'Nowhere', 'city' => '', 'province' => 'La Union'],
        ], $cities, $provinces);

        $this->assertSame('2511', $postal['0101308000'][0]['code']);
        $this->assertSame('2520', $postal['0101318000'][0]['code']);
        $this->assertSame('2500', $postal['0101320000'][0]['code']);
        $this->assertCount(3, $postal);
    }

    private function submit(array $location, string $email): Application
    {
        $program = Program::factory()->create();
        $this->get(route('public.application.create'));
        $this->post(route('public.application.store'), array_merge([
            'academic_status' => 'new_student', 'program_id' => $program->id,
            'submission_token' => $this->app['session.store']->get('public_application_submission_token'),
            'first_name' => 'Address', 'last_name' => 'Applicant', 'gender' => 'female',
            'birthdate' => '2005-01-01', 'email' => $email, 'ack_enrollment' => 'on',
            'country' => 'Philippines', 'region_code' => '0700000000',
            'province_code' => '0701200000', 'city_code' => '0701218000',
        ], $location))->assertSessionHasNoErrors()->assertRedirect(route('public.application.success'));

        return Application::where('email', $email)->firstOrFail();
    }

    /** A tiny but internally consistent PSGC source directory. */
    private function fixtureSource(): string
    {
        $dir = $this->storageRoot . '/fixture-' . bin2hex(random_bytes(3));
        File::ensureDirectoryExists($dir);
        $files = [
            'regions' => [['psgc_code' => '0700000000', 'name' => 'Region VII (Central Visayas)']],
            'provinces' => [['psgc_code' => '0701200000', 'name' => 'Bohol', 'region_code' => '0700000000']],
            'cities' => [['psgc_code' => '0701218000', 'name' => 'Danao', 'province_code' => '0701200000', 'region_code' => '0700000000', 'geographic_level' => 'Mun']],
            'barangays' => [['psgc_code' => '0701218023', 'name' => 'Poblacion', 'city_code' => '0701218000']],
            'zip_codes' => ['6344' => ['area' => 'Danao', 'city' => '', 'province' => 'Bohol']],
        ];
        foreach ($files as $name => $data) {
            File::put("{$dir}/{$name}.json", json_encode($data));
        }

        return $dir;
    }
}
