<?php

namespace App\Services\Records;

use App\Events\VitalSignsOutOfRange;
use App\Exceptions\DomainRuleException;
use App\Models\Central\CentralUser;
use App\Models\Central\Hospital;
use App\Models\Hospital\LocalPatient;
use App\Models\Hospital\VitalSign;
use App\Services\Audit\AuditTrail;
use App\Services\Hospital\UserRefSync;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Módulo signos vitales y alertas por valores anormales: al menos una medición, valores físicamente posibles,
 * clasificación normal/anormal/crítica por rangos de adulto y, si hay valores fuera de rango, el evento
 * VitalSignsOutOfRange para el módulo de alertas críticas.
 */
class VitalSignService
{
    /** [etiqueta, unidad, normal mínimo, normal máximo, crítico bajo, crítico alto, límite físico mínimo, límite físico máximo] */
    public const SIGNS = [
        'temperature' => ['Temperatura', '°C', 36.0, 37.5, 35.0, 39.9, 25, 45],
        'heart_rate' => ['Frecuencia cardíaca', 'lpm', 60, 100, 40, 130, 20, 300],
        'respiratory_rate' => ['Frecuencia respiratoria', 'rpm', 12, 20, 8, 30, 1, 80],
        'systolic_bp' => ['Presión sistólica', 'mmHg', 90, 139, 70, 180, 40, 300],
        'diastolic_bp' => ['Presión diastólica', 'mmHg', 60, 89, 40, 110, 20, 200],
        'oxygen_saturation' => ['Saturación de oxígeno', '%', 95, 100, 90, 101, 50, 100],
        'glucose' => ['Glucosa', 'mg/dL', 70, 140, 54, 300, 10, 1000],
        'weight' => ['Peso', 'kg', null, null, null, null, 0.3, 400],
        'height' => ['Talla', 'cm', null, null, null, null, 20, 260],
    ];

    public function __construct(private readonly ClinicalRecordService $records, private readonly UserRefSync $refs, private readonly AuditTrail $audit) {}

    /** @param array<string, mixed> $values */
    public function record(CentralUser $actor, Hospital $hospital, LocalPatient $patient, ?string $episodeId, array $values, ?string $measuredAt, ?string $ip): VitalSign
    {
        $measures = [];
        $invalid = [];
        foreach (self::SIGNS as $key => $def) {
            if (! isset($values[$key]) || $values[$key] === '' || $values[$key] === null) {
                continue;
            }
            $value = (float) $values[$key];
            if (! is_numeric($values[$key]) || $value < $def[6] || $value > $def[7]) {
                $invalid[$key] = "{$def[0]}: debe estar entre {$def[6]} y {$def[7]} {$def[1]}.";

                continue;
            }
            $measures[$key] = in_array($key, ['heart_rate', 'respiratory_rate', 'systolic_bp', 'diastolic_bp'], true) ? (int) round($value) : $value;
        }
        if ($invalid !== []) {
            throw new DomainRuleException('MEDICION_INVALIDA', 'Hay valores que no son posibles. Revise lo que escribió.', 422, ['campos' => $invalid]);
        }
        if ($measures === []) {
            throw new DomainRuleException('TOMA_VACIA', 'Escriba al menos una medición.');
        }
        $measured = $measuredAt ? CarbonImmutable::parse($measuredAt) : CarbonImmutable::now()->subSecond();
        if ($measured > CarbonImmutable::now() || $measured < CarbonImmutable::now()->subDays(2)) {
            throw new DomainRuleException('HORA_INVALIDA', 'La hora de la toma no puede ser futura ni de hace más de 2 días.');
        }

        $vital = DB::connection('hospital')->transaction(function () use ($actor, $hospital, $patient, $episodeId, $measures, $measured, $ip): VitalSign {
            $episode = $this->records->currentEpisode($actor, $hospital, $patient, $episodeId, $ip);
            $ref = $this->refs->forUser($actor, $hospital, 'enfermeria');
            if ($ref->role_code === 'medico') {
                $ref = $this->records->doctorAuthor($actor, $hospital, $episode);
            } elseif ($ref->role_code !== 'enfermeria') {
                throw new DomainRuleException('ROL_NO_PERMITIDO', 'Solo enfermería o el médico tratante registran signos vitales.', 403);
            }

            return VitalSign::query()->create($measures + [
                'medical_record_id' => $this->records->recordFor($patient)->medical_record_id, 'care_episode_id' => $episode->care_episode_id,
                'recorded_by_user_ref_id' => $ref->local_user_ref_id, 'measured_at' => $measured, 'recorded_at' => now(),
                'status' => 'validated', 'validated_at' => now(), 'version_no' => 1,
            ]);
        });

        $findings = self::findings($vital);
        $this->audit->record('signos_vitales', 'SIGNOS_REGISTRADOS', 'exito', $hospital->hospital_id, $actor->user_id, 'paciente:'.$patient->code,
            ['toma' => $vital->vital_sign_id, 'fuera_de_rango' => array_column($findings, 'signo')], $ip);
        if ($findings !== []) {
            VitalSignsOutOfRange::dispatch($hospital->hospital_id, $patient->local_patient_id, $patient->code, $vital->care_episode_id, $vital->vital_sign_id, $findings);
        }

        return $vital;
    }

    /** @return list<array{signo: string, etiqueta: string, valor: float, nivel: string}> */
    public static function findings(VitalSign $vital): array
    {
        $out = [];
        foreach (self::SIGNS as $key => [$label, , $min, $max, $critLow, $critHigh]) {
            $value = $vital->getAttribute($key);
            if ($value === null || $min === null) {
                continue;
            }
            $level = self::classify((float) $value, $min, $max, $critLow, $critHigh);
            if ($level !== 'normal') {
                $out[] = ['signo' => $key, 'etiqueta' => $label, 'valor' => (float) $value, 'nivel' => $level];
            }
        }

        return $out;
    }

    public static function classify(float $value, float $min, float $max, float $critLow, float $critHigh): string
    {
        if ($value < $critLow || $value > $critHigh) {
            return 'critica';
        }

        return $value < $min || $value > $max ? 'anormal' : 'normal';
    }
}
