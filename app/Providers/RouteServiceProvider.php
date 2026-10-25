<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to the "home" route for your application.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/login';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     *
     * @return void
     */
    public function boot()
    {
        $this->configureRateLimiting();

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }

    /**
     * Configure the rate limiters for the application.
     *
     * @return void
     */
    protected function configureRateLimiting()
    {
        // Pair limits with IP so an attacker cannot lock out an email globally.
        RateLimiter::for('password-recovery', function (Request $request) {
            $email = $request->input('email', '');
            $email = is_string($email) ? strtolower(trim($email)) : '';
            $identity = hash_hmac('sha256', $email . '|' . $request->ip(), (string) config('app.key'));
            return [
                Limit::perMinute(20)->by('recovery-ip:' . $request->ip()),
                Limit::perMinute(5)->by('recovery-identity:' . $identity),
            ];
        });
        RateLimiter::for('password-setup-page', fn (Request $request) => Limit::perMinute(30)->by('setup-ip:' . $request->ip()));
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('application-status-lookup', function (Request $request) {
            $reference = strtoupper(trim((string) $request->input('reference_no')));
            $email = strtolower(trim((string) $request->input('email')));
            $identity = hash_hmac('sha256', $reference . "\n" . $email, (string) config('app.key'));

            return [
                Limit::perMinute((int) config('services.application_status_lookup.per_ip', 20))
                    ->by('application-status-ip:' . $request->ip()),
                Limit::perMinute((int) config('services.application_status_lookup.per_identity', 5))
                    ->by('application-status-identity:' . $identity),
            ];
        });

        // Limits POST /apply across refreshed forms using only the client IP.
        RateLimiter::for('application-submission', function (Request $request) {
            return Limit::perHour((int) config('services.application_submission.per_ip_per_hour', 5))
                ->by('public-application-ip:' . hash('sha256', (string) $request->ip()));
        });
    }
}
