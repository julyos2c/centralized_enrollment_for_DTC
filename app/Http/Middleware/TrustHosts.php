<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustHosts as Middleware;

class TrustHosts extends Middleware
{
    /**
     * Get the host patterns that should be trusted.
     *
     * @return array<int, string|null>
     */
    public function hosts()
    {
        $hosts = config('app.trusted_hosts', []);
        $applicationHost = parse_url(config('app.url'), PHP_URL_HOST);

        if (is_string($applicationHost) && $applicationHost !== '') {
            $hosts[] = $applicationHost;
        }

        if (app()->environment(['local', 'testing'])) {
            $hosts = array_merge($hosts, ['localhost', '127.0.0.1']);
        }

        return collect($hosts)
            ->filter(fn ($host) => is_string($host) && trim($host) !== '')
            ->map(fn ($host) => '^' . preg_quote(trim($host), '/') . '$')
            ->unique()
            ->values()
            ->all();
    }
}
