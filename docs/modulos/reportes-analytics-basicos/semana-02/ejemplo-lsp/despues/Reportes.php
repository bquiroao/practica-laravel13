<?php
declare(strict_types=1);

require_once __DIR__ . '/Contrato.php';

final class ReporteOcupacion implements ContratoReporte
{
    private const OCUPADAS = ['Medicina' => 18, 'Cirugía' => 14, 'Pediatría' => 7];
    public function tipo(): string { return 'ocupacion'; }
    public function dimensiones(): array { return ['sala']; }
    public function calcular(Filtros $f): Indicador
    {
        $ocupadas = array_sum(self::OCUPADAS);
        return new Indicador('Ocupación de camas', round($ocupadas / 50 * 100, 1), '%', $ocupadas,
            ['formula' => 'ocupadas / habilitadas × 100', 'periodo' => "{$f->desde} a {$f->hasta}", 'fuente' => 'ward, bed, bed_stay']);
    }
    public function desglosar(Filtros $f, string $dimension): Desglose { return new Desglose($dimension, self::OCUPADAS); }
}

final class ReporteCitas implements ContratoReporte
{
    private const POR_ESTADO = ['Atendidas' => 95, 'Canceladas' => 15, 'No asistió' => 10];
    public function tipo(): string { return 'citas'; }
    public function dimensiones(): array { return ['estado']; }
    public function calcular(Filtros $f): Indicador   // no exige filtros extra; medicoId solo acota los datos (RN-08)
    {
        $total = array_sum(self::POR_ESTADO);
        return new Indicador('Citas del período', $total, 'cantidad', $total,
            ['formula' => 'conteo de citas', 'periodo' => "{$f->desde} a {$f->hasta}", 'fuente' => 'appointment']);
    }
    public function desglosar(Filtros $f, string $dimension): Desglose { return new Desglose($dimension, self::POR_ESTADO); }
}

final class ReporteMortalidad implements ContratoReporte
{
    private const POR_SERVICIO = ['Medicina' => 2, 'Cirugía' => 1, 'Pediatría' => 1];
    public function tipo(): string { return 'mortalidad'; }
    public function dimensiones(): array { return ['servicio']; }
    public function calcular(Filtros $f): Indicador
    {
        $total = array_sum(self::POR_SERVICIO);
        return new Indicador('Defunciones', $total, 'cantidad', $total,
            ['formula' => 'egresos por defunción', 'periodo' => "{$f->desde} a {$f->hasta}", 'fuente' => 'care_episode']);
    }
    public function desglosar(Filtros $f, string $dimension): Desglose   // protege datos con "Otro" SIN cambiar el total (RN-04)
    {
        $partes = [];
        foreach (self::POR_SERVICIO as $servicio => $casos) {
            $clave = $casos < 3 ? 'Otro' : $servicio;
            $partes[$clave] = ($partes[$clave] ?? 0) + $casos;
        }
        return new Desglose($dimension, $partes);
    }
}

/** Entrega el reporte por tipo: el cliente no conoce clases concretas. */
final class ReportRegistry
{
    private array $reportes = [];
    public function registrar(ContratoReporte $r): void { $this->reportes[$r->tipo()] = $r; }
    public function obtener(string $tipo): ContratoReporte { return $this->reportes[$tipo]; }
    public function todos(): array { return array_values($this->reportes); }
}

/** Cliente: usa SOLO el contrato. Sin instanceof ni parches. */
function mostrarTrazable(ContratoReporte $reporte, Filtros $filtros): string
{
    $i = $reporte->calcular($filtros);
    $d = $reporte->desglosar($filtros, $reporte->dimensiones()[0]);
    return "valor={$i->valor}{$i->unidad} suma_desglose={$d->total()}";
}
