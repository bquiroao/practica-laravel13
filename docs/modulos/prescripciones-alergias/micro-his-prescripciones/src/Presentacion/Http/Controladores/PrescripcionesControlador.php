<?php

declare(strict_types=1);

namespace Prescripciones\Presentacion\Http\Controladores;

use Prescripciones\Aplicacion\CasosDeUso\ConsultarPrescripcion;
use Prescripciones\Aplicacion\CasosDeUso\EmitirPrescripcion;
use Prescripciones\Aplicacion\CasosDeUso\VerificarAlergiasPrevia;
use Prescripciones\Presentacion\Http\Formularios\EntradaInvalida;
use Prescripciones\Presentacion\Http\Formularios\FormularioPrescripcion;
use Prescripciones\Presentacion\Http\Identidad;
use Prescripciones\Presentacion\Http\Respuesta;
use Prescripciones\Presentacion\Http\Solicitud;
use Prescripciones\Presentacion\Http\Vistas\ErrorVista;
use Prescripciones\Presentacion\Http\Vistas\PrescripcionVista;

/**
 * Controlador del MVC: formulario → caso de uso (Model) → vista. Recibe SOLO casos de uso.
 * Sin SQL, sin reglas de negocio, sin códigos HTTP (ARQ-07 lo verifica sobre este archivo).
 */
final class PrescripcionesControlador
{
    public function __construct(
        private readonly VerificarAlergiasPrevia $previa,
        private readonly EmitirPrescripcion $emitir,
        private readonly ConsultarPrescripcion $consultar,
    ) {
    }

    /** POST /api/v1/prescripciones/verificaciones */
    public function verificar(Solicitud $solicitud, Identidad $identidad): Respuesta
    {
        try {
            $entrada = FormularioPrescripcion::verificacion($solicitud);
        } catch (EntradaInvalida $e) {
            return ErrorVista::entradaInvalida($e);
        }

        return PrescripcionVista::verificacion($this->previa->ejecutar($identidad->usuario, $identidad->hospital, $entrada->paciente, $entrada->items));
    }

    /** POST /api/v1/prescripciones */
    public function emitir(Solicitud $solicitud, Identidad $identidad): Respuesta
    {
        try {
            $comando = FormularioPrescripcion::emision($solicitud, $identidad);
        } catch (EntradaInvalida $e) {
            return ErrorVista::entradaInvalida($e);
        }

        return PrescripcionVista::emision($this->emitir->ejecutar($comando));
    }

    /** GET /api/v1/prescripciones/{id} @param array<string, string> $parametros */
    public function consultar(Solicitud $solicitud, Identidad $identidad, array $parametros): Respuesta
    {
        return PrescripcionVista::detalle($this->consultar->ejecutar($identidad->usuario, $identidad->hospital, FormularioPrescripcion::id($parametros)));
    }
}
