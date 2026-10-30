<?php

use App\Http\Controllers\Api\V1\Admin\AuditController;
use App\Http\Controllers\Api\V1\Admin\MembershipController;
use App\Http\Controllers\Api\V1\Admin\RoleController;
use App\Http\Controllers\Api\V1\Admin\UserController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\Clinical\AppointmentController;
use App\Http\Controllers\Api\V1\Clinical\DoctorController;
use App\Http\Controllers\Api\V1\Clinical\PatientController;
use App\Http\Controllers\Api\V1\Clinical\SpecialtyController;
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

        // Fase 2: pacientes, médicos-especialidades y citas médicas. Los ids se buscan en la base del hospital
        // dentro del controlador (no con route model binding, que correría antes de elegir esa base).
        Route::get('/pacientes', [PatientController::class, 'index'])->middleware('permiso:pacientes.ver');
        Route::post('/pacientes', [PatientController::class, 'store'])->middleware('permiso:pacientes.gestionar');
        Route::get('/pacientes/{patient}', [PatientController::class, 'show'])->middleware('permiso:pacientes.ver')->whereUuid('patient');
        Route::put('/pacientes/{patient}', [PatientController::class, 'update'])->middleware('permiso:pacientes.gestionar')->whereUuid('patient');

        Route::get('/especialidades', [SpecialtyController::class, 'index'])->middleware('permiso:medicos.ver|citas.ver');
        Route::post('/especialidades', [SpecialtyController::class, 'store'])->middleware('permiso:medicos.gestionar');
        Route::patch('/especialidades/{specialty}', [SpecialtyController::class, 'update'])->middleware('permiso:medicos.gestionar')->whereUuid('specialty');

        Route::get('/medicos', [DoctorController::class, 'index'])->middleware('permiso:medicos.ver|citas.ver');
        Route::get('/medicos/candidatos', [DoctorController::class, 'candidates'])->middleware('permiso:medicos.gestionar');
        Route::post('/medicos', [DoctorController::class, 'store'])->middleware('permiso:medicos.gestionar');
        Route::get('/medicos/{doctor}', [DoctorController::class, 'show'])->middleware('permiso:medicos.ver')->whereUuid('doctor');
        Route::post('/medicos/{doctor}/especialidades', [DoctorController::class, 'addSpecialty'])->middleware('permiso:medicos.gestionar')->whereUuid('doctor');
        Route::delete('/medicos/{doctor}/especialidades/{link}', [DoctorController::class, 'endSpecialty'])->middleware('permiso:medicos.gestionar')->whereUuid(['doctor', 'link']);
        Route::put('/medicos/{doctor}/horario', [DoctorController::class, 'schedule'])->middleware('permiso:medicos.gestionar')->whereUuid('doctor');
        Route::patch('/medicos/{doctor}/estado', [DoctorController::class, 'updateStatus'])->middleware('permiso:medicos.gestionar')->whereUuid('doctor');

        Route::get('/disponibilidad', [AppointmentController::class, 'availability'])->middleware('permiso:citas.ver');
        Route::get('/citas', [AppointmentController::class, 'index'])->middleware('permiso:citas.ver');
        Route::post('/citas', [AppointmentController::class, 'store'])->middleware('permiso:citas.gestionar');
        Route::post('/citas/{appointment}/estado', [AppointmentController::class, 'transition'])->middleware('permiso:citas.gestionar')->whereUuid('appointment');
    });
});
