<?php

namespace App\Services\Allergies;

use App\Exceptions\DomainRuleException;
use App\Models\Central\CentralUser;
use App\Models\Central\Hospital;
use App\Models\Hospital\Allergy;
use App\Models\Hospital\AllergyAcknowledgement;
use App\Models\Hospital\LocalPatient;
use App\Services\Audit\AuditTrail;
use App\Services\Hospital\UserRefSync;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Módulo alergias clínicas y alerta visual en el expediente: es el único que escribe en `allergies`.
 * Prescripciones las lee por su cuenta para bloquear o advertir, así que lo que se registra aquí aplica allí sin más.
 */
class AllergyService
{
    public const TYPES = ['allergy' => 'Alergia', 'intolerance' => 'Intolerancia', 'unknown' => 'Sin clasificar'];

    public const SEVERITIES = ['mild' => 'Leve', 'moderate' => 'Moderada', 'severe' => 'Grave', 'life_threatening' => 'Pone en riesgo la vida', 'unknown' => 'Desconocida'];

    public const STATUSES = ['active' => 'Activa', 'inactive' => 'Inactiva', 'resolved' => 'Resuelta'];

    public function __construct(private readonly AlertCalculator $calculator, private readonly UserRefSync $refs, private readonly AuditTrail $audit) {}

    /**
     * CU-03: la alerta del expediente, con sus alergias activas de mayor a menor riesgo, y si este usuario ya la
     * reconoció (RN-09). SIN_REGISTRO no significa «sin alergias»: solo que no hay ninguna anotada (DEC-02).
     *
     * @return array{level: AlertLevel, detail: list<array{allergy: Allergy, level: AlertLevel}>, fingerprint: string, acknowledged: bool, history: list<Allergy>}
     */
    public function alert(CentralUser $user, Hospital $hospital, LocalPatient $patient): array
    {
        $all = Allergy::query()->where('local_patient_id', $patient->local_patient_id)->orderByDesc('recorded_at')->get();
        $alert = $this->calculator->calculate($all->where('status', 'active')->values());
        $acknowledged = $alert['level']->requiresAcknowledgement() && AllergyAcknowledgement::query()
            ->where('local_patient_id', $patient->local_patient_id)->where('alert_fingerprint', $alert['fingerprint'])
            ->where('acknowledged_by_user_ref_id', $this->refs->forUser($user, $hospital)->local_user_ref_id)->exists();

        return $alert + ['acknowledged' => $acknowledged, 'history' => $all->where('status', '<>', 'active')->values()->all()];
    }

    /**
     * CU-01 y CU-02. La forma de los datos (RN-01, RN-02, RN-04, RN-05) la valida AllergyRequest.
     *
     * @param  array{allergen_name?: string|null, allergen_code?: string|null, allergy_type: string, severity: string, reaction?: string|null, onset_date?: string|null}  $data
     */
    public function register(CentralUser $actor, Hospital $hospital, LocalPatient $patient, array $data, ?string $ip): Allergy
    {
        if ($patient->status !== 'active') {                                                        // RN-06
            throw new DomainRuleException('PACIENTE_INACTIVO', 'Solo se registran alergias de pacientes activos.', 422);
        }
        $name = self::clean($data['allergen_name'] ?? null);
        $code = self::clean($data['allergen_code'] ?? null);
        $this->ensureNotDuplicated($patient, $data['allergy_type'], $name, $code);                  // RN-03

        try {
            $allergy = Allergy::query()->create([
                'local_patient_id' => $patient->local_patient_id,
                'verified_by_user_ref_id' => $this->refs->forUser($actor, $hospital)->local_user_ref_id, // RN-07
                'allergen_name' => $name, 'allergen_code' => $code !== null ? mb_strtoupper($code) : null,
                'allergy_type' => $data['allergy_type'], 'severity' => $data['severity'],
                'reaction' => self::clean($data['reaction'] ?? null), 'onset_date' => $data['onset_date'] ?? null,
                'status' => 'active', 'recorded_at' => now(),
            ]);
        } catch (QueryException $e) {
            // La misma regla en la base (índice allergies_active_equivalent_unique), por si dos personas registran a la vez.
            if ($e->getCode() === '23505') {
                throw $this->duplicated($name ?? $code);
            }
            throw $e;
        }

        $this->audit->record('alergias', 'ALERGIA_REGISTRADA', 'exito', $hospital->hospital_id, $actor->user_id, 'paciente:'.$patient->code,
            ['alergia' => $allergy->allergy_id, 'alergeno' => AlertCalculator::allergen($allergy), 'severidad' => $allergy->severity,
                'nivel' => $this->calculator->levelFor($allergy)->value], $ip);

        return $allergy;
    }

    /** CU-06 y RN-10: una alergia no se borra; se inactiva (fue un error o ya no aplica) o se resuelve, con fecha. */
    public function changeStatus(CentralUser $actor, Hospital $hospital, Allergy $allergy, string $status, string $reason, ?string $ip): Allergy
    {
        if ($allergy->status !== 'active') {
            throw new DomainRuleException('ALERGIA_NO_ACTIVA', 'Esa alergia ya estaba '.mb_strtolower(self::STATUSES[$allergy->status]).'.', 409);
        }
        // La fecha de inicio nunca es futura (RN-05), así que hoy siempre cumple resolved_at >= onset_date.
        $allergy->forceFill(['status' => $status, 'resolved_at' => now($hospital->timezone ?: config('app.timezone'))->toDateString()])->save();
        $patient = LocalPatient::query()->find($allergy->local_patient_id);

        $this->audit->record('alergias', $status === 'resolved' ? 'ALERGIA_RESUELTA' : 'ALERGIA_INACTIVADA', 'exito', $hospital->hospital_id, $actor->user_id,
            'paciente:'.$patient?->code, ['alergia' => $allergy->allergy_id, 'alergeno' => AlertCalculator::allergen($allergy), 'motivo' => trim($reason)], $ip);

        return $allergy;
    }

    /** CU-05 y RN-09: solo vale para la huella que el usuario vio; si las alergias cambiaron, se vuelve a revisar. */
    public function acknowledge(CentralUser $actor, Hospital $hospital, LocalPatient $patient, string $fingerprint, ?string $ip): AllergyAcknowledgement
    {
        $alert = $this->calculator->calculate(Allergy::query()->where('local_patient_id', $patient->local_patient_id)->where('status', 'active')->get());
        if (! $alert['level']->requiresAcknowledgement()) {
            throw new DomainRuleException('RECONOCIMIENTO_NO_REQUERIDO', 'Esta alerta no requiere reconocimiento.', 422);
        }
        if (! hash_equals($alert['fingerprint'], $fingerprint)) {
            throw new DomainRuleException('ALERTA_CAMBIO', 'Las alergias cambiaron desde que abrió la alerta. Revísela de nuevo.', 409);
        }
        $ref = $this->refs->forUser($actor, $hospital);

        return DB::connection('hospital')->transaction(function () use ($actor, $hospital, $patient, $alert, $ref, $ip): AllergyAcknowledgement {
            $existing = AllergyAcknowledgement::query()->where('local_patient_id', $patient->local_patient_id)
                ->where('acknowledged_by_user_ref_id', $ref->local_user_ref_id)->where('alert_fingerprint', $alert['fingerprint'])->first();
            if ($existing !== null) {
                return $existing; // Reconocer dos veces la misma alerta no cambia nada.
            }
            $ack = AllergyAcknowledgement::query()->create(['local_patient_id' => $patient->local_patient_id, 'acknowledged_by_user_ref_id' => $ref->local_user_ref_id,
                'alert_level' => $alert['level']->value, 'alert_fingerprint' => $alert['fingerprint'], 'acknowledged_at' => now()]);
            $this->audit->record('alergias', 'ALERTA_RECONOCIDA', 'exito', $hospital->hospital_id, $actor->user_id, 'paciente:'.$patient->code,
                ['nivel' => $alert['level']->value, 'huella' => $alert['fingerprint']], $ip);

            return $ack;
        });
    }

    /**
     * RN-03 y defecto D-01 de la semana 15: dos alergias activas del mismo tipo son la misma si coincide su clave
     * (código o, si no hay, nombre) **o** coinciden el nombre o el código cuando ambas lo traen, sin importar
     * mayúsculas ni espacios. Así «Penicilina» y «J01CA04 · Penicilina» ya no quedan duplicadas.
     */
    private function ensureNotDuplicated(LocalPatient $patient, string $type, ?string $name, ?string $code): void
    {
        $key = fn (?string $v) => $v === null ? null : mb_strtolower($v);
        $match = Allergy::query()->where('local_patient_id', $patient->local_patient_id)->where('allergy_type', $type)->where('status', 'active')->get()
            ->first(function (Allergy $a) use ($key, $name, $code) {
                $otherName = $key(self::clean($a->allergen_name));
                $otherCode = $key(self::clean($a->allergen_code));

                return ($name !== null && $key($name) === $otherName) || ($code !== null && $key($code) === $otherCode)
                    || $key($code ?? $name) === $key(self::clean($a->allergen_code) ?? self::clean($a->allergen_name));
            });
        if ($match !== null) {
            throw $this->duplicated(AlertCalculator::allergen($match));
        }
    }

    private function duplicated(?string $allergen): DomainRuleException
    {
        return new DomainRuleException('ALERGIA_DUPLICADA', "El paciente ya tiene una alergia activa a «{$allergen}» del mismo tipo.", 409);
    }

    /** Texto sin espacios de más; vacío = null. */
    private static function clean(?string $value): ?string
    {
        $value = trim((string) preg_replace('/\s+/u', ' ', (string) $value));

        return $value === '' ? null : $value;
    }
}
