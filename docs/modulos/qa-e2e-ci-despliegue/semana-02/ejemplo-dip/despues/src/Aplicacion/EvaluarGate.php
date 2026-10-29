<?php

declare(strict_types=1);

namespace E2e\Aplicacion;

use E2e\Dominio\AlmacenEvidencias;
use E2e\Dominio\Decision;
use E2e\Dominio\FuenteResultados;
use E2e\Dominio\PoliticaGate;
use E2e\Dominio\QualityGate;
use E2e\Dominio\Reloj;
use E2e\Dominio\RepositorioEvaluaciones;

/** CU-09: orquesta. Recibe sus dependencias por el constructor, como abstracciones; nunca las crea. */
final class EvaluarGate
{
    public function __construct(
        private readonly FuenteResultados $fuente,
        private readonly AlmacenEvidencias $almacen,
        private readonly Reloj $reloj,
        private readonly RepositorioEvaluaciones $evaluaciones,
    ) {
    }

    public function ejecutar(string $ejecucionId, string $digestVersion, PoliticaGate $politica): Decision
    {
        $ejecucion = $this->fuente->ejecucion($ejecucionId);
        $decision = QualityGate::evaluar($politica, $ejecucion, $digestVersion, $this->reloj->ahora(),
            fn (string $sha256): bool => $this->almacen->confirmada($sha256));
        $this->evaluaciones->guardar($decision);

        return $decision;
    }
}
