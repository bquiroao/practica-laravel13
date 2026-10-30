<?php

declare(strict_types=1);

namespace E2e\Dominio\Puertos;

use E2e\Dominio\Excepciones\ErrorPersistencia;
use E2e\Dominio\Modelo\Decision;

interface RepositorioEvaluaciones
{
    /** Se agrega; una evaluación nueva no borra la anterior. @throws ErrorPersistencia */
    public function guardar(Decision $decision): void;

    /** @return list<Decision> en orden @throws ErrorPersistencia */
    public function deVersion(string $tenant, string $version): array;
}
