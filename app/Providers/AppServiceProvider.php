<?php

namespace App\Providers;

use App\Services\Access\PermissionResolver;
use App\Services\Catalogs\ConfigLabTestDirectory;
use App\Services\Catalogs\LabTestDirectory;
use App\Services\Catalogs\MedicationDirectory;
use App\Services\Hospital\HospitalDatabase;
use App\Services\Medications\DatabaseMedicationDirectory;
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
        $this->app->singleton(HospitalDatabase::class);
        // Catálogos provisionales: los módulos de catálogo de pruebas y de medicamentos registran aquí su implementación.
        $this->app->bind(LabTestDirectory::class, ConfigLabTestDirectory::class);
        // Módulo catalogo-medicamentos: prescripciones receta desde el catálogo administrable.
        $this->app->bind(MedicationDirectory::class, DatabaseMedicationDirectory::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
