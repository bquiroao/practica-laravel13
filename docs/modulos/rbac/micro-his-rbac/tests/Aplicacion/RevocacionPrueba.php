<?php

declare(strict_types=1);

/*
 * Cambio práctico de la semana 6: «Revocar un rol».
 * Escrito ANTES de implementar (rojo → verde). Cubre roles, permiso nuevo
 * (roles.revocar), ruta protegida, cambio en la decisión de autorización y
 * denegaciones auditables.
 */

use Rbac\Dominio\Modelo\EventoAuditoria;
use Rbac\Pruebas\Dobles\Escenario;

const REVOCACIONES = '/api/v1/roles/revocaciones';

prueba('REV-01', 'Camino feliz: el administrador revoca un rol (200) y queda ROL_REVOCADO con el motivo en la bitácora', function (): void {
    $e = new Escenario();
    $r = $e->caso('revocarRol')->ejecutar(Escenario::comandoRevocar('admin-01', 'ana', 'enfermeria'));
    igual(200, $r->codigoHttp, 'HTTP');
    igual('ROL_REVOCADO', $r->codigo, 'código');
    $evento = $e->auditoria->buscarPorCorrelacion((string) $r->correlationId);
    igual('ROL_REVOCADO', $evento?->tipo, 'tipo de evento');
    igual('admin-01', $evento?->actorId, 'actor');
    igual('ana', $evento?->datos['usuario_destino'] ?? null, 'usuario destino');
    igual('Cambio de servicio: ya no atiende en esta unidad', $evento?->datos['motivo'] ?? null, 'motivo');
    afirmar(!$e->membresias->existeActiva('ana', Escenario::H_ORIENTE, 'enfermeria'), 'la membresía sigue activa');
});

prueba('REV-02', 'La decisión cambia: después de revocar, la misma operación se deniega con MEMBRESIA_NO_VIGENTE (auditado)', function (): void {
    $e = new Escenario();
    afirmar($e->autorizar->ejecutar(Escenario::comandoAutorizar('ana', 'signos_vitales.registrar'))->permitido, 'antes debía permitirse');
    $e->caso('revocarRol')->ejecutar(Escenario::comandoRevocar('admin-01', 'ana', 'enfermeria'));
    $despues = $e->autorizar->ejecutar(Escenario::comandoAutorizar('ana', 'signos_vitales.registrar'));
    igual(403, $despues->codigoHttp, 'HTTP después de revocar');
    igual('MEMBRESIA_NO_VIGENTE', $e->auditoria->buscarPorCorrelacion((string) $despues->correlationId)?->datos['motivo'] ?? null, 'motivo auditado');
});

prueba('REV-03', 'Sin el permiso nuevo «roles.revocar»: 403, ACCESO_DENEGADO auditado y la membresía no cambia', function (): void {
    $e = new Escenario();
    $r = $e->caso('revocarRol')->ejecutar(Escenario::comandoRevocar('marta', 'ana', 'enfermeria'));
    igual(403, $r->codigoHttp, 'HTTP');
    $evento = $e->auditoria->buscarPorCorrelacion((string) $r->correlationId);
    igual([EventoAuditoria::ACCESO_DENEGADO, 'roles.revocar'], [$evento?->tipo, $evento?->datos['permiso'] ?? null], 'denegación auditada');
    afirmar($e->membresias->existeActiva('ana', Escenario::H_ORIENTE, 'enfermeria'), 'la membresía no debía cambiar');
});

prueba('REV-04', 'Reglas de dominio: autorrevocación 422, motivo insuficiente 422, membresía inexistente o vencida 404', function (): void {
    $e = new Escenario();
    $revocar = $e->caso('revocarRol');
    $autorrevocacion = $revocar->ejecutar(Escenario::comandoRevocar('admin-01', 'admin-01', 'admin_hospital'));
    igual([422, 'AUTORREVOCACION'], [$autorrevocacion->codigoHttp, $autorrevocacion->codigo], 'autorrevocación');
    $sinMotivo = $revocar->ejecutar(Escenario::comandoRevocar('admin-01', 'ana', 'enfermeria', 'porque sí'));
    igual([422, 'MOTIVO_INSUFICIENTE'], [$sinMotivo->codigoHttp, $sinMotivo->codigo], 'motivo corto');
    igual([404, 'MEMBRESIA_NO_ENCONTRADA'], [
        $revocar->ejecutar(Escenario::comandoRevocar('admin-01', 'ana', 'medico'))->codigoHttp,
        $revocar->ejecutar(Escenario::comandoRevocar('admin-01', 'ana', 'medico'))->codigo,
    ], 'rol que no tiene');
    igual(404, $revocar->ejecutar(Escenario::comandoRevocar('admin-01', 'pedro', 'medico'))->codigoHttp, 'membresía vencida');
    igual(0, count($e->auditoria->eventosDeTipo('ROL_REVOCADO')), 'ningún ROL_REVOCADO en rechazos');
});

prueba('REV-05', 'Si la bitácora falla al revocar, la transacción se revierte: 500 y la membresía sigue activa', function (): void {
    $e = new Escenario();
    $e->auditoria->fallar = true;
    igual(500, $e->caso('revocarRol')->ejecutar(Escenario::comandoRevocar('admin-01', 'ana', 'enfermeria'))->codigoHttp, 'HTTP');
    afirmar($e->membresias->existeActiva('ana', Escenario::H_ORIENTE, 'enfermeria'), 'la revocación debió revertirse');
});

prueba('REV-06', 'Ruta protegida HTTP: admin 200, enfermera 403 genérico, formato inválido 422', function (): void {
    $e = new Escenario();
    $cuerpo = ['usuario_id' => 'ana', 'rol' => 'enfermeria', 'motivo' => 'Traslado a otro hospital de la red'];
    $denegada = $e->kernel->manejar(Escenario::http('POST', REVOCACIONES, 'token-ana', cuerpo: $cuerpo));
    igual([403, ['mensaje', 'correlation_id']], [$denegada->estado, array_keys($denegada->cuerpo)], 'enfermera');
    $ok = $e->kernel->manejar(Escenario::http('POST', REVOCACIONES, 'token-admin-oriente', cuerpo: $cuerpo));
    igual([200, 'ROL_REVOCADO'], [$ok->estado, $ok->cuerpo['codigo'] ?? null], 'administrador');
    $mal = $e->kernel->manejar(Escenario::http('POST', REVOCACIONES, 'token-admin-oriente', cuerpo: ['rol' => 5]));
    igual([422, ['usuario_id', 'rol', 'motivo']], [$mal->estado, $mal->cuerpo['campos'] ?? null], 'formato');
});
