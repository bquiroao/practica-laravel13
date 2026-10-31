<?php

namespace App\Services\Records;

use App\Exceptions\DomainRuleException;
use App\Models\Central\CentralUser;
use App\Models\Central\Hospital;
use App\Models\Hospital\Appointment;
use App\Models\Hospital\CareAssignment;
use App\Models\Hospital\CareEpisode;
use App\Models\Hospital\DoctorProfile;
use App\Models\Hospital\LocalPatient;
use App\Models\Hospital\LocalUserRef;
use App\Models\Hospital\MedicalRecord;
use App\Services\Audit\AuditTrail;
use App\Services\Hospital\UserRefSync;
use Illuminate\Support\Facades\DB;

/**
 * Módulo expediente médico: un expediente por paciente y los episodios de atención (consulta externa o internamiento)
 * a los que se cuelgan las notas y los signos vitales.
 */
class ClinicalRecordService
{
    public function __construct(private readonly UserRefSync $refs, private readonly AuditTrail $audit) {}

    public function recordFor(LocalPatient $patient): MedicalRecord
    {
        return MedicalRecord::query()->firstOrCreate(['local_patient_id' => $patient->local_patient_id],
            ['record_number' => 'EXP-'.$patient->code, 'opened_at' => now(), 'status' => 'open']);
    }

    /**
     * Episodio donde se escribe. Si el paciente no tiene uno abierto, se abre una consulta externa: así quien atiende
     * no tiene que entender qué es un «episodio» para empezar a escribir.
     */
    public function currentEpisode(CentralUser $actor, Hospital $hospital, LocalPatient $patient, ?string $episodeId, ?string $ip): CareEpisode
    {
        if ($patient->status !== 'active') {
            throw new DomainRuleException('PACIENTE_NO_ACTIVO', 'La ficha del paciente no está activa: no se pueden agregar registros.');
        }
        if ($episodeId !== null) {
            $episode = CareEpisode::query()->where('local_patient_id', $patient->local_patient_id)->find($episodeId);
            if ($episode === null || $episode->status !== 'open') {
                throw new DomainRuleException('EPISODIO_NO_DISPONIBLE', 'Esa atención ya está cerrada. Elija la atención actual.', 409);
            }

            return $episode;
        }

        return CareEpisode::query()->where('local_patient_id', $patient->local_patient_id)->where('status', 'open')
            ->orderByRaw("type = 'inpatient' DESC")->orderByDesc('started_at')->first()
            ?? $this->startConsultation($actor, $hospital, $patient, null, $ip);
    }

    public function startConsultation(CentralUser $actor, Hospital $hospital, LocalPatient $patient, ?Appointment $appointment, ?string $ip): CareEpisode
    {
        $episode = CareEpisode::query()->create([
            'local_patient_id' => $patient->local_patient_id, 'type' => 'outpatient', 'status' => 'open', 'started_at' => now()->subSecond(),
            'reason' => $appointment?->reason ?? 'Consulta externa', 'appointment_id' => $appointment?->appointment_id,
        ]);
        $this->audit->record('expediente', 'CONSULTA_INICIADA', 'exito', $hospital->hospital_id, $actor->user_id, 'paciente:'.$patient->code,
            ['episodio' => $episode->care_episode_id], $ip);

        return $episode;
    }

    public function endConsultation(CentralUser $actor, Hospital $hospital, CareEpisode $episode, ?string $ip): CareEpisode
    {
        if ($episode->type !== 'outpatient' || $episode->status !== 'open') {
            throw new DomainRuleException('EPISODIO_NO_DISPONIBLE', 'Solo se termina una consulta externa abierta. Un internamiento se cierra con el alta.', 409);
        }
        $episode->forceFill(['status' => 'closed', 'ended_at' => now()])->save();
        $this->audit->record('expediente', 'CONSULTA_TERMINADA', 'exito', $hospital->hospital_id, $actor->user_id, 'episodio:'.$episode->care_episode_id, [], $ip);

        return $episode;
    }

    /** El médico que escribe queda asignado al episodio (la base exige la asignación para firmar notas). */
    public function doctorAuthor(CentralUser $actor, Hospital $hospital, CareEpisode $episode): LocalUserRef
    {
        $ref = $this->refs->forUser($actor, $hospital, 'medico');
        $doctor = DoctorProfile::query()->active()->where('local_user_ref_id', $ref->local_user_ref_id)->first()
            ?? throw new DomainRuleException('SIN_PERFIL_MEDICO', 'Solo un médico con perfil registrado en este hospital escribe notas clínicas.', 403);
        $specialty = $doctor->specialties()->validOn(now()->toDateString())->orderByDesc('is_primary')->value('specialty_id')
            ?? throw new DomainRuleException('SIN_ESPECIALIDAD', 'El médico no tiene una especialidad vigente.', 403);

        DB::connection('hospital')->transaction(function () use ($doctor, $episode, $specialty, $ref): void {
            $assigned = CareAssignment::query()->where('care_episode_id', $episode->care_episode_id)->where('doctor_profile_id', $doctor->doctor_profile_id)
                ->where('status', 'active')->whereNull('valid_to')->exists();
            if (! $assigned) {
                CareAssignment::query()->create([
                    'care_episode_id' => $episode->care_episode_id, 'doctor_profile_id' => $doctor->doctor_profile_id, 'specialty_id' => $specialty,
                    'assigned_by_user_ref_id' => $ref->local_user_ref_id, 'scope' => ['expediente' => 'escritura'],
                    'reason' => 'Médico tratante', 'source' => $episode->appointment_id ? 'appointment' : 'episode', 'status' => 'active', 'valid_from' => now()->subSecond(),
                ]);
            }
        });

        return $ref;
    }
}
