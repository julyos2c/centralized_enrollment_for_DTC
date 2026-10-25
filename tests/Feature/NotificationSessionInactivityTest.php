<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SeedsRoles;
use Tests\TestCase;

class NotificationSessionInactivityTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        config(['session.lifetime' => 1]);
        $this->travelTo(\Carbon\Carbon::parse('2026-10-23 09:00:00'));
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        parent::tearDown();
    }

    public function test_polling_preserves_the_original_deadline_and_expires_an_idle_visible_session(): void
    {
        $user = $this->makeUserWithRole('student');
        $this->actingAs($user)->get(route('notifications.index'))->assertOk();
        $last = session('last_user_activity_at');
        $this->travel(30)->seconds();
        $this->getJson(route('notifications.poll'))->assertOk();
        $this->assertSame($last, session('last_user_activity_at'));
        $this->travel(29)->seconds();
        $this->getJson(route('notifications.poll'))->assertOk();
        $this->assertSame($last, session('last_user_activity_at'));
        $this->travel(1)->seconds();
        $this->getJson(route('notifications.poll'))->assertUnauthorized();
        $this->assertGuest();
        $this->getJson(route('notifications.poll'))->assertUnauthorized();
    }

    public function test_genuine_activity_renews_the_server_deadline_but_cashier_polling_does_not(): void
    {
        $user = $this->makeUserWithRole('cashier');
        $this->actingAs($user)->get(route('notifications.index'))->assertOk();
        $this->travel(45)->seconds();
        $this->get(route('notifications.index'))->assertOk();
        $renewed = session('last_user_activity_at');
        $this->travel(30)->seconds();
        $this->getJson(route('cashier.notifications.poll'))->assertOk();
        $this->assertSame($renewed, session('last_user_activity_at'));
        $this->travel(30)->seconds();
        $this->getJson(route('cashier.notifications.poll'))->assertUnauthorized();
    }

    public function test_locked_and_revoked_accounts_cannot_continue_polling(): void
    {
        foreach (['locked', 'revoked'] as $reason) {
            $user = $this->makeUserWithRole('student');
            $this->actingAs($user)->get(route('notifications.index'))->assertOk();
            User::findOrFail($user->id)->update($reason === 'locked'
                ? ['status' => 'locked'] : ['password' => bcrypt('ReplacedPassword123!')]);
            $this->getJson(route('notifications.poll'))->assertStatus($reason === 'locked' ? 403 : 401);
            $this->assertGuest();
        }
    }

    public function test_login_initializes_activity_and_expired_html_requests_cannot_renew_it(): void
    {
        $user = $this->makeUserWithRole('student', ['password' => bcrypt('Password123!')]);
        $this->post(route('login'), ['email' => $user->email, 'password' => 'Password123!'])->assertRedirect();
        $this->assertSame(now()->timestamp, session('last_user_activity_at'));
        $this->travel(61)->seconds();
        $this->get(route('notifications.index'))->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
