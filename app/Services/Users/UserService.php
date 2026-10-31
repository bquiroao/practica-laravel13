<?php

namespace App\Services\Users;

use App\Exceptions\DomainRuleException;
use App\Models\Central\CentralUser;
use App\Models\Central\Hospital;
use App\Models\Central\HospitalMembership;
use App\Services\Access\PermissionResolver;
use App\Services\Access\RoleAssignmentService;
use App\Services\Audit\AuditTrail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/** Módulo usuarios/tenants: alta de usuarios con credencial local y su primer rol en el hospital. */
class UserService
{
    public function __construct(private readonly RoleAssignmentService $roles, private readonly AuditTrail $audit, private readonly PermissionResolver $permissions) {}

    public function create(CentralUser $admin, Hospital $hospital, string $name, string $email, string $password, string $roleCode, ?string $ip = null): CentralUser
    {
        $email = mb_strtolower(trim($email));
        if (CentralUser::query()->whereRaw('lower(email) = ?', [$email])->exists()) {
            throw new DomainRuleException('CORREO_DUPLICADO', 'Ya existe un usuario con ese correo.', 409);
        }

        return DB::connection('central')->transaction(function () use ($admin, $hospital, $name, $email, $password, $roleCode, $ip): CentralUser {
            $user = CentralUser::query()->create([
                'subject' => 'local|'.Str::uuid(), 'email' => $email, 'display_name' => trim($name), 'status' => 'active', 'metadata' => [],
            ]);
            $user->credential()->create(['password_hash' => Hash::make($password)]);
            $this->audit->record('usuarios', 'USUARIO_CREADO', 'exito', $hospital->hospital_id, $admin->user_id, 'usuario:'.$user->user_id, ['email' => $email], $ip);
            $this->roles->assign($admin, $hospital, $user->user_id, $roleCode, ip: $ip);

            return $user;
        });
    }

    public function setStatus(CentralUser $admin, Hospital $hospital, string $userId, string $status, ?string $ip = null): CentralUser
    {
        if ($admin->user_id === $userId) {
            throw new DomainRuleException('AUTODESACTIVACION', 'No puede cambiar el estado de su propia cuenta.');
        }
        $belongs = HospitalMembership::query()->where('user_id', $userId)->where('hospital_id', $hospital->hospital_id)->exists();
        $user = $belongs ? CentralUser::query()->find($userId) : null;
        if ($user === null) {
            throw new DomainRuleException('USUARIO_NO_ENCONTRADO', 'El usuario no pertenece a este hospital.', 404);
        }
        // La cuenta es de la red: si también trabaja en otro hospital, solo el administrador de red la desactiva.
        $elsewhere = HospitalMembership::query()->current()->where('user_id', $userId)->where('hospital_id', '!=', $hospital->hospital_id)->exists();
        if ($elsewhere && ! $this->permissions->allows($admin->user_id, $hospital->hospital_id, 'roles.configurar')) {
            throw new DomainRuleException('ESTADO_FUERA_DE_ALCANCE', 'El usuario también trabaja en otro hospital; solo un administrador de red puede cambiar su estado.', 403);
        }
        $user->forceFill(['status' => $status])->save();
        $this->audit->record('usuarios', $status === 'active' ? 'USUARIO_ACTIVADO' : 'USUARIO_DESACTIVADO', 'exito',
            $hospital->hospital_id, $admin->user_id, 'usuario:'.$userId, [], $ip);

        return $user;
    }
}
