<?php

namespace App\Providers;

use App\Services\IgdbService;
use Http;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Http::macro('igdb', function () {
            // Add delay BEFORE each request (~3.5 req/sec → safe under 4/sec limit)
            //usleep(280000); // 280ms = 0.28 seconds
            usleep(config('services.igdb.rate_limit_delay_ms'));
            return Http::withHeaders([
                'Client-ID' => config('igdb.credentials.client_id'),
                'Authorization' => 'Bearer ' . app(\App\Services\IgdbService::class)->getAccessToken(),
                'Accept' => 'application/json',
            ])
                ->acceptJson();
        });
    }
}
