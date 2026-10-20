<?php
declare(strict_types=1);

namespace MicroHis\Domain;

/** Registry: entrega el reporte por su tipo; agregar un reporte no cambia al cliente (OCP). */
final class ReportRegistry
{
    /** @var array<string,ContratoReporte> */
    private array $reportes = [];

    public function registrar(ContratoReporte $reporte): void
    {
        $this->reportes[$reporte->tipo()] = $reporte;
    }

    public function obtener(string $tipo): ContratoReporte
    {
        return $this->reportes[$tipo] ?? throw new ReporteNoExiste("No existe el reporte «{$tipo}»");
    }

    /** @return list<ContratoReporte> */
    public function todos(): array
    {
        return array_values($this->reportes);
    }
}
