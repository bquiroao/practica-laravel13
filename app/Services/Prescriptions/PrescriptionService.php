<?php

namespace App\Services\Prescriptions;

use App\Exceptions\DomainRuleException;
use App\Models\Central\CentralUser;
use App\Models\Central\Hospital;
use App\Models\Hospital\Allergy;
use App\Models\Hospital\CareEpisode;
use App\Models\Hospital\LocalPatient;
use App\Models\Hospital\Prescription;
use App\Services\Audit\AuditTrail;
use App\Services\Catalogs\MedicationDirectory;
use App\Services\Hospital\UserRefSync;
use Illuminate\Support\Str;

/**
 * Módulo prescripciones con validación de alergias. Antes de emitir se cruzan las alergias activas del paciente con el
 * medicamento (por nombre y por familia, p. ej. penicilina ↔ amoxicilina):
 *  - alergia grave o que pone en riesgo la vida → BLOQUEO_ALERGIA / BLOQUEO_ALERGIA_CRUZADA (no se emite);
 *  - alergia leve, moderada o de severidad desconocida → CONFIRMACION_REQUERIDA (se emite solo con un motivo).
 */
class PrescriptionService
{
    private const BLOCKING = ['severe', 'life_threatening'];

    public function __construct(private readonly MedicationDirectory $medications, private readonly UserRefSync $refs, private readonly AuditTrail $audit) {}

    /** @param array<string, mixed> $data */
    public function issue(CentralUser $actor, Hospital $hospital, LocalPatient $patient, array $data, ?string $overrideReason, ?string $ip): Prescription
    {
        if ($patient->status !== 'active') {
            throw new DomainRuleException('PACIENTE_NO_ACTIVO', 'La ficha del paciente no está activa.');
        }
        $med = $this->medications->find((string) $data['medication_code']) ?? throw new DomainRuleException('MEDICAMENTO_NO_ENCONTRADO', 'El medicamento no está en el catálogo.');

        $findings = $this->findings($patient, $med);
        $blocking = array_values(array_filter($findings, fn ($f) => in_array($f['severity'], self::BLOCKING, true)));
        if ($blocking !== []) {
            $cross = $blocking[0]['match'] === 'familia';
            $this->audit->record('prescripciones', 'PRESCRIPCION_BLOQUEADA', 'denegado', $hospital->hospital_id, $actor->user_id, 'paciente:'.$patient->code,
                ['medicamento' => $med['code'], 'alergeno' => $blocking[0]['allergen']], $ip);
            throw new DomainRuleException($cross ? 'BLOQUEO_ALERGIA_CRUZADA' : 'BLOQUEO_ALERGIA',
                "No se puede recetar {$med['name']}: el paciente tiene alergia ".self::severityLabel($blocking[0]['severity'])." a {$blocking[0]['allergen']}"
                .($cross ? ' (misma familia de medicamentos)' : '').'. Elija otro medicamento.', 409, ['hallazgos' => $findings]);
        }
        if ($findings !== [] && mb_strlen(trim((string) $overrideReason)) < 10) {
            throw new DomainRuleException('CONFIRMACION_REQUERIDA', 'El paciente tiene una alergia relacionada. Para recetar de todos modos, explique por qué (al menos 10 caracteres).', 409,
                ['hallazgos' => $findings]);
        }

        $episode = CareEpisode::query()->where('local_patient_id', $patient->local_patient_id)->where('status', 'open')->orderByRaw("type = 'inpatient' DESC")->value('care_episode_id');
        $prescription = Prescription::query()->create([
            'local_patient_id' => $patient->local_patient_id, 'care_episode_id' => $episode,
            'prescriber_user_ref_id' => $this->refs->forUser($actor, $hospital, 'medico')->local_user_ref_id,
            'medication_code' => $med['code'], 'medication_name' => $med['name'], 'dose' => trim((string) $data['dose']), 'route' => (string) ($data['route'] ?? $med['route']),
            'frequency' => trim((string) $data['frequency']), 'duration_days' => $data['duration_days'] ?? null, 'instructions' => trim((string) ($data['instructions'] ?? '')) ?: null,
            'status' => 'issued', 'allergy_override_reason' => $findings !== [] ? trim((string) $overrideReason) : null, 'issued_at' => now(),
        ]);
        $this->audit->record('prescripciones', 'PRESCRIPCION_EMITIDA', 'exito', $hospital->hospital_id, $actor->user_id, 'paciente:'.$patient->code,
            ['receta' => $prescription->prescription_id, 'medicamento' => $med['code']] + ($findings !== [] ? ['alerta_confirmada' => trim((string) $overrideReason)] : []), $ip);

        return $prescription;
    }

    public function cancel(CentralUser $actor, Hospital $hospital, Prescription $prescription, string $reason, ?string $ip): Prescription
    {
        if ($prescription->status === 'cancelled') {
            throw new DomainRuleException('YA_ANULADA', 'La receta ya está anulada.', 409);
        }
        if (mb_strlen(trim($reason)) < 10) {
            throw new DomainRuleException('MOTIVO_REQUERIDO', 'Explique por qué se anula la receta (al menos 10 caracteres).');
        }
        $prescription->forceFill(['status' => 'cancelled', 'cancel_reason' => trim($reason), 'cancelled_at' => now()])->save();
        $this->audit->record('prescripciones', 'PRESCRIPCION_ANULADA', 'exito', $hospital->hospital_id, $actor->user_id, 'receta:'.$prescription->prescription_id, ['motivo' => trim($reason)], $ip);

        return $prescription;
    }

    /**
     * Alergias activas del paciente que chocan con el medicamento.
     *
     * @param  array<string, mixed>  $med
     * @return list<array{allergen: string, severity: string, match: string}>
     */
    public function findings(LocalPatient $patient, array $med): array
    {
        $families = config('nido_catalogos.familias_alergia');
        $medName = Str::lower(Str::ascii($med['name'].' '.$med['code']));
        $out = [];
        foreach (Allergy::query()->where('local_patient_id', $patient->local_patient_id)->where('status', 'active')->get() as $a) {
            $allergen = trim((string) ($a->allergen_name ?: $a->allergen_code));
            $needle = Str::lower(Str::ascii($allergen));
            if ($needle === '') {
                continue;
            }
            $direct = str_contains($medName, $needle) || collect(explode(' ', $medName))->contains(fn ($w) => strlen($w) > 4 && str_contains($needle, $w));
            $family = collect($med['groups'] ?? [])->contains(fn ($g) => collect($families[$g] ?? [])->contains(fn ($word) => str_contains($needle, $word)));
            if ($direct || $family) {
                $out[] = ['allergen' => $allergen, 'severity' => (string) $a->severity, 'match' => $direct ? 'directa' : 'familia'];
            }
        }

        return $out;
    }

    public static function severityLabel(string $severity): string
    {
        return ['mild' => 'leve', 'moderate' => 'moderada', 'severe' => 'grave', 'life_threatening' => 'que pone en riesgo la vida', 'unknown' => 'de severidad desconocida'][$severity] ?? $severity;
    }
}
