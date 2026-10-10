<?php

declare(strict_types=1);

// Casos de uso con dobles en memoria: camino feliz, denegación trazable y reglas de dominio.

use Rbac\Aplicacion\Resultados\ResultadoAutorizacion;
use Rbac\Dominio\Modelo\EventoAuditoria;
use Rbac\Pruebas\Dobles\Escenario;

prueba('APP-01', 'Camino feliz: asignar rol crea la membresía y registra ROL_ASIGNADO (201)', function (): void {
    $e = new Escenario();
    $r = $e->asignarRol->ejecutar(Escenario::comandoAsignar('admin-01', 'nuevo-01', 'laboratorio'));
    igual(201, $r->codigoHttp, 'HTTP');
    afirmar($e->membresias->existeActiva('nuevo-01', Escenario::H_ORIENTE, 'laboratorio'), 'no se creó la membresía');
    $evento = $e->auditoria->buscarPorCorrelacion((string) $r->correlationId);
    igual(EventoAuditoria::ROL_ASIGNADO, $evento?->tipo, 'tipo de evento');
    igual('admin-01', $evento?->actorId, 'actor');
    igual('laboratorio', $evento?->datos['rol'] ?? null, 'rol en el evento');
});

prueba('APP-02', 'Camino feliz: operación permitida por el rol vigente, sin eventos', function (): void {
    $e = new Escenario();
    $r = $e->autorizar->ejecutar(Escenario::comandoAutorizar('ana', 'signos_vitales.registrar'));
    afirmar($r->permitido, 'debió permitirse');
    igual(0, count($e->auditoria->eventos()), 'eventos registrados');
});

prueba('APP-03', 'Denegación trazable: 403 + correlation_id + ACCESO_DENEGADO completo; el resultado no expone el motivo', function (): void {
    $e = new Escenario();
    $r = $e->autorizar->ejecutar(Escenario::comandoAutorizar('ana', 'prescripciones.crear'));
    igual(403, $r->codigoHttp, 'HTTP');
    $evento = $e->auditoria->buscarPorCorrelacion((string) $r->correlationId);
    igual(EventoAuditoria::ACCESO_DENEGADO, $evento?->tipo, 'tipo');
    igual('PERMISO_NO_ASIGNADO', $evento?->datos['motivo'] ?? null, 'motivo en bitácora');
    igual('prescripciones.crear', $evento?->datos['permiso'] ?? null, 'permiso');
    igual('10.20.5.14', $evento?->datos['ip'] ?? null, 'ip');
    $propiedades = array_map(static fn (ReflectionProperty $p): string => $p->getName(), (new ReflectionClass(ResultadoAutorizacion::class))->getProperties());
    afirmar(!in_array('motivo', $propiedades, true), 'ResultadoAutorizacion no debe exponer el motivo (R-08)');
});

prueba('APP-04', 'Asignar sin «roles.asignar»: 403, ACCESO_DENEGADO y ninguna membresía nueva', function (): void {
    $e = new Escenario();
    $antes = $e->membresias->total();
    $r = $e->asignarRol->ejecutar(Escenario::comandoAsignar('ana', 'nuevo-01', 'medico'));
    igual(403, $r->codigoHttp, 'HTTP');
    igual($antes, $e->membresias->total(), 'membresías');
    igual(1, count($e->auditoria->eventosDeTipo(EventoAuditoria::ACCESO_DENEGADO)), 'eventos de denegación');
});

prueba('APP-05', 'Reglas de dominio desde el caso de uso: duplicado 409, autoasignación 422, usuario inactivo 422', function (): void {
    $e = new Escenario();
    igual(409, $e->asignarRol->ejecutar(Escenario::comandoAsignar('admin-01', 'ana', 'enfermeria'))->codigoHttp, 'duplicado');
    igual(422, $e->asignarRol->ejecutar(Escenario::comandoAsignar('admin-01', 'admin-01', 'medico'))->codigoHttp, 'autoasignación');
    igual(422, $e->asignarRol->ejecutar(Escenario::comandoAsignar('admin-01', 'jorge', 'medico'))->codigoHttp, 'inactivo');
    igual(0, count($e->auditoria->eventosDeTipo(EventoAuditoria::ROL_ASIGNADO)), 'no debe haber ROL_ASIGNADO');
});

prueba('APP-06', 'Consultar evento: solo desde el mismo hospital (aislamiento por tenant)', function (): void {
    $e = new Escenario();
    $r = $e->autorizar->ejecutar(Escenario::comandoAutorizar('ana', 'prescripciones.crear'));
    $evento = $e->consultar->ejecutar((string) $r->correlationId, Escenario::H_ORIENTE);
    igual('PERMISO_NO_ASIGNADO', $evento['datos']['motivo'] ?? null, 'mismo hospital');
    igual(null, $e->consultar->ejecutar((string) $r->correlationId, Escenario::H_NORTE), 'otro hospital');
});
