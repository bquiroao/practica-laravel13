<?php

declare(strict_types=1);

namespace Prescripciones\Aplicacion\Servicios;

use Prescripciones\Aplicacion\Puertos\CatalogoMedicamentos;
use Prescripciones\Aplicacion\Puertos\FuenteAlergias;
use Prescripciones\Aplicacion\Puertos\FuenteAlergiasNoDisponible;
use Prescripciones\Aplicacion\Resultados\Resultado;
use Prescripciones\Dominio\Modelo\ExpedienteAlergias;
use Prescripciones\Dominio\Modelo\ResultadoVerificacion;
use Prescripciones\Dominio\Reglas\ValidadorPrescripcion;
use Prescripciones\Dominio\Reglas\VerificadorAlergias;

/**
 * Pasos comunes a la verificación previa y a la emisión: catálogo, completitud y dosis (R-01, R-02),
 * y verificación de alergias (R-03..R-07). Depende solo de puertos y de reglas puras del dominio.
 */
final class EvaluacionClinica
{
    public function __construct(
        private readonly CatalogoMedicamentos $catalogo,
        private readonly FuenteAlergias $alergias,
        private readonly ValidadorPrescripcion $validador = new ValidadorPrescripcion(),
        private readonly VerificadorAlergias $verificador = new VerificadorAlergias(),
    ) {
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return Resultado|array{0: ExpedienteAlergias, 1: ResultadoVerificacion} un rechazo, o el expediente y la verificación
     * @throws FuenteAlergiasNoDisponible
     */
    public function evaluar(string $paciente, string $hospital, array $items): Resultado|array
    {
        $medicamentos = [];
        foreach ($items as $item) {
            $codigo = is_array($item) ? (string) ($item['medicamento'] ?? '') : '';
            $medicamentos[$codigo] = $this->catalogo->buscar($codigo);
        }
        $errores = $this->validador->validar($items, $medicamentos);
        if ($errores['campos'] !== []) {
            return new Resultado(Resultado::RECHAZADO, 'DATOS_INVALIDOS', ['campos' => $errores['campos']]);
        }
        if ($errores['dosis'] !== []) {
            return new Resultado(Resultado::RECHAZADO, 'DOSIS_EXCEDE_MAXIMO', ['items' => $errores['dosis']]);
        }

        // R-03: siempre se consulta la fuente; una falla sale como excepción, nunca como lista vacía (R-07).
        $expediente = $this->alergias->alergiasDe($paciente, $hospital);
        $grupos = [];
        foreach ($expediente->activas() as $a) {
            $grupo = $this->catalogo->grupoDelAlergeno($a);
            if ($grupo !== null) {
                $grupos[$a->alergeno()] = $grupo;
            }
        }

        return [$expediente, $this->verificador->verificar(array_values(array_filter($medicamentos)), $expediente, $grupos)];
    }
}
