<?php

declare(strict_types=1);

namespace Rbac\Presentacion\Http\Controladores;

use Rbac\Aplicacion\CasosDeUso\AutorizarOperacion;
use Rbac\Aplicacion\CasosDeUso\ConsultarPermisosEfectivos;
use Rbac\Presentacion\Http\Formularios\DatosInvalidos;
use Rbac\Presentacion\Http\Formularios\FormularioDecision;
use Rbac\Presentacion\Http\Formularios\FormularioPermisosEfectivos;
use Rbac\Presentacion\Http\Peticion;
use Rbac\Presentacion\Http\Respuesta;
use Rbac\Presentacion\Http\Vistas\DecisionVista;
use Rbac\Presentacion\Http\Vistas\ErrorVista;
use Rbac\Presentacion\Http\Vistas\PermisosEfectivosVista;

/**
 * POST /api/v1/autorizaciones/decisiones — punto de decisión de políticas (PDP).
 * Los demás módulos del HIS preguntan aquí en lugar de leer permisos por su cuenta.
 *
 * POST /api/v1/autorizaciones/permisos-efectivos — semana 12 (B-6): qué permisos tiene la sesión,
 * para que la interfaz decida qué mostrar sin auditar consultas que no son intentos de acceso.
 */
final class AutorizacionControlador
{
    public function __construct(
        private readonly AutorizarOperacion $autorizar,
        private readonly ConsultarPermisosEfectivos $permisosEfectivos,
    ) {
    }

    public function decidir(Peticion $peticion): Respuesta
    {
        try {
            $comando = FormularioDecision::aComando($peticion);
        } catch (DatosInvalidos $e) {
            return ErrorVista::datosInvalidos($e->campos);
        }

        return DecisionVista::decision($this->autorizar->ejecutar($comando));
    }

    public function permisosEfectivos(Peticion $peticion): Respuesta
    {
        try {
            $permisos = FormularioPermisosEfectivos::aLista($peticion);
        } catch (DatosInvalidos $e) {
            return ErrorVista::datosInvalidos($e->campos);
        }

        return PermisosEfectivosVista::mapa($this->permisosEfectivos->ejecutar(
            $peticion->identidad->usuarioId,
            $peticion->identidad->tenantToken,
            $peticion->tenant(),
            $permisos,
        ));
    }
}
