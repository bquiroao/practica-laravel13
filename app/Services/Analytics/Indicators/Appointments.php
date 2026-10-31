<?php

namespace App\Services\Analytics\Indicators;

use App\Services\Analytics\Filters;
use Illuminate\Support\Facades\DB;

/** Fuente común de los indicadores de consulta externa: las citas del período con su médico y especialidad. */
trait Appointments
{
    private const APPOINTMENT_STATUS = ['scheduled' => 'Programada', 'confirmed' => 'Confirmada', 'completed' => 'Atendida', 'cancelled' => 'Cancelada'];

    /**
     * @param  list<string>|null  $statuses  solo esos estados; null = todas
     * @return list<array{peso: int, dims: array<string, string>, fila: array<string, string|int|null>}>
     */
    protected function appointments(Filters $f, ?array $statuses = null): array
    {
        return $this->appointmentQuery($f, $statuses)
            ->join('local_patients as p', 'p.local_patient_id', '=', 'a.local_patient_id')
            ->join('specialties as sp', 'sp.specialty_id', '=', 'a.specialty_id')
            ->leftJoin('doctor_profiles as d', 'd.doctor_profile_id', '=', 'a.doctor_profile_id')
            ->leftJoin('local_user_refs as u', 'u.local_user_ref_id', '=', 'd.local_user_ref_id')
            ->orderBy('a.starts_at')->orderBy('a.appointment_id')
            ->get(['a.appointment_id', 'a.starts_at', 'a.status', 'p.code as patient', 'sp.name as specialty', 'u.display_name as doctor'])
            ->map(function ($r) use ($f) {
                $status = self::APPOINTMENT_STATUS[$r->status] ?? $r->status;
                $doctor = $r->doctor ?: 'Sin nombre';

                return [
                    'peso' => 1,
                    'dims' => ['estado' => $status, 'medico' => $doctor, 'especialidad' => $r->specialty],
                    'fila' => ['cita' => strtoupper(substr($r->appointment_id, 0, 8)), 'fecha' => $f->localDateTime($r->starts_at), 'medico' => $doctor,
                        'especialidad' => $r->specialty, 'paciente' => $r->patient, 'estado' => $status],
                ];
            })->all();
    }

    protected function appointmentCount(Filters $f, ?array $statuses = null): int
    {
        return $this->appointmentQuery($f, $statuses)->count();
    }

    private function appointmentQuery(Filters $f, ?array $statuses)
    {
        return DB::connection('hospital')->table('appointments as a')
            ->whereBetween('a.starts_at', [$f->from->utc(), $f->to->utc()])
            ->when($statuses !== null, fn ($q) => $q->whereIn('a.status', $statuses))
            ->when($f->doctorProfileId, fn ($q, $id) => $q->where('a.doctor_profile_id', $id));
    }

    protected function appointmentColumns(): array
    {
        return ['cita' => 'Cita', 'fecha' => 'Fecha y hora', 'medico' => 'Médico', 'especialidad' => 'Especialidad', 'paciente' => 'Paciente', 'estado' => 'Estado'];
    }
}
