<?php
declare(strict_types=1);

namespace MicroHis\Domain;

/** Strategy: la ocupación de camas cumple el mismo contrato que cualquier otro reporte. */
final class ReporteOcupacion implements ContratoReporte
{
    public function __construct(private readonly LectorOcupacion $lector)
    {
    }

    public function tipo(): string
    {
        return 'ocupacion';
    }

    public function dimensiones(): array
    {
        return ['sala'];
    }

    public function calcular(Filtros $f): Indicador
    {
        $habilitadas = $this->lector->camasHabilitadas();
        $ocupadas = array_sum($this->lector->ocupadasPorSala($f));
        if ($habilitadas === 0 || $ocupadas === 0) {
            throw new SinDatos("Sin datos de ocupación del {$f->periodo()} (RN-06)");
        }

        return new Indicador('Ocupación de camas', round($ocupadas / $habilitadas * 100, 1), '%', $ocupadas, [
            'formula' => 'camas ocupadas / camas habilitadas × 100',
            'periodo' => $f->periodo(),
            'filtros' => 'ninguno adicional',
            'fuente' => 'ward, bed, bed_stay',
            'calculado_en' => (new \DateTimeImmutable())->format('Y-m-d H:i'),
            'detalle' => "{$ocupadas} de {$habilitadas} camas habilitadas",
        ]);
    }

    public function desglosar(Filtros $f, string $dimension): Desglose
    {
        $this->exigirDimension($dimension);
        return new Desglose($dimension, $this->lector->ocupadasPorSala($f));
    }

    public function registros(Filtros $f, string $dimension, string $parte): array
    {
        $this->exigirDimension($dimension);
        return $this->lector->registrosDeSala($f, $parte);
    }

    private function exigirDimension(string $dimension): void
    {
        if (!in_array($dimension, $this->dimensiones(), true)) {
            throw new \InvalidArgumentException("La ocupación no se desglosa por «{$dimension}»");
        }
    }
}
