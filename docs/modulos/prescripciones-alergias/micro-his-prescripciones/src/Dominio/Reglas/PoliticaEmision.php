<?php

declare(strict_types=1);

namespace Prescripciones\Dominio\Reglas;

use Prescripciones\Dominio\Modelo\DecisionEmision;
use Prescripciones\Dominio\Modelo\ExpedienteAlergias;
use Prescripciones\Dominio\Modelo\ResultadoVerificacion;
use Prescripciones\Dominio\Modelo\SolicitudEmision;

/**
 * Semana 7: la decisión «¿se puede emitir?» vive en UN lugar del Dominio (R-04, R-05, R-06).
 * Antes estaba repartida entre EmitirPrescripcion (bloqueo, advertencia, mínimo de la justificación, confirmación)
 * y VerificarAlergiasPrevia (confirmación), dos clases de la Aplicación que podían divergir.
 * El orden es parte de la regla: confirmación → bloqueo → advertencia.
 */
final class PoliticaEmision
{
    public const MIN_JUSTIFICACION = 20;
    public const CONFIRMACION_REQUERIDA = 'CONFIRMACION_REQUERIDA';

    /**
     * @param \Closure(): bool $tieneOverride se consulta SOLO si hay una advertencia (no se pregunta a RBAC sin necesidad)
     */
    public function decidir(ResultadoVerificacion $verificacion, ExpedienteAlergias $expediente, SolicitudEmision $solicitud,
        \Closure $tieneOverride): DecisionEmision
    {
        if (!$expediente->evaluado && !$solicitud->confirmaSinAlergias) {
            return DecisionEmision::requiereConfirmacion();
        }
        if ($verificacion->estado === ResultadoVerificacion::BLOQUEO) {
            return DecisionEmision::bloqueada($verificacion->codigoBloqueo());
        }
        $confirmacion = !$expediente->evaluado && $solicitud->confirmaSinAlergias;
        if ($verificacion->estado !== ResultadoVerificacion::ADVERTENCIA) {
            return DecisionEmision::permitida(null, $confirmacion);
        }
        $texto = trim((string) $solicitud->justificacion);
        if (mb_strlen($texto) < self::MIN_JUSTIFICACION || !$tieneOverride()) {
            return DecisionEmision::requiereJustificacion();
        }

        return DecisionEmision::permitida($texto, $confirmacion);
    }

    /**
     * Semana 12 (RF-19): ¿este usuario podría emitir la advertencia con una justificación? Es la MISMA condición de permiso
     * que decidir() exige; null cuando no hay advertencia, y entonces no se pregunta a RBAC (igual que en decidir()).
     *
     * @param \Closure(): bool $tieneOverride
     */
    public function puedeJustificar(ResultadoVerificacion $verificacion, ExpedienteAlergias $expediente, \Closure $tieneOverride): ?bool
    {
        return $this->resultadoPrevio($verificacion, $expediente) === ResultadoVerificacion::ADVERTENCIA ? $tieneOverride() : null;
    }

    /** Lo que la verificación previa informa: el resultado, salvo que falte evaluar las alergias. */
    public function resultadoPrevio(ResultadoVerificacion $verificacion, ExpedienteAlergias $expediente): string
    {
        return $expediente->evaluado ? $verificacion->estado : self::CONFIRMACION_REQUERIDA;
    }
}
