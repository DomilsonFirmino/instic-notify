<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Models\Informativo;
use App\Policies\InformativoPolicy;
use App\Models\User;
use App\Policies\UserPolicy;

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
        // Register Informativo policy mapping for gates
        Gate::policy(Informativo::class, InformativoPolicy::class);
        // Register User policy mapping for gates
        Gate::policy(User::class, UserPolicy::class);
    }
}
