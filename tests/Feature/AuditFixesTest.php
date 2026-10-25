<?php

namespace Tests\Feature;

use App\Models\{CourseSubject, Enrollment, Program, Setting, User};
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Hash, Mail, Notification, RateLimiter, Route, URL};
use Tests\SeedsRoles;
use Tests\TestCase;

class AuditFixesTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        Mail::fake();
        Notification::fake();
    }

    public function test_regular_subjects_cannot_be_saved_under_a_different_semester(): void
    {
        Setting::set('current_school_year', '2026-2027');
        Setting::set('current_semester', '1st');
        $user = $this->makeUserWithRole('student');
        $program = Program::factory()->create();
        $subject = CourseSubject::factory()->for($program)->yearSem(1, 2)->create();
        $data = ['program_id' => $program->id, 'year_level' => 1, 'semester' => 2, 'subject_ids' => [$subject->id]];
        $this->actingAs($user)->post(route('portal.enrollment.store'), $data)->assertSessionHasErrors('semester');
        $data['semester'] = 1;
        $this->post(route('portal.enrollment.store'), $data)->assertSessionHasErrors('subject_ids');
        $this->assertSame(0, Enrollment::where('user_id', $user->id)->count());
        $subject->update(['semester' => 1]);
        $this->post(route('portal.enrollment.store'), $data)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('enrollments', ['user_id' => $user->id, 'semester' => 1]);
    }

    public function test_email_change_requires_a_signed_verification_before_protected_access(): void
    {
        $user = $this->makeUserWithRole('student', ['email_verified_at' => now()]);
        $this->actingAs($user)->post(route('portal.profile.update'), ['name' => $user->name, 'email' => 'changed@example.test'])->assertSessionHasNoErrors();
        $user->refresh();
        $this->assertNull($user->email_verified_at);
        $this->assertTrue($user->email_verification_required);
        $this->get(route('portal.dashboard'))->assertRedirect(route('verification.notice'));
        $this->getJson(route('search', ['q' => 'test']))->assertForbidden();
        $this->post(route('verification.send'))->assertRedirect();
        Notification::assertSentTo($user, VerifyEmail::class);
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(20), ['id' => $user->id, 'hash' => sha1($user->email)]);
        $this->get($url)->assertRedirect(route('login'));
        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->assertFalse($user->fresh()->email_verification_required);
        $this->get(route('portal.dashboard'))->assertOk();
    }

    public function test_unchanged_email_preserves_verification_and_admin_changes_also_clear_it(): void
    {
        $user = $this->makeUserWithRole('student', ['email_verified_at' => now()]);
        $this->actingAs($user)->post(route('portal.profile.update'), ['name' => $user->name, 'email' => $user->email])->assertSessionHasNoErrors();
        $this->assertNotNull($user->fresh()->email_verified_at);
        $admin = $this->makeUserWithRole('admin');
        $this->actingAs($admin)->put(route('admin.users.update', $user), ['name' => $user->name, 'email' => 'admin-changed@example.test', 'status' => 'active'])->assertSessionHasNoErrors();
        $this->assertNull($user->fresh()->email_verified_at);
        $this->assertTrue($user->fresh()->email_verification_required);
    }

    public function test_verification_link_cannot_verify_a_different_user_or_an_old_email(): void
    {
        $user = $this->makeUserWithRole('student');
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(20), ['id' => $user->id, 'hash' => sha1($user->email)]);
        $other = $this->makeUserWithRole('student');
        $this->actingAs($other)->get($url)->assertForbidden();
        $user->update(['email' => 'newer@example.test']);
        $this->actingAs($user)->get($url)->assertForbidden();
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_login_setting_changes_enforcement_on_subsequent_requests(): void
    {
        $user = $this->makeUserWithRole('student', ['password' => Hash::make('Password123!')]);
        Setting::set('max_login_attempts', 3);
        for ($i = 0; $i < 3; $i++) {
            $this->post(route('login'), ['email' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
        }
        $this->post(route('login'), ['email' => $user->email, 'password' => 'Password123!'])->assertSessionHasErrors('email');
        $this->assertGuest();
        Setting::set('max_login_attempts', 4);
        $this->post(route('login'), ['email' => $user->email, 'password' => 'Password123!'])->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($user);
    }

    public function test_saved_timeout_applies_to_an_existing_session_and_polling_cannot_renew_it(): void
    {
        config(['session.lifetime' => 120]);
        Setting::set('session_timeout', 30);
        $user = $this->makeUserWithRole('student');
        $this->actingAs($user)->get(route('portal.dashboard'))->assertOk();
        $last = session('last_user_activity_at');
        $this->travel(4)->minutes();
        $this->getJson(route('notifications.poll'))->assertOk();
        $this->assertSame($last, session('last_user_activity_at'));
        Setting::set('session_timeout', 5);
        $this->travel(1)->minutes();
        $this->getJson(route('notifications.poll'))->assertUnauthorized();
        $this->assertGuest();
        $this->get(route('portal.dashboard'))->assertRedirect(route('login'));
    }

    public function test_password_settings_apply_to_admin_and_profile_password_changes(): void
    {
        Setting::set('min_password_length', 12);
        Setting::set('require_special_chars', '1');
        $admin = $this->makeUserWithRole('admin', ['password' => Hash::make('OldPassword123!')]);
        foreach (['short!', 'LongPassword123', 'Long Password123'] as $password) {
            $this->actingAs($admin)->post(route('admin.users.store'), ['name' => 'Staff', 'email' => 'staff@example.test', 'role' => 'cashier', 'password' => $password])->assertSessionHasErrors('password');
            $this->post(route('profile.password'), ['current_password' => 'OldPassword123!', 'password' => $password, 'password_confirmation' => $password])->assertSessionHasErrors('password');
        }
        $this->post(route('admin.users.store'), ['name' => 'Staff', 'email' => 'staff@example.test', 'role' => 'cashier', 'password' => 'LongPassword123!'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['email' => 'staff@example.test']);
    }

    public function test_security_settings_have_server_side_bounds(): void
    {
        $admin = $this->makeUserWithRole('admin');
        $this->actingAs($admin)->put(route('admin.settings.security.update'), ['min_password_length' => 6, 'require_special_chars' => 'bad', 'session_timeout' => 1441, 'max_login_attempts' => 21])
            ->assertSessionHasErrors(['min_password_length', 'require_special_chars', 'session_timeout', 'max_login_attempts']);
    }

    public function test_recovery_limits_are_shared_across_endpoints_and_do_not_lock_out_other_ips(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('password.email'), ['email' => 'missing@example.test'])->assertRedirect();
        }
        $this->post(route('password.email'), ['email' => 'missing@example.test'])->assertStatus(429);
        $this->post(route('password.update'), ['email' => 'missing@example.test'])->assertStatus(429);
        $this->post(route('password.setup.store'), ['email' => 'missing@example.test'])->assertStatus(429);
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.88'])->post(route('password.email'), ['email' => 'missing@example.test'])->assertRedirect();
    }

    public function test_create_route_is_absent_and_existing_modal_crud_routes_remain(): void
    {
        $this->assertFalse(Route::has('admin.users.create'));
        foreach (['index', 'store', 'edit', 'update', 'destroy'] as $action) {
            $this->assertTrue(Route::has('admin.users.'.$action));
        }
    }

    public function test_recovery_ip_budget_also_limits_rotating_email_addresses(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $this->post(route('password.email'), ['email' => 'missing'.$i.'@example.test'])->assertRedirect();
        }
        $this->post(route('password.email'), ['email' => 'another@example.test'])->assertStatus(429);
        $this->travel(61)->seconds();
        $this->post(route('password.email'), ['email' => 'another@example.test'])->assertRedirect();
    }

    public function test_each_recovery_submission_route_limits_invalid_attempts(): void
    {
        foreach (['password.update', 'password.setup.store', 'password.activation.resend'] as $index => $route) {
            $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.'.($index + 20)]);
            for ($i = 0; $i < 5; $i++) {
                $this->post(route($route), ['email' => 'unknown@example.test'])->assertRedirect();
            }
            $this->post(route($route), ['email' => 'unknown@example.test'])->assertStatus(429);
        }
    }

    public function test_setup_page_is_throttled_and_tokens_are_not_consumed_by_invalid_passwords(): void
    {
        $user = $this->makeUserWithRole('new_applicant');
        $token = \Illuminate\Support\Facades\Password::broker()->createToken($user);
        for ($i = 0; $i < 30; $i++) {
            $this->get(route('password.setup.show', ['token' => $token, 'email' => $user->email]))->assertOk();
        }
        $this->get(route('password.setup.show', ['token' => $token, 'email' => $user->email]))->assertStatus(429);
        Setting::set('min_password_length', 12);
        $this->post(route('password.setup.store'), ['email' => $user->email, 'token' => $token, 'password' => 'TooShort!', 'password_confirmation' => 'TooShort!'])->assertSessionHasErrors('password');
        $this->assertTrue(\Illuminate\Support\Facades\Password::broker()->tokenExists($user, $token));
        $this->post(route('password.setup.store'), ['email' => $user->email, 'token' => $token, 'password' => 'LongEnoughPassword!', 'password_confirmation' => 'LongEnoughPassword!'])->assertSessionHasNoErrors();
        $this->assertFalse(\Illuminate\Support\Facades\Password::broker()->tokenExists($user, $token));
    }

    public function test_password_policy_applies_to_reset_activation_and_required_password_endpoints(): void
    {
        Setting::set('min_password_length', 14);
        Setting::set('require_special_chars', '1');
        foreach (['password.update', 'password.setup.store', 'portal.activation.store'] as $index => $route) {
            $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.'.($index + 60)]);
            $this->post(route($route), ['token' => str_repeat('a', 64), 'email' => 'unknown@example.test', 'password' => ['invalid'], 'password_confirmation' => ['invalid']])->assertSessionHasErrors('password');
            foreach (['Short123!', 'LongEnoughButNoSymbol'] as $password) {
                $this->post(route($route), ['token' => str_repeat('a', 64), 'email' => 'unknown@example.test', 'password' => $password, 'password_confirmation' => $password])->assertSessionHasErrors('password');
            }
        }
        $user = $this->makeUserWithRole('student', ['password' => Hash::make('Temporary123!')]);
        $user->forceFill(['must_change_password' => true])->save();
        $this->actingAs($user)->post(route('account.password.update'), ['current_password' => 'Temporary123!', 'password' => 'Short123!', 'password_confirmation' => 'Short123!'])->assertSessionHasErrors('password');
        $this->assertTrue($user->fresh()->must_change_password);
    }

    public function test_saved_password_policy_changes_take_effect_on_following_requests(): void
    {
        $admin = $this->makeUserWithRole('admin');
        $this->actingAs($admin)->put(route('admin.settings.security.update'), ['min_password_length' => 16, 'require_special_chars' => 1, 'session_timeout' => 15, 'max_login_attempts' => 4])->assertSessionHasNoErrors();
        $this->post(route('admin.users.store'), ['name' => 'Staff', 'email' => 'staff@example.test', 'role' => 'cashier', 'password' => 'OldValid123!'])->assertSessionHasErrors('password');
        $this->post(route('admin.users.store'), ['name' => 'Staff', 'email' => 'staff@example.test', 'role' => 'cashier', 'password' => 'NewLongPassword123!'])->assertSessionHasNoErrors();
    }

    public function test_verification_links_expire_and_api_access_respects_changed_email(): void
    {
        $user = $this->makeUserWithRole('student');
        $user->update(['email' => 'changed@example.test']);
        $url = URL::temporarySignedRoute('verification.verify', now()->subMinute(), ['id' => $user->id, 'hash' => sha1($user->email)]);
        $this->actingAs($user)->get($url)->assertForbidden();
        $this->getJson('/api/user')->assertForbidden();
        $this->assertNull($user->fresh()->email_verified_at);
    }
}
