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
        /*
         * Rehearsals and meetings share the services table, so /services/{id}
         * would happily open one — with a setlist to edit, a share button and
         * a "play all" that mean nothing there. The binding is narrowed so
         * that route can only ever resolve an actual service.
         *
         * Calendar entries are reached through {entry}, which resolves any kind.
         */
        \Illuminate\Support\Facades\Route::bind(
            'service',
            fn ($value) => \App\Models\Service::services()->findOrFail($value)
        );

        \Illuminate\Support\Facades\Route::bind(
            'entry',
            fn ($value) => \App\Models\Service::findOrFail($value)
        );

        //
    }
}
