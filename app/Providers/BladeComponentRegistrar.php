<?php
declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Blade;

class BladeServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Register custom Blade components
        Blade::component('app.components.layout', 'layout');
        Blade::component('app.components.button', 'button');
        Blade::component('app.components.card', 'card');
        Blade::component('app.components.navbar', 'navbar');
        Blade::component('app.components.footer', 'footer');
    }
}
