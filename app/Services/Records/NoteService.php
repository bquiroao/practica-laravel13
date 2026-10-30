<?php

namespace App\Services\Records;

use App\Exceptions\DomainRuleException;
use App\Models\Central\CentralUser;
use App\Models\Central\Hospital;
use App\Models\Hospital\ClinicalNote;
use App\Models\Hospital\Diagnosis;
use App\Models\Hospital\LocalPatient;
use App\Services\Audit\AuditTrail;
use Illuminate\Support\Facades\DB;

/**
 * Módulo notas SOAP y diagnósticos: las cuatro secciones obligatorias, diagnósticos con código CIE-10, firma que
 * vuelve la nota inmutable y corrección como versión nueva con motivo de al menos 10 caracteres.
 */
class NoteService
{
    public const MOTIVO_MINIMO = 10;

    private const SECTIONS = ['s' => 'Subjetivo', 'o' => 'Objetivo', 'a' => 'Análisis', 'p' => 'Plan'];

    public function __construct(private readonly ClinicalRecordService $records, private readonly AuditTrail $audit) {}

    /**
     * @param  array{s: string, o: string, a: string, p: string}  $soap
     * @param  list<array{code: string, description: string, type: string}>  $diagnoses
     */
    public function create(CentralUser $actor, Hospital $hospital, LocalPatient $patient, ?string $episodeId, array $soap, array $diagnoses, bool $sign, ?string $ip): ClinicalNote
    {
        $soap = $this->validSoap($soap);
        $diagnoses = $this->validDiagnoses($diagnoses);

        return DB::connection('hospital')->transaction(function () use ($actor, $hospital, $patient, $episodeId, $soap, $diagnoses, $sign, $ip): ClinicalNote {
            $episode = $this->records->currentEpisode($actor, $hospital, $patient, $episodeId, $ip);
            $author = $this->records->doctorAuthor($actor, $hospital, $episode);
            $note = ClinicalNote::query()->create([
                'medical_record_id' => $this->records->recordFor($patient)->medical_record_id, 'care_episode_id' => $episode->care_episode_id,
                'author_user_ref_id' => $author->local_user_ref_id, 'note_type' => 'SOAP', 'content' => json_encode($soap, JSON_UNESCAPED_UNICODE),
                'status' => $sign ? 'signed' : 'draft', 'signed_at' => $sign ? now() : null, 'version_no' => 1,
            ]);
            $this->attachDiagnoses($note, $diagnoses, $author->local_user_ref_id);
            $this->audit->record('notas', $sign ? 'NOTA_FIRMADA' : 'NOTA_BORRADOR', 'exito', $hospital->hospital_id, $actor->user_id, 'paciente:'.$patient->code,
                ['nota' => $note->clinical_note_id, 'diagnosticos' => array_column($diagnoses, 'code')], $ip);

            return $note;
        });
    }

    /** Un borrador se puede completar y firmar; una vez firmado ya no cambia. */
    public function updateDraft(CentralUser $actor, Hospital $hospital, ClinicalNote $note, array $soap, array $diagnoses, bool $sign, ?string $ip): ClinicalNote
    {
        if ($note->status === 'signed') {
            throw new DomainRuleException('NOTA_FIRMADA', 'La nota ya está firmada y no se puede modificar. Use «Corregir».', 409);
        }
        $soap = $this->validSoap($soap);
        $diagnoses = $this->validDiagnoses($diagnoses);
        $author = $this->records->doctorAuthor($actor, $hospital, $note->episode()->firstOrFail());
        if ($author->local_user_ref_id !== $note->author_user_ref_id) {
            throw new DomainRuleException('NO_ES_AUTOR', 'Solo quien escribió el borrador puede completarlo.', 403);
        }

        return DB::connection('hospital')->transaction(function () use ($actor, $hospital, $note, $soap, $diagnoses, $sign, $author, $ip): ClinicalNote {
            Diagnosis::query()->where('clinical_note_id', $note->clinical_note_id)->update(['clinical_note_id' => null, 'status' => 'entered_in_error', 'resolved_at' => now()->toDateString()]);
            $note->forceFill(['content' => json_encode($soap, JSON_UNESCAPED_UNICODE), 'status' => $sign ? 'signed' : 'draft', 'signed_at' => $sign ? now() : null])->save();
            $this->attachDiagnoses($note, $diagnoses, $author->local_user_ref_id);
            $this->audit->record('notas', $sign ? 'NOTA_FIRMADA' : 'NOTA_BORRADOR', 'exito', $hospital->hospital_id, $actor->user_id, 'nota:'.$note->clinical_note_id, [], $ip);

            return $note->fresh();
        });
    }

    public function correct(CentralUser $actor, Hospital $hospital, ClinicalNote $note, array $soap, array $diagnoses, string $reason, ?string $ip): ClinicalNote
    {
        if ($note->status !== 'signed') {
            throw new DomainRuleException('NOTA_NO_FIRMADA', 'Solo se corrige una nota firmada; un borrador todavía se puede editar y firmar.', 409);
        }
        if (ClinicalNote::query()->where('supersedes_id', $note->clinical_note_id)->exists()) {
            throw new DomainRuleException('NOTA_YA_CORREGIDA', 'Esta nota ya tiene una corrección. Corrija la versión más reciente.', 409);
        }
        if (preg_match_all('/./su', trim($reason)) < self::MOTIVO_MINIMO) {
            throw new DomainRuleException('MOTIVO_CORRECCION_INSUFICIENTE', 'Explique el motivo de la corrección (al menos '.self::MOTIVO_MINIMO.' caracteres).');
        }
        $soap = $this->validSoap($soap);
        $diagnoses = $this->validDiagnoses($diagnoses);

        return DB::connection('hospital')->transaction(function () use ($actor, $hospital, $note, $soap, $diagnoses, $reason, $ip): ClinicalNote {
            $episode = $note->episode()->firstOrFail();
            $author = $this->records->doctorAuthor($actor, $hospital, $episode);
            $new = ClinicalNote::query()->create([
                'medical_record_id' => $note->medical_record_id, 'care_episode_id' => $note->care_episode_id, 'author_user_ref_id' => $author->local_user_ref_id,
                'note_type' => 'SOAP', 'content' => json_encode($soap, JSON_UNESCAPED_UNICODE), 'status' => 'signed', 'signed_at' => now(),
                'version_no' => $note->version_no + 1, 'correction_reason' => trim($reason), 'supersedes_id' => $note->clinical_note_id,
            ]);
            // Los diagnósticos de la versión corregida dejan de estar vigentes; los de la corrección los reemplazan.
            Diagnosis::query()->where('clinical_note_id', $note->clinical_note_id)->where('status', 'active')
                ->update(['status' => 'entered_in_error', 'resolved_at' => now()->toDateString()]);
            $this->attachDiagnoses($new, $diagnoses, $author->local_user_ref_id);
            $this->audit->record('notas', 'NOTA_CORREGIDA', 'exito', $hospital->hospital_id, $actor->user_id, 'nota:'.$note->clinical_note_id,
                ['correccion' => $new->clinical_note_id, 'motivo' => trim($reason)], $ip);

            return $new;
        });
    }

    /** @return array{s: string, o: string, a: string, p: string} */
    private function validSoap(array $soap): array
    {
        $clean = [];
        foreach (self::SECTIONS as $key => $label) {
            $clean[$key] = trim((string) ($soap[$key] ?? ''));
            if ($clean[$key] === '') {
                throw new DomainRuleException('NOTA_INCOMPLETA', "La sección «{$label}» no puede estar vacía.", 422, ['campo' => $key]);
            }
        }

        return $clean;
    }

    /** @return list<array{code: string, description: string, type: string}> */
    private function validDiagnoses(array $diagnoses): array
    {
        $clean = [];
        foreach ($diagnoses as $i => $d) {
            $code = strtoupper(trim((string) ($d['code'] ?? '')));
            $description = trim((string) ($d['description'] ?? ''));
            $type = (string) ($d['type'] ?? 'primary');
            if (! preg_match('/^[A-Z]\d{2}(\.\d{1,2})?$/', $code)) {
                throw new DomainRuleException('DIAGNOSTICO_INVALIDO', "El código «{$code}» no tiene formato CIE-10 (ejemplo: J18.9).", 422, ['diagnostico' => $i]);
            }
            if ($description === '') {
                throw new DomainRuleException('DIAGNOSTICO_INVALIDO', 'Escriba la descripción del diagnóstico.', 422, ['diagnostico' => $i]);
            }
            if (! in_array($type, ['primary', 'secondary', 'differential'], true)) {
                throw new DomainRuleException('DIAGNOSTICO_INVALIDO', 'El tipo de diagnóstico no es válido.', 422, ['diagnostico' => $i]);
            }
            $clean[] = ['code' => $code, 'description' => $description, 'type' => $type];
        }
        if (count(array_filter($clean, fn ($d) => $d['type'] === 'primary')) > 1) {
            throw new DomainRuleException('DIAGNOSTICO_INVALIDO', 'Solo puede haber un diagnóstico principal por nota.');
        }

        return $clean;
    }

    private function attachDiagnoses(ClinicalNote $note, array $diagnoses, string $authorRef): void
    {
        foreach ($diagnoses as $d) {
            Diagnosis::query()->create($d + ['care_episode_id' => $note->care_episode_id, 'clinical_note_id' => $note->clinical_note_id,
                'author_user_ref_id' => $authorRef, 'status' => 'active', 'onset_date' => now()->toDateString()]);
        }
    }
}
