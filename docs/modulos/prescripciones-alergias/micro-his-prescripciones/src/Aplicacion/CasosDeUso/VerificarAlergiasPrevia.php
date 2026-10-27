<?php

declare(strict_types=1);

namespace Prescripciones\Aplicacion\CasosDeUso;

use Prescripciones\Aplicacion\Puertos\FuenteAlergiasNoDisponible;
use Prescripciones\Aplicacion\Puertos\Pacientes;
use Prescripciones\Aplicacion\Puertos\PoliticaAcceso;
use Prescripciones\Aplicacion\Resultados\Resultado;
use Prescripciones\Aplicacion\Servicios\EvaluacionClinica;
use Prescripciones\Dominio\Excepciones\ErrorPersistencia;

/** CU-02, verificación previa y consultiva: informa, no guarda ni traza nada. */
final class VerificarAlergiasPrevia
{
    /** Verificar antes de firmar exige el mismo permiso que emitir. */
    public const PERMISO = EmitirPrescripcion::PERMISO;

    public function __construct(
        private readonly PoliticaAcceso $acceso,
        private readonly Pacientes $pacientes,
        private readonly EvaluacionClinica $evaluacion,
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
        $codigo = $expediente->evaluado ? $verificacion->estado : 'CONFIRMACION_REQUERIDA';

        return new Resultado(Resultado::OK, $codigo, ['hallazgos' => $verificacion->hallazgosComoArreglo()]);
    }
}
