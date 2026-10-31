<?php

namespace App\Services\Patients;

use App\Exceptions\DomainRuleException;
use App\Models\Central\CentralUser;
use App\Models\Central\Hospital;
use App\Models\Hospital\LocalPatient;
use App\Services\Audit\AuditTrail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Módulo pacientes: registro con control de duplicados (R-02 documento, R-03 nombre + nacimiento),
 * edición con motivo para la identidad (R-07) y control de versión, y búsqueda por código, documento o nombre (R-10).
 */
class PatientService
{
    public const SEARCH_LIMIT = 20;

    private const IDENTITY = ['given_name', 'family_name', 'birth_date', 'sex', 'document_type', 'document_number', 'document_country', 'status'];

    public function __construct(private readonly AuditTrail $audit) {}

    /** @param array<string, mixed> $data */
    public function register(CentralUser $actor, Hospital $hospital, array $data, bool $confirmDuplicate, string $motivo, ?string $ip): LocalPatient
    {
        $data = $this->normalize($data);

        if ($data['document_number'] !== null && ($holder = $this->documentHolder($data)) !== null) {
            throw new DomainRuleException('PACIENTE_EXISTENTE', "Ya hay un paciente registrado con ese documento: {$holder->code}.", 409,
                ['codigo' => $holder->code, 'id' => $holder->local_patient_id]);
        }

        $similar = $this->similar($data['search_name'], $data['birth_date']);
        if ($similar->isNotEmpty()) {
            if (! $confirmDuplicate) {
                throw new DomainRuleException('POSIBLE_DUPLICADO', 'Hay pacientes con el mismo nombre y fecha de nacimiento. Revise si es la misma persona.', 409,
                    ['candidatos' => $similar->map(fn (LocalPatient $p) => $this->summary($p))->all()]);
            }
            if (mb_strlen(trim($motivo)) < 10) {
                throw new DomainRuleException('MOTIVO_REQUERIDO', 'Explique en al menos 10 caracteres por qué es otra persona.');
            }
        }

        return DB::connection('hospital')->transaction(function () use ($actor, $hospital, $data, $similar, $motivo, $ip): LocalPatient {
            $sequence = (int) DB::connection('hospital')->scalar("SELECT nextval('local_patient_code_seq')");
            $patient = LocalPatient::query()->create($data + [
                'global_id' => (string) Str::uuid(),
                'code' => $hospital->code.'-'.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT),
                'status' => 'active',
                'mpi_link_status' => 'pending',
                'version' => 1,
            ]);
            $this->audit->record('pacientes', 'PACIENTE_REGISTRADO', 'exito', $hospital->hospital_id, $actor->user_id, 'paciente:'.$patient->code,
                $similar->isEmpty() ? [] : ['duplicado_descartado' => $similar->pluck('code')->all(), 'motivo' => trim($motivo)], $ip);

            return $patient;
        });
    }

    /** @param array<string, mixed> $data */
    public function update(CentralUser $actor, Hospital $hospital, LocalPatient $patient, array $data, int $version, string $motivo, ?string $ip): LocalPatient
    {
        if ($patient->version !== $version) {
            throw new DomainRuleException('CONFLICTO_VERSION', 'Otra persona modificó esta ficha mientras usted la editaba. Recargue para ver los cambios.', 409);
        }
        $data = $this->normalize($data + ['status' => $patient->status]);
        $changed = array_keys(array_filter($data, fn ($v, $k) => $this->differs($patient, $k, $v), ARRAY_FILTER_USE_BOTH));
        if ($changed === []) {
            return $patient;
        }
        $identity = array_values(array_intersect($changed, self::IDENTITY));
        if ($identity !== [] && mb_strlen(trim($motivo)) < 10) {
            throw new DomainRuleException('MOTIVO_REQUERIDO', 'Cambiar nombre, nacimiento, sexo, documento o estado exige un motivo de al menos 10 caracteres.', 422,
                ['campos' => $identity]);
        }
        if ($data['document_number'] !== null && ($holder = $this->documentHolder($data)) !== null && $holder->local_patient_id !== $patient->local_patient_id) {
            // R-02 al editar: no se revela de quién es el documento.
            throw new DomainRuleException('DOCUMENTO_EN_USO', 'Ese documento ya pertenece a otro paciente.', 409);
        }

        return DB::connection('hospital')->transaction(function () use ($actor, $hospital, $patient, $data, $version, $changed, $identity, $motivo, $ip): LocalPatient {
            $updated = LocalPatient::query()->whereKey($patient->getKey())->where('version', $version)
                ->update(array_intersect_key($data, array_flip($changed)) + ['version' => $version + 1, 'updated_at' => now()]);
            if ($updated === 0) {
                throw new DomainRuleException('CONFLICTO_VERSION', 'Otra persona modificó esta ficha mientras usted la editaba. Recargue para ver los cambios.', 409);
            }
            $this->audit->record('pacientes', 'PACIENTE_EDITADO', 'exito', $hospital->hospital_id, $actor->user_id, 'paciente:'.$patient->code,
                ['campos' => $changed] + ($identity !== [] ? ['motivo' => trim($motivo)] : []), $ip);

            return $patient->fresh();
        });
    }

    /**
     * R-10: por código o documento exactos, o por nombre (3 letras o más, todas las palabras). Sin criterio,
     * los últimos pacientes atendidos o registrados, para retomar el trabajo.
     *
     * @return Collection<int, LocalPatient>
     */
    public function search(string $query): Collection
    {
        $query = trim($query);
        $base = LocalPatient::query()->orderBy('family_name')->orderBy('given_name')->limit(self::SEARCH_LIMIT);

        if ($query === '') {
            return LocalPatient::query()->orderByDesc('updated_at')->limit(self::SEARCH_LIMIT)->get();
        }
        if (preg_match('/^[A-Z]{2,}-[A-Z]{2,}-\d+$/i', $query)) {
            return $base->where('code', Str::upper($query))->get();
        }
        $digits = preg_replace('/[\s-]/', '', $query);
        if (preg_match('/^\d{13}$/', (string) $digits) || preg_match('/^[A-Z0-9]{6,20}$/', Str::upper((string) $digits)) && preg_match('/\d/', (string) $digits)) {
            return $base->where('document_number', Str::upper((string) $digits))->get();
        }
        $normalized = LocalPatient::normalize($query);
        if (preg_match_all('/\p{L}/u', $normalized) < 3) {
            throw new DomainRuleException('CRITERIO_INSUFICIENTE', 'Escriba al menos 3 letras del nombre, o el código o el documento completo.');
        }

        return $base->where(function (Builder $q) use ($normalized): void {
            foreach (explode(' ', $normalized) as $word) {
                $q->where('search_name', 'like', '%'.addcslashes($word, '%_\\').'%');
            }
        })->get();
    }

    /** @return array<string, mixed> */
    public function summary(LocalPatient $p): array
    {
        return ['id' => $p->local_patient_id, 'codigo' => $p->code, 'nombre' => $p->fullName(), 'nacimiento' => $p->birth_date->toDateString(),
            'documento' => $p->maskedDocument()];
    }

    /**
     * Normaliza la captura y valida el documento: DPI de 13 dígitos con departamento 01–22, o pasaporte de 6 a 20
     * letras o dígitos con país de 3 letras (módulo pacientes, Dpi y Pasaporte).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalize(array $data): array
    {
        $type = $data['document_type'] ?? null;
        $number = isset($data['document_number']) ? Str::upper((string) preg_replace('/[\s-]/', '', (string) $data['document_number'])) : null;
        $country = isset($data['document_country']) ? Str::upper(trim((string) $data['document_country'])) : null;
        if ($number === '' || $type === null || $type === '') {
            [$type, $number, $country] = [null, null, null];
        } elseif ($type === 'DPI') {
            $department = (int) substr($number, 9, 2);
            if (! preg_match('/^\d{13}$/', $number) || $department < 1 || $department > 22) {
                throw new DomainRuleException('DOCUMENTO_INVALIDO', 'El DPI debe tener 13 dígitos y un departamento válido (01 a 22 en los dígitos 10 y 11).', 422,
                    ['campo' => 'document_number']);
            }
            $country = null;
        } else {
            if (! preg_match('/^[A-Z0-9]{6,20}$/', $number) || ! preg_match('/^[A-Z]{3}$/', (string) $country)) {
                throw new DomainRuleException('DOCUMENTO_INVALIDO', 'El pasaporte debe tener de 6 a 20 letras o dígitos y el país un código de 3 letras (p. ej. GTM, MEX, USA).', 422,
                    ['campo' => 'document_number']);
            }
        }

        $given = trim((string) preg_replace('/\s+/', ' ', (string) $data['given_name']));
        $family = trim((string) preg_replace('/\s+/', ' ', (string) $data['family_name']));
        $blankToNull = fn ($v) => ($v = trim((string) $v)) === '' ? null : $v;

        return [
            'given_name' => $given,
            'family_name' => $family,
            'birth_date' => (string) $data['birth_date'],
            'sex' => (string) $data['sex'],
            'contact' => $blankToNull($data['contact'] ?? null),
            'address' => $blankToNull($data['address'] ?? null),
            'emergency_contact_name' => $blankToNull($data['emergency_contact_name'] ?? null),
            'emergency_contact_phone' => $blankToNull($data['emergency_contact_phone'] ?? null),
            'document_type' => $type,
            'document_number' => $number,
            'document_country' => $country,
            'search_name' => LocalPatient::normalize($given.' '.$family),
        ] + (isset($data['status']) ? ['status' => (string) $data['status']] : []);
    }

    /** @param array<string, mixed> $data */
    private function documentHolder(array $data): ?LocalPatient
    {
        return LocalPatient::query()->where('document_type', $data['document_type'])->where('document_number', $data['document_number'])
            ->whereRaw("COALESCE(document_country, '') = ?", [(string) $data['document_country']])->first();
    }

    /** @return Collection<int, LocalPatient> */
    private function similar(string $searchName, string $birthDate): Collection
    {
        return LocalPatient::query()->where('search_name', $searchName)->whereDate('birth_date', $birthDate)->limit(5)->get();
    }

    private function differs(LocalPatient $patient, string $key, mixed $value): bool
    {
        $current = $key === 'birth_date' ? $patient->birth_date?->toDateString() : $patient->getAttribute($key);

        return (string) $current !== (string) $value;
    }
}
