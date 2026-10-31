<?php

use App\Http\Controllers\Api\V1\Admin\AnalyticsController;
use App\Http\Controllers\Api\V1\Admin\AuditController;
use App\Http\Controllers\Api\V1\Admin\MembershipController;
use App\Http\Controllers\Api\V1\Admin\QualityController;
use App\Http\Controllers\Api\V1\Admin\ReportController;
use App\Http\Controllers\Api\V1\Admin\RoleController;
use App\Http\Controllers\Api\V1\Admin\SystemController;
use App\Http\Controllers\Api\V1\Admin\UserController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\Clinical\AdmissionController;
use App\Http\Controllers\Api\V1\Clinical\AllergyController;
use App\Http\Controllers\Api\V1\Clinical\AppointmentController;
use App\Http\Controllers\Api\V1\Clinical\BedController;
use App\Http\Controllers\Api\V1\Clinical\DoctorController;
use App\Http\Controllers\Api\V1\Clinical\LabController;
use App\Http\Controllers\Api\V1\Clinical\PatientController;
use App\Http\Controllers\Api\V1\Clinical\PrescriptionController;
use App\Http\Controllers\Api\V1\Clinical\RecordController;
use App\Http\Controllers\Api\V1\Clinical\SpecialtyController;
use App\Http\Controllers\Api\V1\HospitalController;
use Illuminate\Support\Facades\Route;

Route::get('/hospitales', [HospitalController::class, 'index']);

// El pipeline de CI registra sus ejecuciones con NIDO_CI_TOKEN (sin hospital ni sesión).
Route::post('/calidad/ci/ejecuciones', [QualityController::class, 'storeCiRun']);

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

        // Fase 3: catálogo de salas y camas, traslados y altas, y ocupación.
        Route::get('/camas', [BedController::class, 'index'])->middleware('permiso:camas.ver');
        Route::post('/salas', [BedController::class, 'storeWard'])->middleware('permiso:camas.gestionar');
        Route::post('/salas/{ward}/camas', [BedController::class, 'storeBed'])->middleware('permiso:camas.gestionar')->whereUuid('ward');
        Route::patch('/camas/{bed}/estado', [BedController::class, 'updateBedStatus'])->middleware('permiso:camas.gestionar')->whereUuid('bed');
        Route::post('/internamientos/{episode}/traslado', [BedController::class, 'transfer'])->middleware('permiso:traslados.gestionar')->whereUuid('episode');
        Route::post('/internamientos/{episode}/alta', [BedController::class, 'discharge'])->middleware('permiso:traslados.gestionar')->whereUuid('episode');
        Route::get('/ocupacion', [BedController::class, 'occupancy'])->middleware('permiso:ocupacion.ver');

        // Fase 4: expediente médico, notas SOAP y diagnósticos, y signos vitales.
        Route::get('/expediente/{patient}', [RecordController::class, 'show'])->middleware('permiso:expediente.leer')->whereUuid('patient');
        Route::post('/expediente/{patient}/notas', [RecordController::class, 'storeNote'])->middleware('permiso:notas.registrar')->whereUuid('patient');
        Route::put('/notas/{note}', [RecordController::class, 'updateNote'])->middleware('permiso:notas.registrar')->whereUuid('note');
        Route::post('/notas/{note}/correccion', [RecordController::class, 'correctNote'])->middleware('permiso:notas.firmar')->whereUuid('note');
        Route::post('/expediente/{patient}/signos', [RecordController::class, 'storeVitals'])->middleware('permiso:signos_vitales.registrar')->whereUuid('patient');
        Route::post('/expediente/{patient}/consulta', [RecordController::class, 'startConsultation'])->middleware('permiso:notas.registrar')->whereUuid('patient');
        Route::post('/episodios/{episode}/terminar', [RecordController::class, 'endConsultation'])->middleware('permiso:notas.registrar')->whereUuid('episode');

        // Fase 5: laboratorio (órdenes, recepción, resultados, validación) y prescripciones.
        $lab = 'permiso:pruebas_lab.ver|ordenes_lab.crear|muestras.recibir|resultados.registrar|resultados.validar|expediente.leer';
        Route::get('/laboratorio/pruebas', [LabController::class, 'tests'])->middleware($lab);
        Route::get('/laboratorio/trabajo', [LabController::class, 'worklist'])->middleware('permiso:muestras.recibir|resultados.registrar|resultados.validar');
        Route::get('/expediente/{patient}/laboratorio', [LabController::class, 'patientOrders'])->middleware($lab)->whereUuid('patient');
        Route::post('/expediente/{patient}/laboratorio', [LabController::class, 'store'])->middleware('permiso:ordenes_lab.crear')->whereUuid('patient');
        Route::post('/laboratorio/items/{item}/recibir', [LabController::class, 'receive'])->middleware('permiso:muestras.recibir')->whereUuid('item');
        Route::post('/laboratorio/items/{item}/rechazar', [LabController::class, 'reject'])->middleware('permiso:muestras.recibir')->whereUuid('item');
        Route::post('/laboratorio/items/{item}/resultado', [LabController::class, 'result'])->middleware('permiso:resultados.registrar')->whereUuid('item');
        Route::post('/laboratorio/resultados/{result}/revision', [LabController::class, 'review'])->middleware('permiso:resultados.validar')->whereUuid('result');

        Route::get('/medicamentos', [PrescriptionController::class, 'medications'])->middleware('permiso:medicamentos.ver|prescripciones.crear');
        Route::get('/recetas', [PrescriptionController::class, 'index'])->middleware('permiso:prescripciones.crear|medicamentos.ver|expediente.leer');
        Route::post('/expediente/{patient}/recetas', [PrescriptionController::class, 'store'])->middleware('permiso:prescripciones.crear')->whereUuid('patient');
        Route::post('/recetas/{prescription}/anular', [PrescriptionController::class, 'cancel'])->middleware('permiso:prescripciones.anular')->whereUuid('prescription');

        // Fase 6: reportes operativos, calidad (defectos, ejecuciones y quality gate), verificación de BD y contratos API.
        Route::get('/reportes/operativos/tipos', [ReportController::class, 'types'])->middleware('permiso:reportes.ver|reportes.exportar');
        Route::get('/reportes/operativos', [ReportController::class, 'preview'])->middleware('permiso:reportes.ver|reportes.exportar');
        Route::get('/reportes/operativos/exportar', [ReportController::class, 'export'])->middleware('permiso:reportes.exportar');
        Route::get('/calidad/defectos', [QualityController::class, 'defects'])->middleware('permiso:calidad.gestionar|versiones.gestionar');
        Route::post('/calidad/defectos', [QualityController::class, 'storeDefect'])->middleware('permiso:calidad.gestionar');
        Route::post('/calidad/defectos/{defect}/estado', [QualityController::class, 'transitionDefect'])->middleware('permiso:calidad.gestionar')->whereUuid('defect');
        Route::get('/calidad/ejecuciones', [QualityController::class, 'runs'])->middleware('permiso:calidad.gestionar|versiones.gestionar');
        Route::post('/calidad/ejecuciones', [QualityController::class, 'storeRun'])->middleware('permiso:versiones.gestionar');
        Route::get('/calidad/gate/{version}', [QualityController::class, 'gate'])->middleware('permiso:calidad.gestionar|versiones.gestionar');
        Route::get('/sistema/base-datos', [SystemController::class, 'database'])->middleware('permiso:base_datos.verificar');
        Route::get('/sistema/openapi', [SystemController::class, 'openapi']);

        // Módulo admisión hospitalaria y asignación de cama: abre el internamiento y su estancia en una cama disponible.
        Route::get('/camas-disponibles', [AdmissionController::class, 'availableBeds'])->middleware('permiso:admisiones.gestionar');
        Route::post('/admisiones', [AdmissionController::class, 'store'])->middleware('permiso:admisiones.gestionar');
        Route::get('/admisiones/{admission}', [AdmissionController::class, 'show'])->middleware('permiso:admisiones.gestionar|camas.ver')->whereUuid('admission');
        Route::get('/admisiones/{admission}/eventos', [AdmissionController::class, 'events'])->middleware('permiso:admisiones.gestionar|auditoria.consultar')->whereUuid('admission');

        // Módulo alergias clínicas y alerta visual en el expediente: único que escribe en allergies.
        Route::get('/expediente/{patient}/alergias', [AllergyController::class, 'show'])->middleware('permiso:expediente.leer|alergias.gestionar|prescripciones.crear')->whereUuid('patient');
        Route::post('/expediente/{patient}/alergias', [AllergyController::class, 'store'])->middleware('permiso:alergias.gestionar')->whereUuid('patient');
        Route::post('/expediente/{patient}/alergias/reconocimiento', [AllergyController::class, 'acknowledge'])->middleware('permiso:expediente.leer|alergias.gestionar')->whereUuid('patient');
        Route::patch('/alergias/{allergy}/estado', [AllergyController::class, 'updateStatus'])->middleware('permiso:alergias.gestionar')->whereUuid('allergy');

        // Módulo reportes y analytics básicos: indicadores trazables en tres niveles (indicador, desglose y registros).
        // Van después de /reportes/operativos para que esas rutas fijas no las tome {tipo}.
        $analytics = 'permiso:reportes.ver|reportes.citas_propias';
        Route::get('/reportes/indicadores', [AnalyticsController::class, 'catalog'])->middleware($analytics);
        Route::get('/reportes/indicadores/resumen', [AnalyticsController::class, 'summary'])->middleware($analytics);
        Route::get('/reportes/{tipo}', [AnalyticsController::class, 'show'])->middleware($analytics)->where('tipo', '[a-z_]+');
        Route::get('/reportes/{tipo}/desglose', [AnalyticsController::class, 'breakdown'])->middleware($analytics)->where('tipo', '[a-z_]+');
        Route::get('/reportes/{tipo}/registros', [AnalyticsController::class, 'records'])->middleware($analytics)->where('tipo', '[a-z_]+');
    });
});
