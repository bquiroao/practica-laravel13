<?php

namespace App\Services\Beds;

use App\Exceptions\DomainRuleException;
use App\Models\Central\CentralUser;
use App\Models\Central\Hospital;
use App\Models\Hospital\Bed;
use App\Models\Hospital\Ward;
use App\Services\Audit\AuditTrail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Módulo catálogo de salas y camas: E2 datos obligatorios, E3 duplicados, E4 sala inactiva, E5 cama ocupada
 * (la gestiona admisión), E6 transición no permitida, E7 otro usuario cambió la cama, E9 sala llena.
 */
class BedCatalogService
{
    public function __construct(private readonly AuditTrail $audit) {}

    public function createWard(CentralUser $actor, Hospital $hospital, string $code, string $name, string $location, ?int $capacity, ?string $ip): Ward
    {
        $code = Str::upper(trim($code));
        if (Ward::query()->where('code', $code)->exists()) {
            throw new DomainRuleException('SALA_EXISTENTE', "Ya existe una sala con el código {$code}.", 409);
        }
        $ward = Ward::query()->create(['code' => $code, 'name' => trim($name), 'location' => trim($location), 'capacity' => $capacity, 'status' => 'active']);
        $this->audit->record('camas', 'SALA_CREADA', 'exito', $hospital->hospital_id, $actor->user_id, 'sala:'.$code, ['capacidad' => $capacity], $ip);

        return $ward;
    }

    public function createBed(CentralUser $actor, Hospital $hospital, string $wardId, string $code, string $type, ?string $ip): Bed
    {
        $code = Str::upper(trim($code));

        return DB::connection('hospital')->transaction(function () use ($actor, $hospital, $wardId, $code, $type, $ip): Bed {
            // Bloquea la sala: dos altas simultáneas no pueden pasarse de la capacidad (E9).
            $ward = Ward::query()->lockForUpdate()->find($wardId);
            if ($ward === null || $ward->status !== 'active') {
                throw new DomainRuleException('SALA_NO_DISPONIBLE', 'La sala no existe o está inactiva.');
            }
            if ($ward->beds()->where('code', $code)->exists()) {
                throw new DomainRuleException('CAMA_EXISTENTE', "La sala {$ward->code} ya tiene una cama {$code}.", 409);
            }
            $inUse = $ward->beds()->where('status', '<>', 'inactive')->count();
            if ($ward->capacity !== null && $inUse >= $ward->capacity) {
                throw new DomainRuleException('SALA_LLENA', "La sala {$ward->name} llegó a su capacidad de {$ward->capacity} camas.", 409);
            }
            $bed = Bed::query()->create(['ward_id' => $ward->ward_id, 'code' => $code, 'type' => $type, 'status' => 'available']);
            $this->audit->record('camas', 'CAMA_CREADA', 'exito', $hospital->hospital_id, $actor->user_id, 'cama:'.$ward->code.'/'.$code, ['tipo' => $type], $ip);

            return $bed;
        });
    }

    public function changeStatus(CentralUser $actor, Hospital $hospital, Bed $bed, string $to, string $reason, ?string $ip): Bed
    {
        if (trim($reason) === '') {
            throw new DomainRuleException('MOTIVO_REQUERIDO', 'Escriba el motivo del cambio (por ejemplo «Limpieza terminal» o «Reparación de cama»).');
        }
        $from = $bed->status;
        if ($from === 'occupied' || $bed->openStay()->exists()) {
            throw new DomainRuleException('CAMA_OCUPADA', 'La cama está ocupada: se libera con un traslado o un alta.', 409);
        }
        if (! in_array($to, Bed::TRANSITIONS[$from] ?? [], true)) {
            throw new DomainRuleException('TRANSICION_INVALIDA', 'Una cama «'.self::label($from).'» no puede pasar a «'.self::label($to).'».');
        }
        $updated = Bed::query()->whereKey($bed->getKey())->where('status', $from)
            ->update(['status' => $to, 'status_reason' => trim($reason), 'status_changed_at' => now(), 'updated_at' => now()]);
        if ($updated === 0) {
            throw new DomainRuleException('CAMA_CAMBIO', 'Otra persona cambió esta cama. Recargue y vuelva a intentar.', 409);
        }
        $this->audit->record('camas', 'CAMA_ESTADO', 'exito', $hospital->hospital_id, $actor->user_id, 'cama:'.$bed->bed_id,
            ['de' => $from, 'a' => $to, 'motivo' => trim($reason)], $ip);

        return $bed->fresh();
    }

    public static function label(string $status): string
    {
        return ['available' => 'Disponible', 'occupied' => 'Ocupada', 'maintenance' => 'En mantenimiento', 'inactive' => 'Inactiva'][$status] ?? $status;
    }
}
