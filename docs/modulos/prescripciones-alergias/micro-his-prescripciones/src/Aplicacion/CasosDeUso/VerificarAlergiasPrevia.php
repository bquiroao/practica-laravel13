<?php

declare(strict_types=1);

namespace Prescripciones\Aplicacion\CasosDeUso;

use Prescripciones\Aplicacion\Puertos\FuenteAlergiasNoDisponible;
use Prescripciones\Aplicacion\Puertos\Pacientes;
use Prescripciones\Aplicacion\Puertos\PoliticaAcceso;
use Prescripciones\Aplicacion\Resultados\Resultado;
use Prescripciones\Aplicacion\Servicios\EvaluacionClinica;
use Prescripciones\Dominio\Excepciones\ErrorPersistencia;
use Prescripciones\Dominio\Reglas\PoliticaEmision;

/** CU-02, verificación previa y consultiva: informa, no guarda ni traza nada. Semana 12: dice si se puede justificar. */
final class VerificarAlergiasPrevia
{
    /** Verificar antes de firmar exige el mismo permiso que emitir. */
    public const PERMISO = EmitirPrescripcion::PERMISO;

    public function __construct(
        private readonly PoliticaAcceso $acceso,
        private readonly Pacientes $pacientes,
        private readonly EvaluacionClinica $evaluacion,
        private readonly PoliticaEmision $politica = new PoliticaEmision(),
    ) {
    }

    /** @param list<array<string, mixed>> $items */
    public function ejecutar(string $usuario, string $hospital, string $paciente, array $items): Resultado
    {
        if (!$this->acceso->permite($usuario, self::PERMISO, $hospital)) {
            return new Resultado(Resultado::DENEGADO, 'PERMISO_DENEGADO');
        }
        try {
            if (!$this->pacientes->existeEnHospital($paciente, $hospital)) {
                return new Resultado(Resultado::NO_ENCONTRADO, 'NO_ENCONTRADO');
            }
            $evaluada = $this->evaluacion->evaluar($paciente, $hospital, $items);
        } catch (FuenteAlergiasNoDisponible) {
            return new Resultado(Resultado::OK, 'VERIFICACION_NO_DISPONIBLE', ['hallazgos' => []]);
        } catch (ErrorPersistencia) {
            return new Resultado(Resultado::NO_DISPONIBLE, 'ERROR_PERSISTENCIA');
        }
        if ($evaluada instanceof Resultado) {
            return $evaluada;
        }
        [$expediente, $verificacion] = $evaluada;

        // Semana 12 (RF-19): con una advertencia, la previa dice si este usuario puede justificarla (brecha M-07).
        $puede = $this->politica->puedeJustificar($verificacion, $expediente,
            fn (): bool => $this->acceso->permite($usuario, EmitirPrescripcion::PERMISO_OVERRIDE, $hospital));

        return new Resultado(Resultado::OK, $this->politica->resultadoPrevio($verificacion, $expediente),
            ['hallazgos' => $verificacion->hallazgosComoArreglo()] + ($puede === null ? [] : ['puede_justificar' => $puede]));
    }
}
