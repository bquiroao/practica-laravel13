<?php

declare(strict_types=1);

namespace Prescripciones\Aplicacion\Puertos;

use Prescripciones\Dominio\Prescripcion;

/** Sin actualizar() ni eliminar(): una prescripción emitida es inmutable (R-10). */
interface RepositorioPrescripciones
{
    public function porClave(string $hospital, string $clave): ?Prescripcion;

    /** @throws ErrorPersistencia */
    public function guardar(Prescripcion $prescripcion): Prescripcion;
}
