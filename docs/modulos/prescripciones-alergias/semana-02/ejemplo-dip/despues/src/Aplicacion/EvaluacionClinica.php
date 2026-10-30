<?php

declare(strict_types=1);

namespace Prescripciones\Aplicacion;

use Prescripciones\Aplicacion\Puertos\CatalogoMedicamentos;
use Prescripciones\Aplicacion\Puertos\FuenteAlergias;
use Prescripciones\Aplicacion\Puertos\FuenteAlergiasNoDisponible;
use Prescripciones\Dominio\ExpedienteAlergias;
use Prescripciones\Dominio\ResultadoVerificacion;
use Prescripciones\Dominio\ValidadorPrescripcion;
use Prescripciones\Dominio\VerificadorAlergias;

/**
 * Pasos comunes a la verificación previa y a la emisión: catálogo, completitud y dosis (R-01, R-02),
 * y verificación de alergias (R-04..R-07). Depende solo de puertos y de reglas puras del dominio.
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
     * Devuelve una Respuesta de error, o el expediente y el resultado de la verificación.
     *
     * @param list<array<string, mixed>> $items
     * @return Respuesta|array{0: ExpedienteAlergias, 1: ResultadoVerificacion}
     * @throws FuenteAlergiasNoDisponible
     */
    public function evaluar(string $paciente, string $hospital, array $items): Respuesta|array
    {
        $medicamentos = [];
        foreach ($items as $item) {
            $codigo = (string) ($item['medicamento'] ?? '');
            $medicamentos[$codigo] = $this->catalogo->buscar($codigo);
        }
        $errores = $this->validador->validar($items, $medicamentos);
        if ($errores['campos'] !== []) {
            return new Respuesta(422, 'DATOS_INVALIDOS', ['campos' => $errores['campos']]);
        }
        if ($errores['dosis'] !== []) {
            return new Respuesta(422, 'DOSIS_EXCEDE_MAXIMO', ['items' => $errores['dosis']]);
        }

        // R-03: siempre se consulta la fuente; un fallo sale como excepción, nunca como lista vacía (R-07).
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
