<?php

namespace Tests\Feature;

use App\Http\Middleware\TrustHosts;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\SeedsRoles;
use Tests\TestCase;

class LoginSecurityRemediationTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_login_works_in_production_without_captcha_or_external_calls(): void
    {
        $user = $this->makeStudent('prod-login@example.test');
        $this->app['env'] = 'production';
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
        config(['app.env' => 'production']);
        Http::fake();

        $response = $this->post(route('login'), $this->loginData($user->email));

        $response->assertRedirect(route('portal.dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_per_email_limit_holds_when_source_ip_rotates(): void
    {
        config([
            'services.login_rate_limit.per_email_attempts' => 3,
            'services.login_rate_limit.per_identity_attempts' => 100,
        ]);
        $user = $this->makeStudent('spoofed-ip@example.test');

        foreach (['10.0.0.1', '10.0.0.2', '10.0.0.3'] as $ip) {
            $this->withServerVariables(['REMOTE_ADDR' => $ip])
                ->from(route('login'))
                ->post(route('login'), $this->loginData($user->email, 'wrong-password'))
                ->assertSessionHasErrors(['email' => 'Invalid email or password.']);
        }

        // A fresh IP with the correct password is still refused while the email is locked.
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.4'])
            ->from(route('login'))
            ->post(route('login'), $this->loginData($user->email))
            ->assertSessionHasErrors(['email' => 'Too many login attempts. Please wait a few minutes and try again.']);
        $this->assertGuest();
    }


    public function test_login_limits_normalized_identity_and_ip_without_disclosing_account_existence(): void
    {
        $email = 'Limit.Tester@example.test';

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->from(route('login'))
                ->post(route('login'), $this->loginData($email, 'wrong-password'))
                ->assertRedirect(route('login'))
                ->assertSessionHasErrors('email');
        }

        $response = $this->from(route('login'))
            ->post(route('login'), $this->loginData(strtolower($email), 'wrong-password'));

        $response->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email' => 'Too many login attempts. Please wait a few minutes and try again.']);
        $this->assertGuest();
    }

    public function test_successful_authentication_clears_identity_bucket_but_keeps_ip_bucket(): void
    {
        $user = $this->makeStudent('reset-limit@example.test');
        $identityKey = 'login:identity:' . hash('sha256', strtolower($user->email) . '|127.0.0.1');
        $ipKey = 'login:ip:' . hash('sha256', '127.0.0.1');

        for ($attempt = 0; $attempt < 2; $attempt++) {
            $this->from(route('login'))->post(route('login'), $this->loginData($user->email, 'wrong-password'));
        }

        $this->post(route('login'), $this->loginData($user->email));

        $this->assertSame(0, RateLimiter::attempts($identityKey));
        $this->assertGreaterThan(0, RateLimiter::attempts($ipKey));
    }

    public function test_ip_limit_cannot_be_evaded_by_rotating_login_identifiers(): void
    {
        config(['services.login_rate_limit.per_ip_attempts' => 3]);

        foreach (['one@example.test', 'two@example.test', 'three@example.test'] as $email) {
            $this->from(route('login'))
                ->post(route('login'), $this->loginData($email, 'wrong-password'))
                ->assertSessionHasErrors('email');
        }

        $this->from(route('login'))
            ->post(route('login'), $this->loginData('four@example.test', 'wrong-password'))
            ->assertSessionHasErrors(['email' => 'Too many login attempts. Please wait a few minutes and try again.']);
    }

    public function test_modal_error_fallbacks_do_not_insert_server_messages_into_html(): void
    {
        $receiptModal = file_get_contents(resource_path('views/cashier/payments/_receipt-modal.blade.php'));

        $this->assertStringNotContainsString('xhr.responseJSON.message', $receiptModal);
        $this->assertStringContainsString('$modalContent.html(errorMarkup);', $receiptModal);
    }

    public function test_trusted_hosts_are_exact_and_include_configured_application_host(): void
    {
        config([
            'app.url' => 'https://portal.example.test',
            'app.trusted_hosts' => ['portal.example.test', 'www.example.test'],
        ]);

        $trustedHosts = (new TrustHosts(app()))->hosts();

        $this->assertContains('^portal\\.example\\.test$', $trustedHosts);
        $this->assertContains('^www\\.example\\.test$', $trustedHosts);
        $this->assertNotContains('^(.+\\.)?example\\.test$', $trustedHosts);
    }

    private function makeStudent(string $email): User
    {
        $user = User::factory()->create([
            'email' => $email,
            'password' => Hash::make('correct-password'),
            'status' => 'active',
        ]);
        $user->assignRole('student');

        return $user;
    }

    private function loginData(string $email = 'unknown@example.test', string $password = 'correct-password'): array
    {
        return ['email' => $email, 'password' => $password];
    }
}
