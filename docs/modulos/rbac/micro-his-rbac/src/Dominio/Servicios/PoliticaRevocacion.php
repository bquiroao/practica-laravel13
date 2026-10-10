<?php

declare(strict_types=1);

namespace Rbac\Dominio\Servicios;

use Rbac\Dominio\Excepciones\ErrorPersistencia;
use Rbac\Dominio\Modelo\ErrorRevocacion;
use Rbac\Dominio\Modelo\SolicitudRevocacion;
use Rbac\Dominio\Reglas\ReglaRevocacion;

/** Servicio de dominio: valida una revocación con las reglas recibidas; la primera que falla la detiene. */
final class PoliticaRevocacion
{
    /** @var list<ReglaRevocacion> */
    private readonly array $reglas;

    /** @param iterable<ReglaRevocacion> $reglas */
    public function __construct(iterable $reglas)
    {
        $lista = [];
        foreach ($reglas as $regla) {
            $lista[] = $regla;
        }
        $this->reglas = $lista;
    }

    /** @throws ErrorPersistencia si una regla no puede consultar sus datos */
    public function evaluar(SolicitudRevocacion $solicitud): ?ErrorRevocacion
    {
        foreach ($this->reglas as $regla) {
            $error = $regla->evaluar($solicitud);
            if ($error !== null) {
                return $error;
            }
        }

        return null;
    }
}
