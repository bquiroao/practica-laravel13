<?php

declare(strict_types=1);

namespace Rbac\Dominio\Servicios;

use Rbac\Dominio\Excepciones\ErrorPersistencia;
use Rbac\Dominio\Modelo\ErrorAsignacion;
use Rbac\Dominio\Modelo\SolicitudAsignacion;
use Rbac\Dominio\Reglas\ReglaAsignacion;

/** Servicio de dominio: valida una asignación con las reglas recibidas; la primera que falla la detiene. */
final class PoliticaAsignacion
{
    /** @var list<ReglaAsignacion> */
    private readonly array $reglas;

    /** @param iterable<ReglaAsignacion> $reglas */
    public function __construct(iterable $reglas)
    {
        $lista = [];
        foreach ($reglas as $regla) {
            $lista[] = $regla;
        }
        $this->reglas = $lista;
    }

    /** Fail-closed (semana 7): si una regla no puede leer sus datos, la asignación se rechaza con 500. */
    public function evaluar(SolicitudAsignacion $solicitud): ?ErrorAsignacion
    {
        try {
            foreach ($this->reglas as $regla) {
                $error = $regla->evaluar($solicitud);
                if ($error !== null) {
                    return $error;
                }
            }
        } catch (ErrorPersistencia) {
            return new ErrorAsignacion('ERROR_PERSISTENCIA', 500);
        }

        return null;
    }
}
