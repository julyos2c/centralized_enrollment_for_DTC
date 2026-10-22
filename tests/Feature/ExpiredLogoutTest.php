<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SeedsRoles;
use Tests\TestCase;

class ExpiredLogoutTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        // Laravel normally bypasses CSRF during tests; exercise the real check here.
        $this->app->bind(VerifyCsrfToken::class, function ($app) {
            return new class($app, $app['encrypter']) extends VerifyCsrfToken {
                protected function runningUnitTests() { return false; }
            };
        });
    }

    public function test_expired_guest_logout_redirects_to_a_safe_get_instead_of_an_error(): void
    {
        $this->withSession(['_token' => 'fresh-token'])->post(route('logout'), ['_token' => 'old-token'])
            ->assertStatus(303)->assertRedirect(route('landing'));
        $this->assertGuest();
        $this->get(route('logout.confirm'))->assertRedirect(route('landing'));
    }

    public function test_stale_logout_cannot_log_out_an_authenticated_user_without_fresh_confirmation(): void
    {
        $user = $this->makeUserWithRole('student');
        $this->actingAs($user)->withSession(['_token' => 'fresh-token'])
            ->post(route('logout'), ['_token' => 'old-token'])
            ->assertStatus(303)->assertRedirect(route('logout.confirm'));
        $this->assertAuthenticatedAs($user);
        $this->get(route('logout.confirm'))->assertOk()->assertSee('name="_token"', false)
            ->assertSee('method="POST"', false);
        $this->assertAuthenticatedAs($user);
        $this->withSession(['_token' => 'fresh-token'])->post(route('logout'), ['_token' => 'fresh-token'])
            ->assertRedirect(route('landing'));
        $this->assertGuest();
    }

    public function test_valid_logout_still_works_for_each_account_role(): void
    {
        foreach (['admin', 'registrar', 'cashier', 'student', 'alumni', 'new_applicant'] as $role) {
            $user = $this->makeUserWithRole($role);
            $this->actingAs($user)->withSession(['_token' => 'fresh-token'])
                ->post(route('logout'), ['_token' => 'fresh-token'])->assertRedirect(route('landing'));
            $this->assertGuest();
        }
    }

    public function test_stale_json_logout_and_other_csrf_failures_remain_protected(): void
    {
        $this->withSession(['_token' => 'fresh-token'])->postJson(route('logout'), ['_token' => 'old-token'])
            ->assertStatus(419)->assertJsonPath('redirect_url', route('landing'));
        $this->withSession(['_token' => 'fresh-token'])->post(route('login'), ['_token' => 'old-token'])
            ->assertStatus(419);
    }
}
