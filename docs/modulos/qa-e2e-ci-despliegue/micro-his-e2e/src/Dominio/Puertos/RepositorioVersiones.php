<?php

declare(strict_types=1);

namespace E2e\Dominio\Puertos;

use E2e\Dominio\Excepciones\ErrorPersistencia;
use E2e\Dominio\Modelo\Version;

interface RepositorioVersiones
{
    /** @throws ErrorPersistencia */
    public function crear(Version $version): void;

    /** Guarda estado y plan; el número, el commit y el digest no cambian nunca. @throws ErrorPersistencia */
    public function actualizar(Version $version): void;

    /** @throws ErrorPersistencia */
    public function porNumero(string $tenant, string $numero): ?Version;

    /** @throws ErrorPersistencia */
    public function porDigest(string $tenant, string $digest): ?Version;
}
