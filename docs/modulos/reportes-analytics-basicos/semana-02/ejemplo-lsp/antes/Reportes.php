<?php
declare(strict_types=1);

/* ANTES: jerarquía que VIOLA LSP. El cliente espera que todo ReporteBase se calcule y se
 * desglose, y que la suma del desglose coincida con el valor (RN-07). Las subclases lo rompen. */
abstract class ReporteBase
{
    abstract public function calcular(array $filtros): float;                      // % (0-100) o cantidad
    abstract public function desglosar(array $filtros, string $dimension): array;  // [parte => valor]
}

final class ReporteOcupacionAntes extends ReporteBase
{
    public function calcular(array $filtros): float { return 39 / 50; }   // POSTCONDICIÓN débil: 0.78, no 78
    public function desglosar(array $filtros, string $dimension): array { return ['Medicina' => 18, 'Cirugía' => 14, 'Pediatría' => 7]; }
}

final class ReporteCitasAntes extends ReporteBase
{
    public function calcular(array $filtros): float
    {
        if (!isset($filtros['medico_id'])) {                               // PRECONDICIÓN reforzada
            throw new InvalidArgumentException('Citas exige medico_id');
        }
        return 120;
    }
    public function desglosar(array $filtros, string $dimension): array { return ['Atendidas' => 95, 'Canceladas' => 15, 'No asistió' => 10]; }
}

final class ReporteMortalidadAntes extends ReporteBase
{
    public function calcular(array $filtros): float { return 4; }
    public function desglosar(array $filtros, string $dimension): array    // CONTRATO roto: no desglosa
    {
        throw new LogicException('Mortalidad no se puede desglosar (datos sensibles)');
    }
}

/** Cliente: obligado a conocer cada subclase para no fallar. */
function mostrarTrazableAntes(ReporteBase $reporte, array $filtros): string
{
    if ($reporte instanceof ReporteCitasAntes) { $filtros['medico_id'] ??= 0; }              // parche
    $valor = $reporte->calcular($filtros);
    if ($reporte instanceof ReporteOcupacionAntes) { $valor *= 100; }                        // parche
    if ($reporte instanceof ReporteMortalidadAntes) { return "valor={$valor} (sin desglose)"; } // parche
    return "valor={$valor} suma_desglose=" . array_sum($reporte->desglosar($filtros, 'sala'));
}
