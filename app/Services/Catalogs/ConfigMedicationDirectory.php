<?php

namespace App\Services\Catalogs;

/** Medicamentos provisionales mientras no exista el catálogo de medicamentos. */
class ConfigMedicationDirectory implements MedicationDirectory
{
    public function all(): array
    {
        return config('nido_catalogos.medicamentos');
    }

    public function find(string $code): ?array
    {
        return collect($this->all())->firstWhere('code', strtoupper(trim($code)));
    }
}
