<?php

declare(strict_types=1);

namespace Rbac\Presentacion\Http\Acciones;

use Rbac\Presentacion\Http\Accion;
use Rbac\Presentacion\Http\Identidad;
use Rbac\Presentacion\Http\Respuesta;
use Rbac\Presentacion\Http\Solicitud;

/**
 * Operación clínica simulada (prescripción, signos vitales). Solo existe para
 * demostrar la protección de rutas: la lógica clínica pertenece a otros módulos.
 */
final class OperacionClinicaDemoAccion implements Accion
{
    public function __construct(private readonly string $descripcion)
    {
    }

    public function ejecutar(Solicitud $solicitud, Identidad $identidad, array $parametros): Respuesta
    {
        return Respuesta::json(201, ['mensaje' => $this->descripcion . ' registrada (demo).']);
    }
}
