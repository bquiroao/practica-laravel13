<?php

declare(strict_types=1);

namespace Rbac\Dominio\Servicios;

use Rbac\Dominio\Modelo\ContextoAutorizacion;
use Rbac\Dominio\Reglas\ReglaAutorizacion;

/**
 * Servicio de dominio: decide si una operación se permite.
 * Recorre las reglas recibidas (OCP) y aplica fail-closed (R-05).
 */
final class PoliticaAutorizacion
{
    /** @var list<ReglaAutorizacion> */
    private readonly array $reglas;

    /** @param iterable<ReglaAutorizacion> $reglas */
    public function __construct(iterable $reglas)
    {
        $lista = [];
        foreach ($reglas as $regla) {
            $lista[] = $regla;
        }
        $this->reglas = $lista;
    }

    /** @return string|null null si se permite; el código del motivo si se deniega. */
    public function evaluar(ContextoAutorizacion $contexto): ?string
    {
        if ($this->reglas === []) {
            return 'SIN_REGLAS_CONFIGURADAS';
        }

        foreach ($this->reglas as $regla) {
            try {
                $motivo = $regla->evaluar($contexto);
            } catch (\Throwable) {
                $motivo = 'ERROR_EVALUACION';
            }

            if ($motivo !== null) {
                return $motivo;
            }
        }

        return null;
    }
}
