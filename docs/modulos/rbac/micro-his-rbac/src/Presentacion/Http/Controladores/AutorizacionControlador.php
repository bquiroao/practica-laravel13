<?php

declare(strict_types=1);

namespace Rbac\Presentacion\Http\Controladores;

use Rbac\Aplicacion\CasosDeUso\AutorizarOperacion;
use Rbac\Presentacion\Http\Formularios\DatosInvalidos;
use Rbac\Presentacion\Http\Formularios\FormularioDecision;
use Rbac\Presentacion\Http\Peticion;
use Rbac\Presentacion\Http\Respuesta;
use Rbac\Presentacion\Http\Vistas\DecisionVista;
use Rbac\Presentacion\Http\Vistas\ErrorVista;

/**
 * POST /api/v1/autorizaciones/decisiones — punto de decisión de políticas (PDP).
 * Los demás módulos del HIS preguntan aquí en lugar de leer permisos por su cuenta.
 */
final class AutorizacionControlador
{
    public function __construct(private readonly AutorizarOperacion $autorizar)
    {
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
}
