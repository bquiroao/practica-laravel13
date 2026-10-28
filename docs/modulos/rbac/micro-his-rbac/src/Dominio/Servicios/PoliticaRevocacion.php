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

    /** Fail-closed (semana 7): si una regla no puede leer sus datos, la revocación se rechaza con 500. */
    public function evaluar(SolicitudRevocacion $solicitud): ?ErrorRevocacion
    {
        try {
            foreach ($this->reglas as $regla) {
                $error = $regla->evaluar($solicitud);
                if ($error !== null) {
                    return $error;
                }
            }
        } catch (ErrorPersistencia) {
            return new ErrorRevocacion('ERROR_PERSISTENCIA', 500);
        }

        return null;
    }
}
