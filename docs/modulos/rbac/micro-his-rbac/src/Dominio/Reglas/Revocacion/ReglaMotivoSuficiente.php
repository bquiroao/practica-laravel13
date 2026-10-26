<?php

declare(strict_types=1);

namespace Rbac\Dominio\Reglas\Revocacion;

use Rbac\Dominio\Modelo\ErrorRevocacion;
use Rbac\Dominio\Modelo\SolicitudRevocacion;
use Rbac\Dominio\Reglas\ReglaRevocacion;

/** Una revocación debe ser auditable: exige un motivo con al menos 10 caracteres útiles. */
final class ReglaMotivoSuficiente implements ReglaRevocacion
{
    public const MINIMO = 10;

    public function evaluar(SolicitudRevocacion $solicitud): ?ErrorRevocacion
    {
        return mb_strlen(trim($solicitud->motivo)) < self::MINIMO
            ? new ErrorRevocacion('MOTIVO_INSUFICIENTE', 422)
            : null;
    }
}
