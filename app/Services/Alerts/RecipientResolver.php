<?php

namespace App\Services\Alerts;

use App\Models\Central\CentralUser;
use App\Models\Central\Hospital;
use App\Models\Central\HospitalMembership;
use App\Models\Hospital\CriticalAlert;
use App\Models\Hospital\LocalUserRef;
use App\Services\Hospital\UserRefSync;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * CU-02: quién recibe la alerta en cada nivel, solo personal con rol vigente en el hospital de la alerta (RNF-01).
 *  - nivel 0: enfermería y el médico tratante (el que pidió el laboratorio o el asignado al episodio);
 *  - nivel 1: los médicos del hospital (no existe el rol «jefe de servicio»);
 *  - nivel 2: dirección médica.
 * Quien ya recibió la alerta en un nivel anterior no se vuelve a contar (RN-03 decide si el nivel quedó vacío).
 */
class RecipientResolver
{
    public const ROLES = [0 => ['enfermeria'], 1 => ['medico'], 2 => ['direccion']];

    public function __construct(private readonly UserRefSync $refs) {}

    /** @return Collection<int, LocalUserRef> */
    public function forLevel(CriticalAlert $alert, int $level, Hospital $hospital): Collection
    {
        $userIds = HospitalMembership::query()->current()->where('hospital_id', $hospital->hospital_id)
            ->whereIn('role_code', self::ROLES[$level])->pluck('user_id')->unique();
        $refs = CentralUser::query()->whereIn('user_id', $userIds)->where('status', 'active')->orderBy('email')->get()
            ->map(fn (CentralUser $u) => $this->refs->forUser($u, $hospital, self::ROLES[$level][0]));
        if ($level === 0) {
            $refs = $refs->merge(LocalUserRef::query()->whereIn('local_user_ref_id', $this->attendingRefs($alert))->get());
        }
        $already = $alert->notifications()->pluck('recipient_user_ref_id')->all();

        return $refs->unique('local_user_ref_id')->reject(fn (LocalUserRef $r) => in_array($r->local_user_ref_id, $already, true))->values();
    }

    /** @return list<string> */
    private function attendingRefs(CriticalAlert $alert): array
    {
        if ($alert->source === 'laboratorio') {
            return ! Str::isUuid($alert->source_id) ? [] : DB::connection('hospital')->table('lab_results as r')->join('lab_order_items as i', 'i.lab_order_item_id', '=', 'r.lab_order_item_id')
                ->join('lab_orders as o', 'o.lab_order_id', '=', 'i.lab_order_id')->where('r.lab_result_id', $alert->source_id)
                ->pluck('o.ordered_by_user_ref_id')->all();
        }

        return $alert->care_episode_id === null ? [] : DB::connection('hospital')->table('care_assignments as a')
            ->join('doctor_profiles as d', 'd.doctor_profile_id', '=', 'a.doctor_profile_id')
            ->where('a.care_episode_id', $alert->care_episode_id)->where('a.status', 'active')->whereNull('a.valid_to')
            ->pluck('d.local_user_ref_id')->all();
    }
}
