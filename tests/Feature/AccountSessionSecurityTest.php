<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\SeedsRoles;
use Tests\TestCase;

class AccountSessionSecurityTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_locking_an_account_rejects_its_existing_session_and_later_unlock_does_not_restore_it(): void
    {
        $user = $this->makeUserWithRole('student');
        $this->actingAs($user)->get(route('portal.dashboard'))->assertOk();
        User::findOrFail($user->id)->update(['status' => 'locked']);
        $this->getJson(route('search', ['q' => 'test']))->assertForbidden();
        $this->assertGuest();
        $user->refresh()->update(['status' => 'active']);
        $this->actingAs($user->fresh())->withSession(['auth_session_token' => null])
            ->getJson(route('search', ['q' => 'test']))->assertUnauthorized();
    }

    public function test_admin_reset_revokes_only_the_target_sessions_and_tokens(): void
    {
        $admin = $this->makeUserWithRole('admin');
        $target = $this->makeUserWithRole('student');
        $other = $this->makeUserWithRole('student');
        $target->forceFill(['remember_token' => 'old-remember-token'])->save();
        $oldStamp = $target->auth_session_token;
        $token = $target->createToken('old client')->plainTextToken;
        $other->createToken('unrelated client');
        $this->actingAs($admin)->put(route('admin.users.resetPassword', $target), [
            'password' => 'Temporary123!', 'password_confirmation' => 'Temporary123!',
        ])->assertRedirect(route('admin.users.index'));
        $this->assertAuthenticatedAs($admin);
        $target->refresh();
        $this->assertNotSame($oldStamp, $target->auth_session_token);
        $this->assertNotSame('old-remember-token', $target->remember_token);
        $this->assertTrue($target->must_change_password);
        $this->assertSame(0, $target->tokens()->count());
        $this->assertSame(1, $other->tokens()->count());
        $this->get(route('admin.dashboard'))->assertOk();
        $this->actingAs($target)->withSession(['auth_session_token' => $oldStamp])
            ->getJson(route('search', ['q' => 'test']))->assertUnauthorized();
        Auth::forgetGuards();
        $this->withHeader('Authorization', 'Bearer ' . $token)->getJson('/api/user')->assertUnauthorized();
    }

    public function test_old_remember_cookie_cannot_authenticate_after_a_password_reset(): void
    {
        $target = $this->makeUserWithRole('student');
        $target->forceFill(['remember_token' => 'remember-before-reset'])->save();
        $cookie = $target->id . '|remember-before-reset|' . $target->password;
        $name = Auth::guard('web')->getRecallerName();
        $target->forceFill(['password' => Hash::make('Replaced123!')])->save();
        Auth::forgetGuards();
        $this->withCookie($name, $cookie)->get(route('portal.dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_temporary_password_requires_a_valid_change_before_access_is_restored(): void
    {
        $user = $this->makeUserWithRole('student', ['password' => Hash::make('Temporary123!')]);
        $user->forceFill(['must_change_password' => true])->save();
        $this->post(route('login'), ['email' => $user->email, 'password' => 'Temporary123!'])->assertRedirect();
        $this->get(route('portal.dashboard'))->assertRedirect(route('account.password.edit'));
        $this->getJson(route('search', ['q' => 'test']))->assertForbidden();
        $this->get(route('account.password.edit'))->assertOk();
        $this->post(route('account.password.update'), [
            'current_password' => 'Wrong123!', 'password' => 'Permanent123!', 'password_confirmation' => 'Permanent123!',
        ])->assertSessionHasErrors('current_password');
        $this->assertTrue($user->fresh()->must_change_password);
        $this->post(route('account.password.update'), [
            'current_password' => 'Temporary123!', 'password' => 'Permanent123!', 'password_confirmation' => 'Permanent123!',
        ])->assertSessionHasNoErrors()->assertRedirect(route('login'));
        $this->assertFalse($user->fresh()->must_change_password);
        $this->get(route('portal.dashboard'))->assertOk();
    }

    public function test_profile_password_errors_do_not_flash_secrets_and_valid_changes_keep_the_current_session(): void
    {
        $user = $this->makeUserWithRole('student', ['password' => Hash::make('Current123!')]);
        $this->actingAs($user)->from(route('portal.profile.show'))->post(route('portal.profile.password'), [
            'current_password' => 'Incorrect123!', 'password' => 'Changed123!', 'password_confirmation' => 'Changed123!',
        ])->assertSessionHasErrors('current_password');
        $old = session()->getOldInput();
        foreach (['current_password', 'password', 'password_confirmation'] as $field) {
            $this->assertArrayNotHasKey($field, $old);
        }
        $this->post(route('portal.profile.password'), [
            'current_password' => 'Current123!', 'password' => 'Changed123!', 'password_confirmation' => 'Changed123!',
        ])->assertSessionHasNoErrors();
        $this->get(route('portal.profile.show'))->assertOk();
        $this->assertSame($user->fresh()->auth_session_token, session('auth_session_token'));
    }
}
