<?php

namespace App\Services\Admissions;

use App\Exceptions\DomainRuleException;
use App\Models\Central\AuditLog;
use App\Models\Central\CentralUser;
use App\Models\Central\Hospital;
use App\Models\Hospital\AdmissionOrder;
use App\Models\Hospital\Bed;
use App\Models\Hospital\BedStay;
use App\Models\Hospital\CareEpisode;
use App\Models\Hospital\LocalPatient;
use App\Services\Access\PermissionResolver;
use App\Services\Admissions\Eligibility\BedRequirement;
use App\Services\Admissions\Eligibility\EligibilityEvaluator;
use App\Services\Audit\AuditTrail;
use App\Services\Hospital\UserRefSync;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Módulo admisión hospitalaria y asignación de cama (CU-01): abre el internamiento (care_episode «inpatient») y su
 * estancia en una cama disponible, todo en una transacción. Traslados y altas trabajan después sobre esa estancia.
 *
 * RN-01 la garantiza la base (exclusión de bed_stays: una estancia abierta por cama). RN-02 (un internamiento abierto
 * por paciente) se valida con la fila del paciente bloqueada, porque el esquema base admite varios episodios abiertos.
 */
class AdmissionService
{
    public const SOURCES = ['emergencia' => 'Emergencia', 'consulta_externa' => 'Consulta externa', 'referencia' => 'Referencia de otro centro'];

    public function __construct(
        private readonly EligibilityEvaluator $evaluator,
        private readonly UserRefSync $refs,
        private readonly AuditTrail $audit,
        private readonly PermissionResolver $permissions,
    ) {}

    /** CU-03: camas disponibles y compatibles con el requerimiento. Una lista vacía no es un error (E-05). */
    public function availableBeds(BedRequirement $requirement): Collection
    {
        return Bed::query()->with(['ward', 'openStay'])->where('status', 'available')
            ->whereHas('ward', fn ($q) => $q->where('status', 'active'))
            ->orderBy('code')->get()
            ->filter(fn (Bed $bed) => $this->evaluator->rejections($bed, $requirement) === [])
            ->sortBy(fn (Bed $bed) => $bed->ward->name.' '.$bed->code)->values();
    }

    /**
     * CU-01. Los comentarios A-NN / E-NN apuntan al diagrama de actividad de la semana 1 del módulo.
     *
     * @param  array{patient_id: string, bed_id: string, requirement: BedRequirement, source: string, reason: string}  $data
     */
    public function admit(CentralUser $actor, Hospital $hospital, array $data, ?string $ip, ?string $requestId): CareEpisode
    {
        $patient = LocalPatient::query()->find($data['patient_id']);
        if ($patient === null || $patient->status !== 'active') {                                     // A-04, A-05 / E-03
            throw new DomainRuleException('PACIENTE_NO_ENCONTRADO', 'El paciente no existe o no está activo en este hospital.', 404);
        }
        $this->ensureNoOpenAdmission($patient);                                                     // A-06 / E-04, RN-02 (aviso temprano)

        try {
            $episode = DB::connection('hospital')->transaction(function () use ($actor, $hospital, $data, $patient): CareEpisode {
                // CU-05: si otra transacción tiene la cama bloqueada más de 2 s, se avisa en lugar de esperar.
                DB::connection('hospital')->statement("SET LOCAL lock_timeout = '2s'");
                // RN-02 ante dos admisiones simultáneas del mismo paciente: la fila del paciente se bloquea, así la
                // segunda espera a la primera y ve su internamiento abierto.
                LocalPatient::query()->lockForUpdate()->find($patient->local_patient_id);
                $this->ensureNoOpenAdmission($patient);
                $bed = Bed::query()->with(['ward', 'openStay'])->lockForUpdate()->find($data['bed_id'])  // A-07, RN-04
                    ?? throw new DomainRuleException('CAMA_NO_ENCONTRADA', 'La cama no existe en este hospital.', 404);
                if ($bed->openStay !== null) {
                    throw new DomainRuleException('CAMA_YA_ASIGNADA', 'La cama ya fue asignada a otra admisión. Elija otra cama.', 409);
                }
                $reasons = $this->evaluator->rejections($bed, $data['requirement']);                // A-08 / E-05, RN-03
                if ($reasons !== []) {
                    throw new DomainRuleException('CAMA_NO_ELEGIBLE', 'La cama no es compatible: '.implode(' ', $reasons), 422, ['motivos' => $reasons]);
                }

                $now = now();
                $ref = $this->refs->forUser($actor, $hospital);
                // forceCreate: admission_source es columna de este módulo y no está en el fillable del modelo compartido.
                $episode = CareEpisode::query()->forceCreate([                                      // A-11
                    'local_patient_id' => $patient->local_patient_id, 'type' => 'inpatient', 'status' => 'open', 'started_at' => $now,
                    'reason' => trim($data['reason']), 'service_code' => $bed->ward->code, 'admission_source' => $data['source'],
                ]);
                // La estancia deja la cama «occupied» (trigger de la base, defecto AU-05). Aquí solo se anota el motivo.
                BedStay::query()->create(['care_episode_id' => $episode->care_episode_id, 'bed_id' => $bed->bed_id,      // A-12, RN-01
                    'assigned_by_user_ref_id' => $ref->local_user_ref_id, 'started_at' => $now, 'reason' => 'Admisión']);
                $bed->forceFill(['status' => 'occupied', 'status_reason' => 'Admisión del paciente', 'status_changed_at' => $now])->save();
                // Mejora: si el paciente esperaba con una orden de ingreso, la cama la cumple; de ahí sale la espera.
                AdmissionOrder::query()->where('local_patient_id', $patient->local_patient_id)->where('status', 'pendiente')->lockForUpdate()->first()
                    ?->forceFill(['status' => 'asignada', 'care_episode_id' => $episode->care_episode_id, 'assigned_at' => $now,
                        'assigned_by_user_ref_id' => $ref->local_user_ref_id])->save();

                return $episode;
            });
        } catch (QueryException $e) {
            throw $this->translate($e);                                                             // E-06
        }

        // A-14, RN-05: la trazabilidad va en la bitácora encadenada, solo si la admisión se guardó.
        $episode->load(['patient', 'stays.bed.ward']);
        $stay = $episode->stays->first();
        $detail = ['paciente' => $patient->code, 'cama' => $stay->bed->ward->code.'/'.$stay->bed->code, 'origen' => $data['source'],
            'rol' => implode(', ', $this->permissions->rolesFor($actor->user_id, $hospital->hospital_id))];
        $order = AdmissionOrder::query()->where('care_episode_id', $episode->care_episode_id)->first();
        if ($order !== null) {
            $detail += ['orden' => $order->admission_order_id, 'espera_minutos' => (int) $order->ordered_at->diffInMinutes($order->assigned_at)];
        }
        $resource = 'admision:'.$episode->care_episode_id;
        $this->audit->record('admisiones', 'ADMISION_CREADA', 'exito', $hospital->hospital_id, $actor->user_id, $resource, $detail, $ip, $requestId);
        $this->audit->record('admisiones', 'CAMA_ASIGNADA', 'exito', $hospital->hospital_id, $actor->user_id, $resource,
            $detail + ['cama_id' => $stay->bed_id], $ip, $requestId);

        return $episode;                                                                            // A-16
    }

    /**
     * CU-06: la trazabilidad de una admisión, en orden: su creación y la cama asignada, y después los traslados y el
     * alta que hicieron sobre ella (módulo traslados y altas).
     *
     * @return list<array{accion: string, usuario: string, detalle: array, ocurrido_en: string, solicitud: string|null}>
     */
    public function events(Hospital $hospital, CareEpisode $episode): array
    {
        $episode->loadMissing('patient');
        $logs = AuditLog::query()->where('hospital_id', $hospital->hospital_id)
            ->where(function ($q) use ($episode) {
                $q->where('recurso', 'admision:'.$episode->care_episode_id)
                    ->orWhere(fn ($t) => $t->where('modulo', 'traslados')->where('recurso', 'paciente:'.$episode->patient->code)
                        ->where('ocurrido_en', '>=', $episode->started_at->copy()->startOfSecond())
                        ->when($episode->ended_at, fn ($w, $end) => $w->where('ocurrido_en', '<=', $end->copy()->addSecond())));
            })
            ->orderBy('secuencia')->get();
        $names = CentralUser::query()->whereIn('user_id', $logs->pluck('usuario_id')->filter()->unique())->pluck('display_name', 'user_id');

        return $logs->map(fn (AuditLog $log) => [
            'accion' => $log->accion, 'usuario' => (string) ($names[$log->usuario_id] ?? 'Sistema'), 'detalle' => (array) $log->detalle,
            'ocurrido_en' => $log->ocurrido_en->toIso8601String(), 'solicitud' => $log->correlation_id,
        ])->values()->all();
    }

    private function ensureNoOpenAdmission(LocalPatient $patient): void
    {
        $open = CareEpisode::query()->where('local_patient_id', $patient->local_patient_id)->where('type', 'inpatient')->where('status', 'open')->first();
        if ($open !== null) {
            throw new DomainRuleException('PACIENTE_CON_ADMISION_ACTIVA', 'El paciente ya está internado. Para cambiarlo de cama use «Trasladar».', 409,
                ['admision_id' => $open->care_episode_id]);
        }
    }

    /** Las garantías de la base, traducidas a los códigos del contrato del módulo. */
    private function translate(QueryException $e): \Throwable
    {
        return match (true) {
            $e->getCode() === '23P01' => new DomainRuleException('CAMA_YA_ASIGNADA', 'Otra persona acaba de asignar esa cama. Elija otra.', 409),
            $e->getCode() === '55P03' => new DomainRuleException('CONFLICTO_CONCURRENCIA', 'Otra admisión está usando la cama en este momento. Intente de nuevo en unos segundos.', 409,
                ['reintentable' => true]),
            default => $e,
        };
    }
}
