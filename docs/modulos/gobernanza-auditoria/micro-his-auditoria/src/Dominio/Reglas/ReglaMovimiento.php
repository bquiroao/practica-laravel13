<?php

declare(strict_types=1);

namespace Auditoria\Dominio\Reglas;

use Auditoria\Dominio\Modelo\Rechazo;
use Auditoria\Dominio\Modelo\SolicitudRegistro;

/** Punto de extensión 1 (OCP): una validación del movimiento antes de registrarlo. */
interface ReglaMovimiento
{
    /** null = cumple; Rechazo = el motivo por el que no se registra. */
    public function evaluar(SolicitudRegistro $solicitud): ?Rechazo;
}
