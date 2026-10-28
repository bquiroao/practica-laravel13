<?php

declare(strict_types=1);

namespace Prescripciones\Adaptadores\EnMemoria;

use Prescripciones\Aplicacion\Puertos\ErrorPersistencia;
use Prescripciones\Aplicacion\Puertos\RepositorioPrescripciones;
use Prescripciones\Dominio\Prescripcion;

final class RepositorioEnMemoria implements RepositorioPrescripciones
{
    /** @var list<Prescripcion> */
    public array $guardadas = [];
    public bool $fallar = false;

    public function porClave(string $hospital, string $clave): ?Prescripcion
    {
        foreach ($this->guardadas as $p) {
            if ($p->hospital === $hospital && $p->clave === $clave) {
                return $p;
            }
        }

        return null;
    }

    public function guardar(Prescripcion $prescripcion): Prescripcion
    {
        if ($this->fallar) {
            throw new ErrorPersistencia('La base no confirmó la transacción.');
        }
        $guardada = $prescripcion->conId(count($this->guardadas) + 1);
        $this->guardadas[] = $guardada;

        return $guardada;
    }
}
