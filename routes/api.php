<?php

use App\Http\Controllers\Api\V1\Admin\AuditController;
use App\Http\Controllers\Api\V1\Admin\MembershipController;
use App\Http\Controllers\Api\V1\Admin\RoleController;
use App\Http\Controllers\Api\V1\Admin\UserController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\HospitalController;
use Illuminate\Support\Facades\Route;

Route::get('/hospitales', [HospitalController::class, 'index']);

Route::middleware('tenant')->group(function (): void {
    Route::post('/auth/login', [AuthController::class, 'login']);

    Route::middleware('sesion')->group(function (): void {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::get('/auth/hospitales', [AuthController::class, 'hospitals']);
        Route::post('/auth/cambiar-hospital', [AuthController::class, 'switchHospital']);
        Route::post('/auth/refresh', [AuthController::class, 'refresh']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        // Plataforma: usuarios/tenants, RBAC y gobernanza-auditoría.
        Route::get('/usuarios', [UserController::class, 'index'])->middleware('permiso:usuarios.ver');
        Route::post('/usuarios', [UserController::class, 'store'])->middleware('permiso:usuarios.gestionar');
        Route::patch('/usuarios/{user}/estado', [UserController::class, 'updateStatus'])->middleware('permiso:usuarios.gestionar')->whereUuid('user');

        Route::get('/roles', [RoleController::class, 'index'])->middleware('permiso:roles.ver');
        Route::put('/roles/{role}/permisos', [RoleController::class, 'updatePermissions'])->middleware('permiso:roles.configurar');
        Route::post('/membresias', [MembershipController::class, 'store'])->middleware('permiso:roles.asignar');
        Route::post('/membresias/{membership}/revocar', [MembershipController::class, 'revoke'])->middleware('permiso:roles.revocar')->whereUuid('membership');

        Route::get('/auditoria', [AuditController::class, 'index'])->middleware('permiso:auditoria.consultar');
        Route::get('/auditoria/verificacion', [AuditController::class, 'verify'])->middleware('permiso:auditoria.consultar');
    });
});
