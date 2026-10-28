<?php
// DESPUÉS: el controlador solo coordina. El cálculo está en el dominio y la consulta en el Repository.
final class ReporteController
{
    public function __construct(private readonly CalcularIndicadorAction $accion)
    {
    }

    public function desglose(FiltrosReporteRequest $request, string $tipo): IndicadorResource
    {
        return new IndicadorResource($this->accion->ejecutar($request->user(), $tipo, $request->filtros(), 'desglose'));
    }
}

// Dominio: valores exactos + precisión declarada; el desglose son conteos enteros (RN-07).
final class Precision
{
    public function __construct(public readonly int $decimales, public readonly string $unidad)
    {
    }
}
// ReporteOcupacion::calcular() devuelve:
// new Indicador('Ocupación de camas', $ocupadas / $habilitadas * 100, $ocupadas, $habilitadas, new Precision(1, '%'), $ficha)
