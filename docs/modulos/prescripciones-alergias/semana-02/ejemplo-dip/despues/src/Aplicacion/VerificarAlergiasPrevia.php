<?php

declare(strict_types=1);

namespace Prescripciones\Aplicacion;

use Prescripciones\Aplicacion\Puertos\FuenteAlergiasNoDisponible;
use Prescripciones\Aplicacion\Puertos\Pacientes;
use Prescripciones\Aplicacion\Puertos\PoliticaAcceso;

/** Verificación previa, consultiva (CU-02): informa, no guarda nada. */
final class VerificarAlergiasPrevia
{
    public function __construct(
        private readonly PoliticaAcceso $acceso,
        private readonly Pacientes $pacientes,
        private readonly EvaluacionClinica $evaluacion,
    ) {
    }

    /** @param list<array<string, mixed>> $items */
    public function ejecutar(string $usuario, string $hospital, string $paciente, array $items): Respuesta
    {
        if (!$this->acceso->permite($usuario, 'prescripcion.emitir', $hospital)) {
            return new Respuesta(403, 'PERMISO_DENEGADO');
        }
        if (!$this->pacientes->existeEnHospital($paciente, $hospital)) {
            return new Respuesta(404, 'NO_ENCONTRADO');
        }
        try {
            $evaluada = $this->evaluacion->evaluar($paciente, $hospital, $items);
        } catch (FuenteAlergiasNoDisponible) {
            return new Respuesta(200, 'VERIFICACION_NO_DISPONIBLE');
        }
        if ($evaluada instanceof Respuesta) {
            return $evaluada;
        }
        [$expediente, $verificacion] = $evaluada;
        $estado = !$expediente->evaluado ? 'CONFIRMACION_REQUERIDA' : $verificacion->estado;

        return new Respuesta(200, $estado, ['hallazgos' => $verificacion->hallazgosComoArreglo()]);
    }
}
