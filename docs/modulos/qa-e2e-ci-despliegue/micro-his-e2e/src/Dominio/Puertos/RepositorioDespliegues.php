<?php

declare(strict_types=1);

namespace E2e\Dominio\Puertos;

use E2e\Dominio\Excepciones\ErrorPersistencia;
use E2e\Dominio\Modelo\Despliegue;

/** Semana 6: los despliegues se agregan; nunca se editan. */
interface RepositorioDespliegues
{
    /** @throws ErrorPersistencia */
    public function registrar(Despliegue $despliegue): Despliegue;

    /** @return list<Despliegue> en orden @throws ErrorPersistencia */
    public function deVersion(string $tenant, string $version): array;

    /** El último DESPLEGADO (no revertido) en ese entorno: lo que hoy está instalado. @throws ErrorPersistencia */
    public function vigente(string $tenant, string $entorno): ?Despliegue;
}
