<?php

declare(strict_types=1);

// Capa de presentación: el Kernel recibe una Solicitud construida en memoria (sin servidor web).

use Rbac\Presentacion\Http\Autenticacion\AutenticadorTokenDemo;
use Rbac\Presentacion\Http\Kernel;
use Rbac\Presentacion\Http\MiddlewareAutorizacion;
use Rbac\Presentacion\Http\Peticion;
use Rbac\Presentacion\Http\Respuesta;
use Rbac\Presentacion\Http\Ruta;
use Rbac\Pruebas\Dobles\Escenario;

const ASIGNACIONES = '/api/v1/roles/asignaciones';

prueba('HTTP-01', 'Camino feliz HTTP: POST asignaciones con token de administrador → 201 + correlation_id', function (): void {
    $e = new Escenario();
    $r = $e->kernel->manejar(Escenario::http('POST', ASIGNACIONES, 'token-admin-oriente', cuerpo: [
        'usuario_id' => 'nuevo-01', 'rol' => 'laboratorio', 'valido_desde' => '2026-11-15T00:00:00-06:00',
    ]));
    igual(201, $r->estado, 'HTTP');
    igual('ROL_ASIGNADO', $r->cuerpo['codigo'] ?? null, 'código');
    afirmar(isset($r->cuerpo['correlation_id']), 'falta correlation_id');
});

prueba('HTTP-02', 'Cadena del Kernel: 404 ruta, 405 método, 400 sin tenant, 401 sin token o token inválido', function (): void {
    $k = (new Escenario())->kernel;
    igual(404, $k->manejar(Escenario::http('GET', '/api/v1/no-existe', 'token-ana'))->estado, 'ruta');
    igual(405, $k->manejar(Escenario::http('GET', ASIGNACIONES, 'token-ana'))->estado, 'método');
    igual(400, $k->manejar(Escenario::http('POST', '/api/v1/signos-vitales', 'token-ana', tenant: null))->estado, 'sin X-Tenant-ID');
    igual(401, $k->manejar(Escenario::http('POST', '/api/v1/signos-vitales', null))->estado, 'sin token');
    igual(401, $k->manejar(Escenario::http('POST', '/api/v1/signos-vitales', 'token-falso'))->estado, 'token inválido');
});

prueba('HTTP-03', 'Ruta protegida denegada: 403 solo con mensaje genérico y correlation_id (sin motivo ni rol)', function (): void {
    $e = new Escenario();
    $r = $e->kernel->manejar(Escenario::http('POST', '/api/v1/prescripciones', 'token-ana'));
    igual(403, $r->estado, 'HTTP');
    igual(['mensaje', 'correlation_id'], array_keys($r->cuerpo), 'campos de la respuesta');
    $json = (string) json_encode($r->cuerpo);
    afirmar(!str_contains($json, 'PERMISO_NO_ASIGNADO') && !str_contains($json, 'enfermeria'), 'la respuesta revela detalles internos');
    igual('PERMISO_NO_ASIGNADO', $e->auditoria->buscarPorCorrelacion((string) $r->cuerpo['correlation_id'])?->datos['motivo'] ?? null, 'motivo solo en la bitácora');
});

prueba('HTTP-04', 'Ruta protegida permitida: la médica crea una prescripción (201)', function (): void {
    igual(201, (new Escenario())->kernel->manejar(Escenario::http('POST', '/api/v1/prescripciones', 'token-marta'))->estado, 'HTTP');
});

prueba('HTTP-05', 'Validación de formato en el formulario: datos faltantes o fechas inválidas → 422 DATOS_INVALIDOS', function (): void {
    $r = (new Escenario())->kernel->manejar(Escenario::http('POST', ASIGNACIONES, 'token-admin-oriente', cuerpo: [
        'rol' => 'medico', 'valido_desde' => 'no-es-fecha',
    ]));
    igual(422, $r->estado, 'HTTP');
    igual(['usuario_id', 'valido_desde'], $r->cuerpo['campos'] ?? null, 'campos inválidos');
});

prueba('HTTP-06', 'Consulta de auditoría: admin del mismo hospital 200; admin de otro hospital 404; enfermera 403', function (): void {
    $e = new Escenario();
    $cid = (string) $e->kernel->manejar(Escenario::http('POST', '/api/v1/prescripciones', 'token-ana'))->cuerpo['correlation_id'];
    $ruta = '/api/v1/auditoria/eventos/' . $cid;

    $propio = $e->kernel->manejar(Escenario::http('GET', $ruta, 'token-admin-oriente'));
    igual(200, $propio->estado, 'mismo hospital');
    igual('PERMISO_NO_ASIGNADO', $propio->cuerpo['datos']['motivo'] ?? null, 'motivo visible para el auditor');
    igual(404, $e->kernel->manejar(Escenario::http('GET', $ruta, 'token-admin-norte', Escenario::H_NORTE))->estado, 'otro hospital');
    igual(403, $e->kernel->manejar(Escenario::http('GET', $ruta, 'token-ana'))->estado, 'sin auditoria.consultar');
});

prueba('HTTP-07', 'Token de un hospital con X-Tenant-ID de otro → 403', function (): void {
    igual(403, (new Escenario())->kernel->manejar(Escenario::http('POST', '/api/v1/signos-vitales', 'token-ana', Escenario::H_NORTE))->estado, 'HTTP');
});

prueba('HTTP-08', 'Error inesperado en una acción → 500 genérico, sin traza ni detalles', function (): void {
    $e = new Escenario();
    $controladorRoto = static function (Peticion $peticion): Respuesta {
        throw new RuntimeException('SQLSTATE[XX000] detalle interno');
    };
    $kernel = new Kernel(
        [new Ruta('POST', '#^/api/v1/signos-vitales$#', 'signos_vitales.registrar', $controladorRoto)],
        new AutenticadorTokenDemo(['token-ana' => ['ana', Escenario::H_ORIENTE]]),
        new MiddlewareAutorizacion($e->autorizar),
    );
    $r = $kernel->manejar(Escenario::http('POST', '/api/v1/signos-vitales', 'token-ana'));
    igual(500, $r->estado, 'HTTP');
    igual(['mensaje' => 'Error interno.'], $r->cuerpo, 'cuerpo');
});
