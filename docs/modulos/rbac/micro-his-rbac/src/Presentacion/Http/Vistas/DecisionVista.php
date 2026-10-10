<?php

declare(strict_types=1);

namespace Rbac\Presentacion\Http\Vistas;

use Rbac\Aplicacion\Resultados\ResultadoAutorizacion;
use Rbac\Presentacion\Http\Respuesta;

/**
 * Vista de una decisión de autorización. Responde 200 en ambos casos: la consulta fue
 * atendida; la decisión va en el cuerpo. El motivo nunca se expone (R-08).
 */
final class DecisionVista
{
    public static function decision(ResultadoAutorizacion $resultado): Respuesta
    {
        return $resultado->permitido
            ? Respuesta::json(200, ['decision' => 'PERMITIDO'])
            : Respuesta::json(200, ['decision' => 'DENEGADO', 'correlation_id' => $resultado->correlationId]);
    }
}
