<?php

namespace App\Services\Analytics\Indicators;

use App\Services\Analytics\Filters;
use App\Services\Analytics\Indicator;

/** Tasa de cancelación de citas: canceladas ÷ citas del período × 100. */
class CancellationIndicator extends Indicator
{
    use Appointments;

    public function key(): string
    {
        return 'cancelacion_citas';
    }

    public function name(): string
    {
        return 'Cancelación de citas';
    }

    public function area(): string
    {
        return 'Consulta externa';
    }

    public function unit(): string
    {
        return '%';
    }

    public function description(): string
    {
        return 'De cada 100 citas del período, cuántas se cancelaron.';
    }

    public function formula(): string
    {
        return 'citas canceladas / citas del período × 100';
    }

    public function source(): string
    {
        return 'appointments, doctor_profiles, specialties, local_patients';
    }

    public function dimensions(): array
    {
        return ['especialidad' => 'Especialidad', 'medico' => 'Médico'];
    }

    public function columns(): array
    {
        return $this->appointmentColumns();
    }

    public function numeratorLabel(): string
    {
        return 'citas canceladas';
    }

    public function denominatorLabel(): string
    {
        return 'citas del período';
    }

    public function feeds(): array
    {
        return ['citas-medicas'];
    }

    public function items(Filters $f): array
    {
        return $this->appointments($f, ['cancelled']);
    }

    public function denominator(Filters $f, array $items): int
    {
        return $this->appointmentCount($f);
    }
}
