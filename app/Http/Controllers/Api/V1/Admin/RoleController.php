<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Exceptions\DomainRuleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateRolePermissionsRequest;
use App\Models\Central\Permission;
use App\Models\Central\Role;
use App\Services\Access\PermissionResolver;
use App\Services\Audit\AuditTrail;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class RoleController extends Controller
{
    /** Matriz rol × permiso; cada permiso indica su módulo. */
    public function index(): JsonResponse
    {
        return response()->json([
            'roles' => Role::query()->with('permissions:permission_code')->orderBy('name')->get()->map(fn (Role $r) => [
                'code' => $r->role_code, 'name' => $r->name, 'scope' => $r->scope,
                'permissions' => $r->permissions->pluck('permission_code')->sort()->values(),
            ]),
            'permissions' => Permission::query()->orderBy('module')->orderBy('permission_code')->get()->map(fn (Permission $p) => [
                'code' => $p->permission_code, 'module' => $p->module, 'description' => $p->description,
            ]),
        ]);
    }

    public function updatePermissions(UpdateRolePermissionsRequest $request, string $role, AuditTrail $audit, PermissionResolver $resolver): JsonResponse
    {
        $model = Role::query()->find($role) ?? throw new DomainRuleException('ROL_INVALIDO', 'El rol no existe en el catálogo.', 404);
        $wanted = collect($request->input('permissions'))->sort()->values();
        $unknown = $wanted->diff(Permission::query()->pluck('permission_code'));
        if ($unknown->isNotEmpty()) {
            throw new DomainRuleException('PERMISO_INVALIDO', 'Permisos inexistentes: '.$unknown->implode(', '));
        }
        $before = $model->permissions()->pluck('permissions.permission_code')->sort()->values();

        DB::connection('central')->transaction(function () use ($model, $wanted, $before, $request, $audit): void {
            $model->permissions()->sync($wanted->all());
            $audit->record('rbac', 'PERMISOS_DE_ROL_CAMBIADOS', 'exito', $request->attributes->get('hospital')->hospital_id, $request->user()->user_id,
                'rol:'.$model->role_code, ['agregados' => $wanted->diff($before)->values()->all(), 'quitados' => $before->diff($wanted)->values()->all()], $request->ip());
        });
        $resolver->forget();

        return response()->json(['code' => $model->role_code, 'permissions' => $wanted]);
    }
}
