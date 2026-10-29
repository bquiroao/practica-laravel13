<?php

declare(strict_types=1);

namespace E2e\Dominio\Puertos;

use E2e\Dominio\Excepciones\ErrorPersistencia;
use E2e\Dominio\Modelo\EscenarioE2E;

interface RepositorioEscenarios
{
    /** @throws ErrorPersistencia */
    public function guardar(EscenarioE2E $escenario): void;

    /** @throws ErrorPersistencia */
    public function porId(string $tenant, string $id): ?EscenarioE2E;

    /** @return list<EscenarioE2E> ordenados por id @throws ErrorPersistencia */
    public function delTenant(string $tenant): array;
}
