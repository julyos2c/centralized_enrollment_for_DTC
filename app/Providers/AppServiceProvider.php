<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        // asset_v(): cache-busting URLs for local CSS/JS (see App\Support\AssetVersion).
        require_once app_path('Support/helpers.php');
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // The app's theme CSS (dtc-theme.css) styles Bootstrap's
        // .pagination / .page-link / .page-item markup. Without this,
        // ->links() falls back to Laravel's default Tailwind pagination
        // view, which renders unstyled since no Tailwind CSS is loaded.
        Paginator::useBootstrap();
    }
}