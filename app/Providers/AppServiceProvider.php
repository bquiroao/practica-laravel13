<?php

namespace App\Providers;

use App\Services\Access\PermissionResolver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Una caché de permisos por petición: un rol revocado se nota en la petición siguiente.
        $this->app->scoped(PermissionResolver::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
