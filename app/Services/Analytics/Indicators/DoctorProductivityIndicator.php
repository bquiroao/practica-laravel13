<?php

namespace App\Services\Analytics\Indicators;

use App\Services\Analytics\Filters;
use App\Services\Analytics\Indicator;

/** Productividad médica: citas atendidas en el período, por médico y especialidad. */
class DoctorProductivityIndicator extends Indicator
{
    use Appointments;

    public function key(): string
    {
        return 'medicos';
    }

    public function name(): string
    {
        return 'Consultas atendidas';
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
        return 'Citas que el médico marcó como atendidas: la carga de trabajo de cada médico y especialidad.';
    }

    public function formula(): string
    {
        return 'número de citas en estado «atendida» con fecha en el período';
    }

    public function source(): string
    {
        return 'appointments, doctor_profiles, local_user_refs, specialties';
    }

    public function dimensions(): array
    {
        return ['medico' => 'Médico', 'especialidad' => 'Especialidad'];
    }

    public function columns(): array
    {
        return $this->appointmentColumns();
    }

    public function numeratorLabel(): string
    {
        return 'consultas atendidas';
    }

    /** La carga de trabajo se publica por médico, aunque tenga pocas consultas: no identifica pacientes. */
    public function groupsSmallCases(string $dimension): bool
    {
        return false;
    }

    public function feeds(): array
    {
        return ['citas-medicas', 'medicos-especialidades'];
    }

    public function items(Filters $f): array
    {
        return $this->appointments($f, ['completed']);
    }
}
