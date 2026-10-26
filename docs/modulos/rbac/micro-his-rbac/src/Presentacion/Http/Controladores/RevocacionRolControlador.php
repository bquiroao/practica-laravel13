<?php

declare(strict_types=1);

namespace Rbac\Presentacion\Http\Controladores;

use Rbac\Aplicacion\CasosDeUso\RevocarRol;
use Rbac\Presentacion\Http\Formularios\DatosInvalidos;
use Rbac\Presentacion\Http\Formularios\FormularioRevocacion;
use Rbac\Presentacion\Http\Peticion;
use Rbac\Presentacion\Http\Respuesta;
use Rbac\Presentacion\Http\Vistas\ErrorVista;
use Rbac\Presentacion\Http\Vistas\RevocacionVista;

/** POST /api/v1/roles/revocaciones — formulario → caso de uso → vista (semana 6). */
final class RevocacionRolControlador
{
    public function __construct(private readonly RevocarRol $revocarRol)
    {
    }

    public function crear(Peticion $peticion): Respuesta
    {
        try {
            $comando = FormularioRevocacion::aComando($peticion);
        } catch (DatosInvalidos $e) {
            return ErrorVista::datosInvalidos($e->campos);
        }

        return RevocacionVista::resultado($this->revocarRol->ejecutar($comando));
    }
}
