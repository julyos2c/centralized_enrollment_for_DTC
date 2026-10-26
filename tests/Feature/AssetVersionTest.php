<?php

namespace Tests\Feature;

use App\Support\AssetVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SeedsRoles;
use Tests\TestCase;

class AssetVersionTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    private string $probe = 'css/zz-asset-version-probe.css';

    protected function tearDown(): void
    {
        @unlink(public_path($this->probe));
        parent::tearDown();
    }

    public function test_versioned_url_changes_when_the_file_changes(): void
    {
        file_put_contents(public_path($this->probe), 'a{}');
        touch(public_path($this->probe), 1_700_000_000);
        clearstatcache();
        $first = $this->freshUrl();

        file_put_contents(public_path($this->probe), 'a{color:red}');
        touch(public_path($this->probe), 1_700_000_100);
        clearstatcache();
        $second = $this->freshUrl();

        $this->assertMatchesRegularExpression('#/css/zz-asset-version-probe\.css\?v=[a-f0-9]{10}$#', $first);
        $this->assertNotSame($first, $second, 'A changed file must get a new URL so caches fetch it again.');
    }

    public function test_missing_file_falls_back_to_the_plain_asset_url(): void
    {
        $this->assertSame(asset('css/does-not-exist.css'), asset_v('css/does-not-exist.css'));
    }

    public function test_public_pages_reference_versioned_design_assets(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        foreach (['css/welcome.css', 'css/dtc-theme.css', 'css/public-navbar.css', 'js/dtc-app.js'] as $path) {
            $this->assertMatchesRegularExpression('#/' . preg_quote($path, '#') . '\?v=[a-f0-9]{10}"#', $html, "$path should be cache-busted");
        }
        $this->assertDoesNotMatchRegularExpression('#/(css|js)/[a-z0-9._-]+\.(css|js)"#', $html, 'No local CSS/JS should be referenced without a version.');
    }

    public function test_staff_pages_load_adminlte_theme_with_versioned_urls(): void
    {
        $this->seedRoles();
        $admin = $this->makeUserWithRole('admin', ['status' => 'active']);

        $html = $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#/css/dtc-theme\.css\?v=[a-f0-9]{10}"#', $html);
        $this->assertMatchesRegularExpression('#/js/dtc-app\.js\?v=[a-f0-9]{10}"#', $html);
    }

    private function freshUrl(): string
    {
        // Tokens are memoised per request; reset between assertions.
        $prop = new \ReflectionProperty(AssetVersion::class, 'tokens');
        $prop->setAccessible(true);
        $prop->setValue(null, []);

        return asset_v($this->probe);
    }
}
