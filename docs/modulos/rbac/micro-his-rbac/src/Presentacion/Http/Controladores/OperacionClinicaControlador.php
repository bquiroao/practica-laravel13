<?php

declare(strict_types=1);

namespace Rbac\Presentacion\Http\Controladores;

use Rbac\Presentacion\Http\Peticion;
use Rbac\Presentacion\Http\Respuesta;
use Rbac\Presentacion\Http\Vistas\OperacionVista;

/**
 * Operaciones clínicas simuladas. Solo existen para demostrar la protección de rutas:
 * la lógica clínica pertenece a otros módulos del HIS.
 */
final class OperacionClinicaControlador
{
    public function prescribir(Peticion $peticion): Respuesta
    {
        return OperacionVista::registrada('Prescripción');
    }

    public function registrarSignosVitales(Peticion $peticion): Respuesta
    {
        return OperacionVista::registrada('Toma de signos vitales');
    }
}
