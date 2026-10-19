<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    protected function admin(): User
    {
        $admin = User::where('email', 'admin@dtc.edu.ph')->first();
        $admin->update(['password' => bcrypt('correct-password')]);
        return $admin;
    }

    public function test_login_page_no_longer_loads_recaptcha()
    {
        $this->get('/login')
            ->assertOk()
            ->assertDontSee('recaptcha')
            ->assertDontSee('g-recaptcha');
    }

    public function test_user_can_log_in_without_captcha()
    {
        $admin = $this->admin();

        $this->post('/login', ['email' => $admin->email, 'password' => 'correct-password'])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($admin);
    }

    public function test_wrong_password_is_rejected()
    {
        $admin = $this->admin();

        $this->from('/login')
            ->post('/login', ['email' => $admin->email, 'password' => 'wrong'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors(['email' => 'Invalid email or password.']);

        $this->assertGuest();
    }

    public function test_login_is_validated_server_side()
    {
        $this->post('/login', ['email' => 'not-an-email', 'password' => ''])
            ->assertSessionHasErrors(['email', 'password']);

        $this->assertGuest();
    }

    public function test_login_is_throttled_after_repeated_failures()
    {
        $admin = $this->admin();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => $admin->email, 'password' => 'wrong']);
        }

        // Even the correct password is refused while locked out.
        $this->post('/login', ['email' => $admin->email, 'password' => 'correct-password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertStringContainsString('Too many login attempts', session('errors')->first('email'));
    }

    public function test_successful_login_clears_failed_attempts()
    {
        $admin = $this->admin();

        for ($i = 0; $i < 4; $i++) {
            $this->post('/login', ['email' => $admin->email, 'password' => 'wrong']);
        }

        $this->post('/login', ['email' => $admin->email, 'password' => 'correct-password'])
            ->assertRedirect(route('admin.dashboard'));

        $this->post('/logout');
        $this->assertGuest();

        for ($i = 0; $i < 4; $i++) {
            $this->post('/login', ['email' => $admin->email, 'password' => 'wrong']);
        }

        $this->post('/login', ['email' => $admin->email, 'password' => 'correct-password'])
            ->assertRedirect(route('admin.dashboard'));
    }
}
