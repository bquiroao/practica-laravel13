<?php

namespace App\Services\Beds;

use App\Exceptions\DomainRuleException;
use App\Models\Central\CentralUser;
use App\Models\Central\Hospital;
use App\Models\Hospital\Bed;
use App\Models\Hospital\BedStay;
use App\Models\Hospital\CareEpisode;
use App\Services\Audit\AuditTrail;
use App\Services\Hospital\UserRefSync;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Módulo traslados, altas y liberación de camas. Trabaja sobre la estancia abierta que crea la admisión:
 * el traslado cierra esa estancia y abre otra en una cama disponible (todo o nada); el alta cierra la estancia y el
 * episodio, y libera la cama.
 */
class BedMovementService
{
    public function __construct(private readonly UserRefSync $refs, private readonly AuditTrail $audit) {}

    public function transfer(CentralUser $actor, Hospital $hospital, string $episodeId, string $bedId, string $reason, bool $requireIcu, ?string $ip): BedStay
    {
        if (trim($reason) === '') {
            throw new DomainRuleException('MOTIVO_REQUERIDO', 'Escriba el motivo del traslado.');
        }

        try {
            return DB::connection('hospital')->transaction(function () use ($actor, $hospital, $episodeId, $bedId, $reason, $requireIcu, $ip): BedStay {
                [$episode, $stay] = $this->openStay($episodeId);
                $target = Bed::query()->with('ward')->lockForUpdate()->find($bedId);
                if ($target === null || $target->bed_id === $stay->bed_id) {
                    throw new DomainRuleException('CAMA_NO_DISPONIBLE', 'Elija una cama distinta a la actual.');
                }
                if ($target->status !== 'available' || $target->ward->status !== 'active' || $target->openStay()->exists()) {
                    throw new DomainRuleException('CAMA_NO_DISPONIBLE', "La cama {$target->ward->code}/{$target->code} no está disponible.", 409);
                }
                if ($requireIcu && $target->type !== 'uci') {
                    throw new DomainRuleException('CAMA_NO_CUMPLE_REQUISITO', 'El traslado pide una cama de UCI.', 409);
                }

                $now = now();
                $ref = $this->refs->forUser($actor, $hospital);
                $stay->forceFill(['ended_at' => $now])->save();
                Bed::query()->whereKey($stay->bed_id)->update(['status' => 'available', 'status_reason' => 'Traslado del paciente', 'status_changed_at' => $now, 'updated_at' => $now]);
                $new = BedStay::query()->create(['care_episode_id' => $episode->care_episode_id, 'bed_id' => $target->bed_id,
                    'assigned_by_user_ref_id' => $ref->local_user_ref_id, 'started_at' => $now, 'reason' => trim($reason)]);
                $target->forceFill(['status' => 'occupied', 'status_reason' => 'Traslado', 'status_changed_at' => $now])->save();

                $this->audit->record('traslados', 'TRASLADO', 'exito', $hospital->hospital_id, $actor->user_id, 'paciente:'.$episode->patient->code,
                    ['desde' => $stay->bed_id, 'hacia' => $target->bed_id, 'motivo' => trim($reason)], $ip);

                return $new;
            });
        } catch (QueryException $e) {
            // La exclusión de bed_stays: otra persona ocupó la cama en el mismo instante.
            if ($e->getCode() === '23P01') {
                throw new DomainRuleException('CAMA_NO_DISPONIBLE', 'Otra persona acaba de ocupar esa cama. Elija otra.', 409);
            }
            throw $e;
        }
    }

    public function discharge(CentralUser $actor, Hospital $hospital, string $episodeId, string $type, string $reason, ?string $ip): CareEpisode
    {
        if (! array_key_exists($type, CareEpisode::DISCHARGE_TYPES)) {
            throw new DomainRuleException('ALTA_INVALIDA', 'Elija el tipo de alta.');
        }
        if (trim($reason) === '') {
            throw new DomainRuleException('MOTIVO_REQUERIDO', 'Escriba el resumen o motivo del alta.');
        }

        return DB::connection('hospital')->transaction(function () use ($actor, $hospital, $episodeId, $type, $reason, $ip): CareEpisode {
            [$episode, $stay] = $this->openStay($episodeId);
            $now = now();
            $stay->forceFill(['ended_at' => $now])->save();
            // Liberación de la cama: queda en mantenimiento para la limpieza terminal, y se habilita desde el catálogo.
            Bed::query()->whereKey($stay->bed_id)->update(['status' => 'maintenance', 'status_reason' => 'Limpieza después del alta', 'status_changed_at' => $now, 'updated_at' => $now]);
            $episode->forceFill(['status' => 'closed', 'ended_at' => $now, 'discharge_type' => $type, 'discharge_reason' => trim($reason)])->save();
            if ($type === 'defuncion') {
                $episode->patient->forceFill(['status' => 'deceased', 'version' => $episode->patient->version + 1])->save();
            }
            $this->audit->record('traslados', 'ALTA', 'exito', $hospital->hospital_id, $actor->user_id, 'paciente:'.$episode->patient->code,
                ['tipo' => $type, 'cama' => $stay->bed_id], $ip);

            return $episode->fresh();
        });
    }

    /** @return array{0: CareEpisode, 1: BedStay} */
    private function openStay(string $episodeId): array
    {
        $episode = CareEpisode::query()->with('patient')->lockForUpdate()->find($episodeId);
        if ($episode === null || $episode->status !== 'open') {
            throw new DomainRuleException('EPISODIO_NO_DISPONIBLE', 'El internamiento no existe o ya está cerrado.', 409);
        }
        $stay = BedStay::query()->where('care_episode_id', $episodeId)->whereNull('ended_at')->lockForUpdate()->first()
            ?? throw new DomainRuleException('SIN_CAMA', 'El paciente no tiene una cama asignada.', 409);

        return [$episode, $stay];
    }
}
