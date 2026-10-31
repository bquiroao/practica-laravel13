<?php

namespace App\Services\Catalogs;

/** Pruebas provisionales mientras no exista el catálogo de pruebas de laboratorio. */
class ConfigLabTestDirectory implements LabTestDirectory
{
    public function all(): array
    {
        return config('nido_catalogos.pruebas_laboratorio');
    }

    public function find(string $code): ?array
    {
        return collect($this->all())->firstWhere('code', strtoupper(trim($code)));
    }
}
