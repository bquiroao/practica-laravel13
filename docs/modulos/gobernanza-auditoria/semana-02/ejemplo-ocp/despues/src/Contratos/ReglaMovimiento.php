<?php

declare(strict_types=1);

namespace Auditoria\Contratos;

use Auditoria\Dominio\Rechazo;
use Auditoria\Dominio\SolicitudRegistro;

/** Punto de extensión 1 (OCP): una validación del movimiento antes de registrarlo. */
interface ReglaMovimiento
{
    /** null = cumple; Rechazo = el motivo por el que no se registra. */
    public function evaluar(SolicitudRegistro $solicitud): ?Rechazo;
}
