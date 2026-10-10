<?php

declare(strict_types=1);

namespace Rbac\Contratos;

use Rbac\Dominio\ContextoAutorizacion;

/**
 * Punto de extensión del autorizador (OCP).
 *
 * Cada regla decide UNA condición. Devuelve null si la condición se cumple o el
 * código del motivo de denegación si no. Una regla nueva es una clase nueva que
 * implementa esta interfaz; el Autorizador no se modifica.
 */
interface ReglaAutorizacion
{
    public function evaluar(ContextoAutorizacion $contexto): ?string;
}
