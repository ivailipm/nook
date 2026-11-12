<?php

namespace App\Providers;

use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

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
        // Read the environment each time the rule is used, not when the provider boots.
        Password::defaults(fn () => $this->app->isProduction()
            ? Password::min(12)->uncompromised()
            : Password::min(8));

        // wire:navigate keeps only the stylesheets and scripts in <head>, so it would add a preload tag again and fetch the CSS once more.
        Vite::usePreloadTagAttributes(false);
    }
}
