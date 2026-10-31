<?php

namespace App\Services\Medications;

use App\Exceptions\DomainRuleException;
use App\Models\Central\CentralUser;
use App\Models\Central\Hospital;
use App\Models\Hospital\Medication;
use App\Services\Audit\AuditTrail;
use App\Services\Hospital\UserRefSync;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Módulo catálogo de medicamentos: es el único que escribe en `medications`. Prescripciones lo lee a través de
 * MedicationDirectory (DatabaseMedicationDirectory), así que un medicamento dado de baja deja de poder recetarse.
 *
 * Un cambio en la ficha nunca edita la versión vigente: crea la versión N+1 y cierra la anterior en esa fecha (RN-01).
 */
class MedicationCatalogService
{
    /** Campos de la ficha: lo que se compara para saber si una versión nueva cambia algo. */
    private const SHEET = ['name', 'active_ingredient', 'strength_value', 'strength_unit', 'dosage_form', 'route', 'presentation',
        'sanitary_registration', 'therapeutic_group', 'allergy_groups'];

    public function __construct(private readonly UserRefSync $refs, private readonly AuditTrail $audit) {}

    public static function today(?Hospital $hospital): string
    {
        return now($hospital?->timezone ?: config('app.timezone'))->toDateString();
    }

    /**
     * CU-02: versiones vigentes en la fecha, por texto contenido en el código, el nombre o el principio activo, y por
     * forma farmacéutica. Sin filtros, lista el catálogo vigente. Máximo 50, por nombre y código (RF-04).
     *
     * @return array{items: Collection<int, Medication>, total: int}
     */
    public function search(?string $query, ?string $form, string $date): array
    {
        $base = Medication::query()->validOn($date)
            ->when($query !== null && trim($query) !== '', fn ($q) => $q->whereRaw("search_text LIKE ? ESCAPE '\\'", [EquivalenceKey::likePattern($query)]))
            ->when($form, fn ($q, $f) => $q->where('dosage_form', $f));

        return ['items' => (clone $base)->orderBy('name')->orderBy('code')->limit(MedicationOptions::MAX_RESULTS)->get(), 'total' => $base->count()];
    }

    /** RF-09: la versión vigente de un código en la fecha. También acepta el código del catálogo provisional. */
    public function validOn(string $code, string $date): ?Medication
    {
        $code = mb_strtoupper(trim($code));

        return Medication::query()->validOn($date)->where(fn ($q) => $q->where('code', $code)->orWhere('legacy_code', $code))->first();
    }

    /**
     * CU-05: todas las versiones del código, de la más antigua a la más reciente.
     *
     * @return Collection<int, Medication>
     */
    public function history(string $code): Collection
    {
        $versions = Medication::query()->with(['creator', 'closer'])->where('code', mb_strtoupper(trim($code)))->orderBy('version')->get();

        return $versions->isEmpty() ? throw $this->notFound() : $versions;
    }

    /** RN-02: en cuántas recetas aparece el código (con su código provisional, si lo tiene). */
    public function usage(Medication $medication): int
    {
        return DB::connection('hospital')->table('prescriptions')->whereIn('medication_code', array_filter([$medication->code, $medication->legacy_code]))->count();
    }

    /**
     * CU-01: alta. El código MED-NNNNNN es el siguiente libre del hospital (RN-06) y la versión es la 1.
     *
     * @param  array<string, mixed>  $data  validado por MedicationRequest
     */
    public function register(CentralUser $actor, Hospital $hospital, array $data, ?string $ip): Medication
    {
        $from = $data['valid_from'] ?? self::today($hospital);
        $sheet = $this->sheet($data);
        $userRef = $this->refs->forUser($actor, $hospital)->local_user_ref_id;

        $medication = $this->guarded(fn () => DB::connection('hospital')->transaction(function () use ($sheet, $from, $userRef, $data) {
            // D-03: un candado por hospital mientras se calcula el código, así dos altas a la vez toman códigos distintos.
            DB::connection('hospital')->select("SELECT pg_advisory_xact_lock(hashtext('medications.code'))");
            $this->ensureNoEquivalent($sheet, $from, null);
            $next = (int) DB::connection('hospital')->scalar('SELECT coalesce(max(substr(code, 5)::int), 0) + 1 FROM medications');
            $code = 'MED-'.str_pad((string) $next, 6, '0', STR_PAD_LEFT);

            return Medication::query()->create($sheet + [
                'code' => $code, 'version' => 1, 'legacy_code' => $data['legacy_code'] ?? null, 'valid_from' => $from,
                'equivalence_key' => EquivalenceKey::of($sheet), 'search_text' => EquivalenceKey::searchText($code, $sheet['name'], $sheet['active_ingredient'], $data['legacy_code'] ?? null),
                'created_by_user_ref_id' => $userRef, 'created_at' => now(),
            ]);
        }));

        $this->audit->record('catalogo-medicamentos', 'MEDICAMENTO_REGISTRADO', 'exito', $hospital->hospital_id, $actor->user_id, 'medicamento:'.$medication->code,
            ['version' => 1, 'nombre' => $medication->name, 'vigente_desde' => $from], $ip);

        return $medication;
    }

    /**
     * CU-03: versión nueva con la ficha completa. Cierra la vigente en la fecha de inicio de la nueva (RN-01).
     *
     * @param  array<string, mixed>  $data  validado por MedicationRequest
     */
    public function newVersion(CentralUser $actor, Hospital $hospital, string $code, array $data, ?string $ip): Medication
    {
        $from = $data['valid_from'] ?? self::today($hospital);
        $sheet = $this->sheet($data);
        $userRef = $this->refs->forUser($actor, $hospital)->local_user_ref_id;

        [$previous, $medication] = $this->guarded(fn () => DB::connection('hospital')->transaction(function () use ($code, $data, $from, $sheet, $userRef) {
            $current = $this->openVersion($code, $data['version'] ?? null);
            if ($from <= $current->valid_from->toDateString()) {
                throw new DomainRuleException('SOLAPE_VIGENCIA', "La versión nueva debe empezar después del {$current->valid_from->toDateString()}, cuando empezó la versión {$current->version}.", 409,
                    ['vigente_desde' => $current->valid_from->toDateString()]);
            }
            if ($this->sheet($current->only(self::SHEET)) == $sheet) {
                throw new DomainRuleException('SIN_CAMBIOS', 'La ficha es igual a la versión vigente: cambie al menos un dato.', 422);
            }
            $this->ensureNoEquivalent($sheet, $from, $current->code);

            $current->forceFill(['valid_to' => $from, 'closed_by_user_ref_id' => $userRef])->save();
            $new = Medication::query()->create($sheet + [
                'code' => $current->code, 'version' => $current->version + 1, 'legacy_code' => $current->legacy_code, 'valid_from' => $from,
                'equivalence_key' => EquivalenceKey::of($sheet), 'search_text' => EquivalenceKey::searchText($current->code, $sheet['name'], $sheet['active_ingredient'], $current->legacy_code),
                'created_by_user_ref_id' => $userRef, 'created_at' => now(),
            ]);

            return [$current, $new];
        }));

        $this->audit->record('catalogo-medicamentos', 'MEDICAMENTO_NUEVA_VERSION', 'exito', $hospital->hospital_id, $actor->user_id, 'medicamento:'.$medication->code,
            ['version' => $medication->version, 'anterior_hasta' => $from, 'cambios' => array_keys(array_diff_assoc(
                array_map('json_encode', $sheet), array_map('json_encode', $this->sheet($previous->only(self::SHEET)))))], $ip);

        return $medication;
    }

    /** CU-04: baja. Cierra la versión vigente en la fecha indicada; el registro se conserva (RN-02). */
    public function retire(CentralUser $actor, Hospital $hospital, string $code, ?string $date, string $reason, ?int $expectedVersion, ?string $ip): Medication
    {
        $today = self::today($hospital);
        $date ??= $today;
        $userRef = $this->refs->forUser($actor, $hospital)->local_user_ref_id;

        $medication = $this->guarded(fn () => DB::connection('hospital')->transaction(function () use ($code, $date, $today, $reason, $expectedVersion, $userRef) {
            $current = $this->openVersion($code, $expectedVersion);
            if ($date < $today) {
                throw new DomainRuleException('FECHA_BAJA_INVALIDA', 'La baja no puede ser con fecha pasada: las recetas de esos días ya usaron el medicamento.', 422);
            }
            if ($date <= $current->valid_from->toDateString()) {
                throw new DomainRuleException('FECHA_BAJA_INVALIDA', "La baja debe ser después del {$current->valid_from->toDateString()}, cuando empezó la versión {$current->version}.", 422);
            }
            $current->forceFill(['valid_to' => $date, 'close_reason' => trim($reason), 'closed_by_user_ref_id' => $userRef])->save();

            return $current;
        }));

        $this->audit->record('catalogo-medicamentos', 'MEDICAMENTO_DADO_DE_BAJA', 'exito', $hospital->hospital_id, $actor->user_id, 'medicamento:'.$medication->code,
            ['version' => $medication->version, 'vigente_hasta' => $date, 'motivo' => trim($reason)], $ip);

        return $medication;
    }

    /** CU-06: borrar un registro erróneo, solo si nunca se usó en una receta (RN-02). Se borran todas sus versiones. */
    public function delete(CentralUser $actor, Hospital $hospital, string $code, ?string $ip): void
    {
        $versions = $this->history($code);
        $uses = $this->usage($versions->last());
        if ($uses > 0) {
            $this->audit->record('catalogo-medicamentos', 'MEDICAMENTO_ELIMINACION_RECHAZADA', 'denegado', $hospital->hospital_id, $actor->user_id,
                'medicamento:'.$versions->last()->code, ['recetas' => $uses], $ip);
            throw $this->inUse($uses);
        }
        $this->guarded(fn () => Medication::query()->where('code', $versions->last()->code)->delete());

        $this->audit->record('catalogo-medicamentos', 'MEDICAMENTO_ELIMINADO', 'exito', $hospital->hospital_id, $actor->user_id, 'medicamento:'.$versions->last()->code,
            ['versiones' => $versions->count(), 'nombre' => $versions->last()->name], $ip);
    }

    /** La versión abierta (sin fecha de cierre) del código, bloqueada hasta el final de la transacción. */
    private function openVersion(string $code, ?int $expectedVersion): Medication
    {
        $latest = Medication::query()->where('code', mb_strtoupper(trim($code)))->orderByDesc('version')->lockForUpdate()->first() ?? throw $this->notFound();
        if ($expectedVersion !== null && $expectedVersion !== $latest->version) {
            throw new DomainRuleException('CONFLICTO_CONCURRENTE', "Otra persona cambió {$latest->code} mientras usted lo editaba (ahora va en la versión {$latest->version}). Vuelva a cargarlo.", 409,
                ['version_actual' => $latest->version]);
        }
        if ($latest->valid_to !== null) {
            throw new DomainRuleException('YA_DADO_DE_BAJA', "{$latest->code} se dio de baja desde el {$latest->valid_to->toDateString()}. Para volver a usarlo, regístrelo como alta.", 409);
        }

        return $latest;
    }

    /** RN-04: ningún otro código con la misma clave puede estar vigente en algún día desde $from. */
    private function ensureNoEquivalent(array $sheet, string $from, ?string $exceptCode): void
    {
        $other = Medication::query()->where('equivalence_key', EquivalenceKey::of($sheet))
            ->when($exceptCode, fn ($q, $c) => $q->where('code', '<>', $c))
            ->where(fn ($q) => $q->whereNull('valid_to')->orWhere('valid_to', '>', $from))->orderBy('valid_from')->first();
        if ($other) {
            throw $this->duplicated($other->code);
        }
    }

    /**
     * La ficha normalizada como se guarda: textos sin espacios extremos, registro sanitario en mayúsculas y, si no se
     * indicaron, las familias de alergia que se reconocen en el principio activo.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function sheet(array $data): array
    {
        $text = fn (?string $v) => ($v = preg_replace('/\s+/u', ' ', trim((string) $v))) === '' ? null : $v;
        $groups = $data['allergy_groups'] ?? EquivalenceKey::suggestAllergyGroups((string) $data['active_ingredient']);
        sort($groups);

        return [
            'name' => $text($data['name']), 'active_ingredient' => $text($data['active_ingredient']),
            'strength_value' => EquivalenceKey::strength($data['strength_value']), 'strength_unit' => $data['strength_unit'],
            'dosage_form' => $data['dosage_form'], 'route' => $data['route'], 'presentation' => $text($data['presentation']),
            'sanitary_registration' => ($r = $text($data['sanitary_registration'] ?? null)) !== null ? mb_strtoupper($r) : null,
            'therapeutic_group' => $data['therapeutic_group'], 'allergy_groups' => array_values(array_unique($groups)),
        ];
    }

    /**
     * La base repite RN-01, RN-02 y RN-04 (RNF-04). Si dos personas cambian lo mismo a la vez y la base lo rechaza,
     * la respuesta es el mismo 409 que da el dominio, no un 500.
     *
     * @template T
     *
     * @param  callable(): T  $work
     * @return T
     */
    private function guarded(callable $work): mixed
    {
        try {
            return $work();
        } catch (QueryException $e) {
            $message = $e->getMessage();
            throw match (true) {
                str_contains($message, 'medications_equivalent_overlap') => $this->duplicated(null),
                str_contains($message, 'medications_version_overlap') => new DomainRuleException('SOLAPE_VIGENCIA', 'Las vigencias de dos versiones se cruzan.', 409),
                str_contains($message, 'medications_code_version_unique') => new DomainRuleException('CONFLICTO_CONCURRENTE', 'Otra persona cambió este medicamento a la vez. Vuelva a cargarlo.', 409),
                str_contains($message, 'medications_in_use') => $this->inUse(null),
                in_array($e->getCode(), ['23505', '23P01', '23514', '23503'], true) => new DomainRuleException('INTEGRIDAD', 'La base rechazó el cambio por una regla del catálogo.', 409),
                default => $e,
            };
        }
    }

    private function duplicated(?string $code): DomainRuleException
    {
        return new DomainRuleException('DUPLICADO', $code
            ? "Ya existe {$code} con el mismo principio activo, concentración, forma farmacéutica y vía, vigente en esas fechas."
            : 'Ya existe un medicamento equivalente vigente en esas fechas.', 409, array_filter(['codigo_existente' => $code]));
    }

    private function inUse(?int $uses): DomainRuleException
    {
        return new DomainRuleException('EN_USO', 'Este medicamento ya se usó en recetas: no se puede eliminar. Dele de baja para que deje de recetarse.', 409,
            array_filter(['recetas' => $uses, 'accion_sugerida' => 'baja']));
    }

    private function notFound(): DomainRuleException
    {
        return new DomainRuleException('MEDICAMENTO_NO_ENCONTRADO', 'El medicamento no existe en el catálogo de este hospital.', 404);
    }
}
