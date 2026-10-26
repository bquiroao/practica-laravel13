<?php

declare(strict_types=1);

namespace Rbac\Presentacion\Http\Controladores;

use Rbac\Aplicacion\CasosDeUso\AsignarRol;
use Rbac\Presentacion\Http\Formularios\DatosInvalidos;
use Rbac\Presentacion\Http\Formularios\FormularioAsignacion;
use Rbac\Presentacion\Http\Peticion;
use Rbac\Presentacion\Http\Respuesta;
use Rbac\Presentacion\Http\Vistas\AsignacionVista;
use Rbac\Presentacion\Http\Vistas\ErrorVista;

/** POST /api/v1/roles/asignaciones — formulario → caso de uso → vista. Sin SQL ni reglas de negocio. */
final class AsignacionRolControlador
{
    public function __construct(private readonly AsignarRol $asignarRol)
    {
    }

    public function crear(Peticion $peticion): Respuesta
    {
        try {
            $comando = FormularioAsignacion::aComando($peticion);
        } catch (DatosInvalidos $e) {
            return ErrorVista::datosInvalidos($e->campos);
        }

        return AsignacionVista::resultado($this->asignarRol->ejecutar($comando));
    }
}
