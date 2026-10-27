<?php

declare(strict_types=1);

namespace Prescripciones\Presentacion\Http\Acciones;

use Prescripciones\Aplicacion\CasosDeUso\ConsultarPrescripcion;
use Prescripciones\Aplicacion\CasosDeUso\EmitirPrescripcion;
use Prescripciones\Aplicacion\CasosDeUso\VerificarAlergiasPrevia;
use Prescripciones\Dominio\Modelo\SolicitudEmision;
use Prescripciones\Presentacion\Http\Identidad;
use Prescripciones\Presentacion\Http\Respuesta;
use Prescripciones\Presentacion\Http\Solicitud;
use Prescripciones\Presentacion\Http\TraductorResultado;

/**
 * Acciones HTTP: leen la solicitud, llaman al caso de uso y traducen el resultado.
 * Sin SQL y sin reglas clínicas (el hospital sale de la Identidad, no del cuerpo).
 */
final class AccionesPrescripciones
{
    private const BASE = '/api/v1/prescripciones';

    public function __construct(
        private readonly VerificarAlergiasPrevia $previa,
        private readonly EmitirPrescripcion $emitir,
        private readonly ConsultarPrescripcion $consultar,
        private readonly TraductorResultado $traductor = new TraductorResultado(),
    ) {
    }

    /** POST /api/v1/prescripciones/verificaciones */
    public function verificar(Solicitud $solicitud, Identidad $identidad): Respuesta
    {
        $cuerpo = $this->cuerpo($solicitud);
        if ($cuerpo instanceof Respuesta) {
            return $cuerpo;
        }

        return $this->traductor->traducir($this->previa->ejecutar($identidad->usuario, $identidad->hospital, $cuerpo['paciente'], $cuerpo['items']));
    }

    /** POST /api/v1/prescripciones (Idempotency-Key obligatoria) */
    public function emitir(Solicitud $solicitud, Identidad $identidad): Respuesta
    {
        $clave = $solicitud->cabecera('idempotency-key');
        if ($clave === null || !preg_match('/^[A-Za-z0-9._:-]{1,128}$/', $clave)) {
            return Respuesta::json(400, ['error' => 'IDEMPOTENCY_KEY_REQUERIDA']);
        }
        $cuerpo = $this->cuerpo($solicitud);
        if ($cuerpo instanceof Respuesta) {
            return $cuerpo;
        }
        $justificacion = $cuerpo['justificacion'] ?? null;
        $confirma = $cuerpo['confirma_sin_alergias'] ?? false;
        if (($justificacion !== null && !is_string($justificacion)) || !is_bool($confirma)) {
            return Respuesta::json(400, ['error' => 'CUERPO_INVALIDO']);
        }

        $resultado = $this->emitir->ejecutar(new SolicitudEmision($identidad->usuario, $identidad->hospital, $cuerpo['paciente'], $clave,
            $cuerpo['items'], $justificacion, $confirma));

        return $this->traductor->traducir($resultado, isset($resultado->datos['id']) ? self::BASE . '/' . $resultado->datos['id'] : null);
    }

    /** GET /api/v1/prescripciones/{id} */
    public function consultar(Solicitud $solicitud, Identidad $identidad, array $parametros): Respuesta
    {
        return $this->traductor->traducir($this->consultar->ejecutar($identidad->usuario, $identidad->hospital, (int) $parametros['id']));
    }

    /** @return array{paciente: string, items: list<mixed>, justificacion?: mixed, confirma_sin_alergias?: mixed}|Respuesta */
    private function cuerpo(Solicitud $solicitud): array|Respuesta
    {
        try {
            $datos = json_decode($solicitud->cuerpo, true, 16, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return Respuesta::json(400, ['error' => 'JSON_INVALIDO']);
        }
        if (!is_array($datos) || !is_string($datos['paciente'] ?? null) || !is_array($datos['items'] ?? null) || !array_is_list($datos['items'])) {
            return Respuesta::json(400, ['error' => 'CUERPO_INVALIDO']);
        }

        return $datos;
    }
}
