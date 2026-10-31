<?php

namespace App\Services\Analytics\Indicators;

use App\Services\Analytics\Filters;
use App\Services\Analytics\Indicator;

/** Citas del período por estado. Es el único indicador del Médico, y solo con sus propias citas (RN-08). */
class AppointmentIndicator extends Indicator
{
    use Appointments;

    public function key(): string
    {
        return 'citas';
    }

    public function name(): string
    {
        return 'Citas de consulta externa';
    }

    public function area(): string
    {
        return 'Consulta externa';
    }

    public function unit(): string
    {
        return 'cantidad';
    }

    public function description(): string
    {
        return 'Citas con fecha dentro del período, en cualquier estado.';
    }

    public function formula(): string
    {
        return 'número de citas con fecha en el período';
    }

    public function source(): string
    {
        return 'appointments, doctor_profiles, specialties, local_patients';
    }

    public function dimensions(): array
    {
        return ['estado' => 'Estado', 'medico' => 'Médico', 'especialidad' => 'Especialidad'];
    }

    public function columns(): array
    {
        return $this->appointmentColumns();
    }

    public function numeratorLabel(): string
    {
        return 'citas';
    }

    public function note(): string
    {
        return 'Incluye las citas futuras del período que ya están agendadas.';
    }

    public function doctorScoped(): bool
    {
        return true;
    }

    public function feeds(): array
    {
        return ['citas-medicas'];
    }

    public function items(Filters $f): array
    {
        return $this->appointments($f);
    }
}
