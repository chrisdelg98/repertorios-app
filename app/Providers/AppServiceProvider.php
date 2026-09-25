<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Built from config rather than autowired: its constructor takes the
        // credentials, not services.
        $this->app->singleton(\App\Services\R2Signer::class, fn () => \App\Services\R2Signer::fromConfig());

        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
