<?php

declare(strict_types=1);

namespace Prescripciones\Aplicacion\CasosDeUso;

use Prescripciones\Aplicacion\Puertos\FuenteAlergiasNoDisponible;
use Prescripciones\Aplicacion\Puertos\Pacientes;
use Prescripciones\Aplicacion\Puertos\PoliticaAcceso;
use Prescripciones\Aplicacion\Puertos\RegistroAuditoria;
use Prescripciones\Aplicacion\Puertos\Transaccion;
use Prescripciones\Aplicacion\Resultados\Resultado;
use Prescripciones\Aplicacion\Servicios\EvaluacionClinica;
use Prescripciones\Dominio\Excepciones\ClaveEnUso;
use Prescripciones\Dominio\Excepciones\ErrorPersistencia;
use Prescripciones\Dominio\Modelo\DecisionEmision;
use Prescripciones\Dominio\Modelo\Prescripcion;
use Prescripciones\Dominio\Modelo\ResultadoVerificacion;
use Prescripciones\Dominio\Modelo\SolicitudEmision;
use Prescripciones\Dominio\Puertos\Reloj;
use Prescripciones\Dominio\Puertos\RepositorioPrescripciones;
use Prescripciones\Dominio\Reglas\PoliticaEmision;

/**
 * CU-01 «creación de prescripción con verificación previa de alergias». ORQUESTA; no decide reglas clínicas:
 * permiso → idempotencia → tenant → evaluación → decisión del Dominio (PoliticaEmision) → guardar con su traza.
 */
final class EmitirPrescripcion
{
    public const PERMISO = 'prescripcion.emitir';
    public const PERMISO_OVERRIDE = 'prescripcion.override';

    public function __construct(
        private readonly PoliticaAcceso $acceso,
        private readonly Pacientes $pacientes,
        private readonly EvaluacionClinica $evaluacion,
        private readonly RepositorioPrescripciones $repositorio,
        private readonly RegistroAuditoria $auditoria,
        private readonly Transaccion $transaccion,
        private readonly Reloj $reloj,
        private readonly PoliticaEmision $politica = new PoliticaEmision(),
    ) {
    }

    public function ejecutar(SolicitudEmision $s): Resultado
    {
        try {
            return $this->emitir($s);
        } catch (ErrorPersistencia) {
            return new Resultado(Resultado::NO_DISPONIBLE, 'ERROR_PERSISTENCIA');
        }
    }

    private function emitir(SolicitudEmision $s): Resultado
    {
        if (!$this->acceso->permite($s->usuario, self::PERMISO, $s->hospital)) {
            return new Resultado(Resultado::DENEGADO, 'PERMISO_DENEGADO');
        }

        $previa = $this->repositorio->porClave($s->hospital, $s->clave);
        if ($previa !== null) {
            return $this->reintento($previa, $s);
        }

        if (!$this->pacientes->existeEnHospital($s->paciente, $s->hospital)) {
            return new Resultado(Resultado::NO_ENCONTRADO, 'NO_ENCONTRADO');
        }

        try {
            $evaluada = $this->evaluacion->evaluar($s->paciente, $s->hospital, $s->items);
        } catch (FuenteAlergiasNoDisponible) {
            $this->trazarAparte('VERIFICACION_NO_DISPONIBLE', $s, 'error');

            return new Resultado(Resultado::NO_DISPONIBLE, 'VERIFICACION_NO_DISPONIBLE');
        }
        if ($evaluada instanceof Resultado) {
            return $evaluada;
        }
        [$expediente, $verificacion] = $evaluada;

        $decision = $this->politica->decidir($verificacion, $expediente, $s,
            fn (): bool => $this->acceso->permite($s->usuario, self::PERMISO_OVERRIDE, $s->hospital));
        if (!$decision->permiteEmitir()) {
            return $this->rechazo($decision, $verificacion, $s);
        }

        return $this->guardar(Prescripcion::emitir($s, $expediente, $verificacion, $decision, $this->reloj->ahora()), $s);
    }

    /** Traduce una decisión negativa del Dominio a un resultado; solo el bloqueo deja traza. */
    private function rechazo(DecisionEmision $decision, ResultadoVerificacion $verificacion, SolicitudEmision $s): Resultado
    {
        if ($decision->tipo === DecisionEmision::BLOQUEADA) {
            $this->trazarAparte('PRESCRIPCION_BLOQUEADA', $s, 'denegado');

            return new Resultado(Resultado::RECHAZADO, (string) $decision->codigoBloqueo, ['hallazgos' => $verificacion->hallazgosComoArreglo()]);
        }

        return $decision->tipo === DecisionEmision::REQUIERE_CONFIRMACION
            ? new Resultado(Resultado::RECHAZADO, PoliticaEmision::CONFIRMACION_REQUERIDA)
            : new Resultado(Resultado::CONFLICTO, 'JUSTIFICACION_REQUERIDA', ['hallazgos' => $verificacion->hallazgosComoArreglo()]);
    }

    private function guardar(Prescripcion $prescripcion, SolicitudEmision $s): Resultado
    {
        try {
            // La prescripción, sus ítems y su traza: todo o nada (R-12).
            $guardada = $this->transaccion->ejecutar(function () use ($prescripcion, $s): Prescripcion {
                $guardada = $this->repositorio->guardar($prescripcion);
                $this->auditoria->registrar('PRESCRIPCION_EMITIDA', $s->hospital, $s->usuario, 'prescripcion:' . $guardada->id, 'exito');

                return $guardada;
            });
        } catch (ClaveEnUso) {
            // Otra emisión con la misma clave ganó la carrera: se responde como a un reintento.
            $ganadora = $this->repositorio->porClave($s->hospital, $s->clave);

            return $ganadora !== null ? $this->reintento($ganadora, $s) : new Resultado(Resultado::CONFLICTO, 'CLAVE_REUTILIZADA');
        }

        return new Resultado(Resultado::CREADO, 'EMITIDA', ['id' => $guardada->id, 'verificacion' => $prescripcion->verificacion->estado]);
    }

    private function reintento(Prescripcion $previa, SolicitudEmision $s): Resultado
    {
        return $previa->huella === $s->huella()
            ? new Resultado(Resultado::OK, 'YA_EMITIDA', ['id' => $previa->id, 'verificacion' => $previa->verificacion->estado])
            : new Resultado(Resultado::CONFLICTO, 'CLAVE_REUTILIZADA');
    }

    /** La traza de un bloqueo o de la fuente caída no depende de una prescripción: va en su propia transacción. */
    private function trazarAparte(string $accion, SolicitudEmision $s, string $resultado): void
    {
        $this->transaccion->ejecutar(fn () => $this->auditoria->registrar($accion, $s->hospital, $s->usuario, 'paciente:' . $s->paciente, $resultado));
    }
}
