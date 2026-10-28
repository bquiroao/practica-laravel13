<?php

declare(strict_types=1);

namespace Auditoria\Aplicacion\Servicios;

use Auditoria\Aplicacion\Puertos\PoliticaAcceso;
use Auditoria\Dominio\Modelo\Alcance;

/**
 * Semana 7: qué permiso exige leer la bitácora según el alcance. Antes estaba repetido en la
 * consulta y en el detalle, y el detalle leía las constantes de la consulta (un caso de uso
 * dependía de otro).
 */
final class AutorizacionLectura
{
    public const PERMISO = 'auditoria.consultar';
    public const PERMISO_RED = 'auditoria.consultar_red';

    public function __construct(private readonly PoliticaAcceso $acceso)
    {
    }

    public function permite(string $actor, Alcance $alcance): bool
    {
        return $this->acceso->permite($actor, $alcance->tenant(), $alcance->esRed() ? self::PERMISO_RED : self::PERMISO);
    }
}
