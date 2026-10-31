<?php

namespace App\Services\Doctors;

use App\Exceptions\DomainRuleException;
use App\Models\Central\CentralUser;
use App\Models\Central\Hospital;
use App\Models\Central\HospitalMembership;
use App\Models\Hospital\DoctorProfile;
use App\Models\Hospital\DoctorSchedule;
use App\Models\Hospital\DoctorSpecialty;
use App\Models\Hospital\Specialty;
use App\Services\Audit\AuditTrail;
use App\Services\Hospital\UserRefSync;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Módulo médicos-especialidades: perfil con colegiado, especialidades con vigencia y horario semanal. */
class DoctorService
{
    public function __construct(private readonly UserRefSync $refs, private readonly AuditTrail $audit) {}

    public function create(CentralUser $actor, Hospital $hospital, string $userId, string $license, string $specialtyId, ?string $ip): DoctorProfile
    {
        $license = Str::upper(trim($license));
        $user = CentralUser::query()->find($userId);
        $isDoctor = $user !== null && $user->isActive() && HospitalMembership::query()->current()
            ->where('user_id', $userId)->where('hospital_id', $hospital->hospital_id)->where('role_code', 'medico')->exists();
        if (! $isDoctor) {
            throw new DomainRuleException('USUARIO_NO_ES_MEDICO', 'La persona debe tener el rol «Médico» vigente en este hospital. Asígnelo primero en Usuarios.');
        }
        if (DoctorProfile::query()->where('license_number', $license)->exists()) {
            throw new DomainRuleException('COLEGIADO_DUPLICADO', 'Ese número de colegiado ya está registrado.', 409);
        }
        $specialty = Specialty::query()->active()->find($specialtyId) ?? throw new DomainRuleException('ESPECIALIDAD_INVALIDA', 'La especialidad no existe o no está activa.');

        return DB::connection('hospital')->transaction(function () use ($actor, $hospital, $user, $license, $specialty, $ip): DoctorProfile {
            $ref = $this->refs->forUser($user, $hospital, 'medico');
            if (DoctorProfile::query()->active()->where('local_user_ref_id', $ref->local_user_ref_id)->exists()) {
                throw new DomainRuleException('MEDICO_DUPLICADO', 'Esa persona ya tiene un perfil de médico activo.', 409);
            }
            $profile = DoctorProfile::query()->create([
                'local_user_ref_id' => $ref->local_user_ref_id, 'license_number' => $license, 'status' => 'active', 'valid_from' => now()->toDateString(),
            ]);
            $profile->specialties()->create(['specialty_id' => $specialty->specialty_id, 'is_primary' => true, 'valid_from' => now()->toDateString(), 'status' => 'active']);
            $this->audit->record('medicos', 'MEDICO_REGISTRADO', 'exito', $hospital->hospital_id, $actor->user_id, 'medico:'.$profile->doctor_profile_id,
                ['colegiado' => $license, 'especialidad' => $specialty->code], $ip);

            return $profile;
        });
    }

    public function addSpecialty(CentralUser $actor, Hospital $hospital, DoctorProfile $profile, string $specialtyId, bool $primary, ?string $ip): DoctorSpecialty
    {
        $specialty = Specialty::query()->active()->find($specialtyId) ?? throw new DomainRuleException('ESPECIALIDAD_INVALIDA', 'La especialidad no existe o no está activa.');
        try {
            $link = DB::connection('hospital')->transaction(fn () => $profile->specialties()->create([
                'specialty_id' => $specialty->specialty_id, 'is_primary' => $primary, 'valid_from' => now()->toDateString(), 'status' => 'active',
            ]));
        } catch (QueryException $e) {
            // Las exclusiones de la base: la misma especialidad, o dos principales, en fechas que se cruzan.
            if ($e->getCode() === '23P01') {
                throw new DomainRuleException('ESPECIALIDAD_DUPLICADA', $primary
                    ? 'Ya tiene una especialidad principal vigente. Quite la actual antes de marcar otra como principal.'
                    : 'Ya tiene esa especialidad vigente.', 409);
            }
            throw $e;
        }
        $this->audit->record('medicos', 'ESPECIALIDAD_AGREGADA', 'exito', $hospital->hospital_id, $actor->user_id, 'medico:'.$profile->doctor_profile_id,
            ['especialidad' => $specialty->code, 'principal' => $primary], $ip);

        return $link;
    }

    /** Cerrar una especialidad no borra la historia: termina su vigencia hoy (o la desactiva si empezó hoy). */
    public function endSpecialty(CentralUser $actor, Hospital $hospital, DoctorSpecialty $link, ?string $ip): void
    {
        $today = now()->toDateString();
        $link->forceFill($link->valid_from->toDateString() < $today ? ['valid_to' => $today] : ['status' => 'inactive'])->save();
        $this->audit->record('medicos', 'ESPECIALIDAD_RETIRADA', 'exito', $hospital->hospital_id, $actor->user_id, 'medico:'.$link->doctor_profile_id,
            ['especialidad_id' => $link->specialty_id], $ip);
    }

    /**
     * Reemplaza el horario semanal completo. Bloques en horas y medias horas, sin cruces el mismo día.
     *
     * @param  list<array{weekday: int, starts_at: string, ends_at: string}>  $blocks
     */
    public function setSchedule(CentralUser $actor, Hospital $hospital, DoctorProfile $profile, array $blocks, ?string $ip): void
    {
        foreach ($blocks as $i => $b) {
            if (! preg_match('/^([01]\d|2[0-3]):(00|30)$/', $b['starts_at']) || ! preg_match('/^([01]\d|2[0-3]):(00|30)$/', $b['ends_at'])) {
                throw new DomainRuleException('HORARIO_INVALIDO', 'Las horas van en bloques de 30 minutos (por ejemplo 08:00 o 08:30).', 422, ['bloque' => $i]);
            }
            if ($b['ends_at'] <= $b['starts_at']) {
                throw new DomainRuleException('HORARIO_INVALIDO', 'La hora de salida debe ser posterior a la de entrada.', 422, ['bloque' => $i]);
            }
        }
        try {
            DB::connection('hospital')->transaction(function () use ($profile, $blocks): void {
                DoctorSchedule::query()->where('doctor_profile_id', $profile->doctor_profile_id)->delete();
                foreach ($blocks as $b) {
                    $profile->schedules()->create(['weekday' => $b['weekday'], 'starts_at' => $b['starts_at'], 'ends_at' => $b['ends_at']]);
                }
            });
        } catch (QueryException $e) {
            if ($e->getCode() === '23P01') {
                throw new DomainRuleException('HORARIO_SUPERPUESTO', 'Dos bloques del mismo día se cruzan. Únalos o ajuste las horas.');
            }
            throw $e;
        }
        $this->audit->record('medicos', 'HORARIO_ACTUALIZADO', 'exito', $hospital->hospital_id, $actor->user_id, 'medico:'.$profile->doctor_profile_id,
            ['bloques' => count($blocks)], $ip);
    }

    public function setStatus(CentralUser $actor, Hospital $hospital, DoctorProfile $profile, string $status, ?string $ip): DoctorProfile
    {
        $profile->forceFill(['status' => $status])->save();
        $this->audit->record('medicos', 'MEDICO_ESTADO', 'exito', $hospital->hospital_id, $actor->user_id, 'medico:'.$profile->doctor_profile_id, ['estado' => $status], $ip);

        return $profile;
    }
}
