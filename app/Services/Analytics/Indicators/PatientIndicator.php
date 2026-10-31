<?php

namespace App\Services\Analytics\Indicators;

use App\Services\Analytics\Filters;
use App\Services\Analytics\Indicator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/** Pacientes nuevos: fichas registradas en el período, por sexo y grupo de edad. */
class PatientIndicator extends Indicator
{
    private const SEXES = ['female' => 'Femenino', 'male' => 'Masculino', 'other' => 'Otro', 'unknown' => 'No indicado'];

    public function key(): string
    {
        return 'pacientes';
    }

    public function name(): string
    {
        return 'Pacientes nuevos';
    }

    public function area(): string
    {
        return 'Pacientes';
    }

    public function unit(): string
    {
        return 'cantidad';
    }

    public function description(): string
    {
        return 'Personas que se registraron por primera vez en el hospital durante el período.';
    }

    public function formula(): string
    {
        return 'número de fichas de paciente creadas en el período';
    }

    public function source(): string
    {
        return 'local_patients';
    }

    public function dimensions(): array
    {
        return ['grupo_edad' => 'Grupo de edad', 'sexo' => 'Sexo'];
    }

    public function columns(): array
    {
        return ['paciente' => 'Paciente', 'registrado' => 'Registrado', 'sexo' => 'Sexo', 'grupo_edad' => 'Grupo de edad'];
    }

    public function numeratorLabel(): string
    {
        return 'pacientes';
    }

    public function feeds(): array
    {
        return ['pacientes'];
    }

    public function items(Filters $f): array
    {
        if (! $f->started()) {
            return [];
        }

        return DB::connection('hospital')->table('local_patients')->whereBetween('created_at', $f->range())->orderBy('created_at')->orderBy('code')
            ->get(['code', 'created_at', 'sex', 'birth_date'])
            ->map(function ($r) use ($f) {
                $group = self::ageGroup(CarbonImmutable::parse($r->birth_date)->diffInYears(CarbonImmutable::parse($r->created_at)));
                $sex = self::SEXES[$r->sex] ?? $r->sex;

                return ['peso' => 1, 'dims' => ['grupo_edad' => $group, 'sexo' => $sex],
                    'fila' => ['paciente' => $r->code, 'registrado' => $f->localDateTime($r->created_at), 'sexo' => $sex, 'grupo_edad' => $group]];
            })->all();
    }
}
