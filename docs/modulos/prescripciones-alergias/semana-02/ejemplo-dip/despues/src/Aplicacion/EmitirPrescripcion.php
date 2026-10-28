<?php

declare(strict_types=1);

namespace Prescripciones\Aplicacion;

use Prescripciones\Aplicacion\Puertos\ErrorPersistencia;
use Prescripciones\Aplicacion\Puertos\FuenteAlergiasNoDisponible;
use Prescripciones\Aplicacion\Puertos\Pacientes;
use Prescripciones\Aplicacion\Puertos\PoliticaAcceso;
use Prescripciones\Aplicacion\Puertos\RegistroAuditoria;
use Prescripciones\Aplicacion\Puertos\Reloj;
use Prescripciones\Aplicacion\Puertos\RepositorioPrescripciones;
use Prescripciones\Dominio\Prescripcion;
use Prescripciones\Dominio\ResultadoVerificacion;
use Prescripciones\Dominio\SolicitudEmision;

/**
 * Política de alto nivel: «creación de prescripción con verificación previa de alergias» (CU-01).
 * Solo conoce puertos (interfaces de Aplicacion\Puertos) y el dominio: ni PDO, ni SQL, ni archivos, ni HTTP.
 */
final class EmitirPrescripcion
{
    public const MIN_JUSTIFICACION = 20;

    public function __construct(
        private readonly PoliticaAcceso $acceso,
        private readonly Pacientes $pacientes,
        private readonly EvaluacionClinica $evaluacion,
        private readonly RepositorioPrescripciones $repositorio,
        private readonly RegistroAuditoria $auditoria,
        private readonly Reloj $reloj,
    ) {
    }

    public function ejecutar(SolicitudEmision $s): Respuesta
    {
        if (!$this->acceso->permite($s->usuario, 'prescripcion.emitir', $s->hospital)) {
            return new Respuesta(403, 'PERMISO_DENEGADO');
        }

        $previa = $this->repositorio->porClave($s->hospital, $s->clave);
        if ($previa !== null) {
            return $previa->huella === $s->huella()
                ? new Respuesta(200, 'YA_EMITIDA', ['id' => $previa->id])
                : new Respuesta(409, 'CLAVE_REUTILIZADA');
        }

        if (!$this->pacientes->existeEnHospital($s->paciente, $s->hospital)) {
            return new Respuesta(404, 'NO_ENCONTRADO');
        }

        try {
            $evaluada = $this->evaluacion->evaluar($s->paciente, $s->hospital, $s->items);
        } catch (FuenteAlergiasNoDisponible) {
            $this->auditoria->registrar('VERIFICACION_NO_DISPONIBLE', $s->hospital, $s->usuario, 'paciente:' . $s->paciente, 'error');

            return new Respuesta(503, 'VERIFICACION_NO_DISPONIBLE');
        }
        if ($evaluada instanceof Respuesta) {
            return $evaluada;
        }
        [$expediente, $verificacion] = $evaluada;

        if (!$expediente->evaluado && !$s->confirmaSinAlergias) {
            return new Respuesta(422, 'CONFIRMACION_REQUERIDA');
        }

        if ($verificacion->estado === ResultadoVerificacion::BLOQUEO) {
            $this->auditoria->registrar('PRESCRIPCION_BLOQUEADA', $s->hospital, $s->usuario, 'paciente:' . $s->paciente, 'denegado');

            return new Respuesta(422, $verificacion->codigoBloqueo(), ['hallazgos' => $verificacion->hallazgosComoArreglo()]);
        }

        if ($verificacion->estado === ResultadoVerificacion::ADVERTENCIA
            && (mb_strlen(trim((string) $s->justificacion)) < self::MIN_JUSTIFICACION
                || !$this->acceso->permite($s->usuario, 'prescripcion.override', $s->hospital))) {
            return new Respuesta(409, 'JUSTIFICACION_REQUERIDA', ['hallazgos' => $verificacion->hallazgosComoArreglo()]);
        }

        $prescripcion = new Prescripcion(null, $s->hospital, $s->paciente, $s->usuario, $s->clave, $s->huella(), $s->items,
            $verificacion, $s->justificacion, !$expediente->evaluado && $s->confirmaSinAlergias, $this->reloj->ahora());
        try {
            $guardada = $this->repositorio->guardar($prescripcion);
        } catch (ErrorPersistencia) {
            return new Respuesta(503, 'ERROR_PERSISTENCIA');
        }

        $this->auditoria->registrar('PRESCRIPCION_EMITIDA', $s->hospital, $s->usuario, 'prescripcion:' . $guardada->id, 'exito');

        return new Respuesta(201, 'EMITIDA', ['id' => $guardada->id, 'verificacion' => $verificacion->estado]);
    }
}
