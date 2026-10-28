<?php

declare(strict_types=1);

use Auditoria\Presentacion\Http\Autenticacion\AutenticadorTokenDemo;
use Auditoria\Presentacion\Http\Kernel;
use Auditoria\Presentacion\Http\Respuesta;
use Auditoria\Presentacion\Http\Ruta;
use Auditoria\Pruebas\Dobles\Escenario;

const HTTP_MOV = '/api/v1/auditoria/movimientos';
$httpHoy = ['desde' => '2026-10-21', 'hasta' => '2026-10-21'];
$httpPar = static fn (Respuesta $r): array => [$r->estado, $r->cuerpo['error'] ?? $r->cuerpo['codigo'] ?? null];

prueba('HTTP-01', 'POST movimientos → 201 con Location, secuencia y sello; el reintento → 200 YA_REGISTRADO', function () use ($httpPar): void {
    $e = Escenario::crear();
    $r = $e->kernel->manejar(Escenario::http('POST', HTTP_MOV, 'token-svc-pacientes-oriente', Escenario::H_ORIENTE, Escenario::mov()));
    igual([201, HTTP_MOV . '/1', 1], [$r->estado, $r->cabeceras['Location'] ?? null, $r->cuerpo['secuencia']], 'alta');
    $reintento = $e->kernel->manejar(Escenario::http('POST', HTTP_MOV, 'token-svc-pacientes-oriente', Escenario::H_ORIENTE, Escenario::mov()));
    igual([200, 'YA_REGISTRADO'], $httpPar($reintento), 'reintento');
    afirmar(!isset($reintento->cabeceras['Location']), 'el 200 lleva Location: PHP lo enviaría como 302');
});

prueba('HTTP-02', 'Orden del kernel: 404 ruta, 405 método, 400 sin hospital, 401 sin token o inválido, 403 tenant del token', function () use ($httpPar): void {
    $k = Escenario::crear()->kernel;
    igual([404, 'RUTA_NO_ENCONTRADA'], $httpPar($k->manejar(Escenario::http('GET', '/api/v1/auditoria/todo', null, ''))), '404 antes que todo');
    igual([405, 'METODO_NO_PERMITIDO'], $httpPar($k->manejar(Escenario::http('DELETE', HTTP_MOV . '/1', 'token-superadmin', '*'))), 'DELETE no existe');
    igual([405, 'METODO_NO_PERMITIDO'], $httpPar($k->manejar(Escenario::http('PUT', HTTP_MOV . '/1', 'token-superadmin', '*'))), 'PUT no existe');
    igual([400, 'HOSPITAL_REQUERIDO'], $httpPar($k->manejar(Escenario::http('GET', HTTP_MOV, null, ''))), 'sin hospital, antes que el token');
    igual([401, 'NO_AUTENTICADO'], $httpPar($k->manejar(Escenario::http('GET', HTTP_MOV, null))), 'sin token');
    igual([401, 'NO_AUTENTICADO'], $httpPar($k->manejar(Escenario::http('GET', HTTP_MOV, 'token-falso'))), 'token inválido');
    igual([403, 'TENANT_NO_COINCIDE'], $httpPar($k->manejar(Escenario::http('GET', HTTP_MOV, 'token-auditor-oriente', Escenario::H_NORTE))), 'otro hospital');
    igual([403, 'TENANT_NO_COINCIDE'], $httpPar($k->manejar(Escenario::http('GET', HTTP_MOV, 'token-auditor-oriente', '*'))), 'auditor pide la red');
});

prueba('HTTP-03', 'Registro: sin cuerpo 400; datos inválidos 422 con campos; módulo ajeno 403', function () use ($httpPar): void {
    $k = Escenario::crear()->kernel;
    $post = static fn (?array $cuerpo): Respuesta => $k->manejar(Escenario::http('POST', HTTP_MOV, 'token-svc-pacientes-oriente', Escenario::H_ORIENTE, $cuerpo));
    igual([400, 'CUERPO_INVALIDO'], $httpPar($post(null)), 'sin cuerpo');
    $r = $post(Escenario::mov(['ocurrido_en' => 'ayer']));
    igual([422, 'DATOS_INVALIDOS', ['ocurrido_en']], [$r->estado, $r->cuerpo['error'], array_keys($r->cuerpo['campos'])], 'fecha');
    igual([403, 'MODULO_NO_PERMITIDO'], $httpPar($post(Escenario::mov(['modulo' => 'rbac', 'accion' => 'ROL_ASIGNADO']))), 'módulo ajeno');
});

prueba('HTTP-04', 'Consulta: 200 con movimientos; sin rango 422; filtro desconocido 422; parámetro repetido (arreglo) 400', function () use ($httpHoy, $httpPar): void {
    $e = Escenario::crear();
    $e->kernel->manejar(Escenario::http('POST', HTTP_MOV, 'token-svc-pacientes-oriente', Escenario::H_ORIENTE, Escenario::mov()));
    $get = static fn (array $q): Respuesta => $e->kernel->manejar(Escenario::http('GET', HTTP_MOV, 'token-auditor-oriente', Escenario::H_ORIENTE, null, $q));
    $ok = $get($httpHoy + ['usuario' => 'u-ana', 'resultado' => 'exito']);
    igual([200, 1, null], [$ok->estado, count($ok->cuerpo['movimientos']), $ok->cuerpo['cursor_siguiente']], 'consulta');
    igual([422, 'DATOS_INVALIDOS'], $httpPar($get(['usuario' => 'u-ana'])), 'sin rango');
    igual([422, 'FILTRO_NO_SOPORTADO'], $httpPar($get($httpHoy + ['ip' => '10.0.0.1'])), 'filtro desconocido');
    igual([400, 'FORMATO_INVALIDO'], $httpPar($get($httpHoy + ['usuario' => ['a', 'b']])), 'usuario[]=a&usuario[]=b');
});

prueba('HTTP-05', 'Detalle: el de otro hospital da el MISMO 404 que uno inexistente; el super administrador lo ve con X-Tenant-ID «*»', function () use ($httpHoy): void {
    $e = Escenario::crear();
    $e->kernel->manejar(Escenario::http('POST', HTTP_MOV, 'token-svc-pacientes-norte', Escenario::H_NORTE, Escenario::mov(['hospital' => Escenario::H_NORTE])));
    $ajeno = $e->kernel->manejar(Escenario::http('GET', HTTP_MOV . '/1', 'token-auditor-oriente'));
    $inexistente = $e->kernel->manejar(Escenario::http('GET', HTTP_MOV . '/999', 'token-auditor-oriente'));
    igual([$inexistente->estado, $inexistente->cuerpo], [$ajeno->estado, $ajeno->cuerpo], 'ajeno = inexistente');
    igual(404, $ajeno->estado, 'estado');
    $red = $e->kernel->manejar(Escenario::http('GET', HTTP_MOV . '/1', 'token-superadmin', '*'));
    igual([200, Escenario::H_NORTE], [$red->estado, $red->cuerpo['movimiento']['hospital']], 'super administrador');
    $v = $e->kernel->manejar(Escenario::http('POST', '/api/v1/auditoria/verificaciones', 'token-superadmin', '*', ['hospital' => Escenario::H_NORTE]));
    igual([200, 'INTEGRA'], [$v->estado, $v->cuerpo['estado']], 'verificación');
});

prueba('HTTP-06', 'Base caída → 503 genérico; excepción inesperada → 500 genérico; ninguno revela detalles internos', function () use ($httpHoy): void {
    $e = Escenario::crear();
    $e->bitacora->fallarEn = ['buscar'];
    $r = $e->kernel->manejar(Escenario::http('GET', HTTP_MOV, 'token-auditor-oriente', Escenario::H_ORIENTE, null, $httpHoy));
    igual([503, ['error' => 'SERVICIO_NO_DISPONIBLE']], [$r->estado, $r->cuerpo], 'base caída');
    $k = new Kernel([new Ruta('GET', '#^/explota$#', static fn () => throw new RuntimeException('SQLSTATE secreto en /var/www'))],
        new AutenticadorTokenDemo(['t' => ['a', 'h']]));
    $r = $k->manejar(Escenario::http('GET', '/explota', 't', 'h'));
    igual([500, ['error' => 'ERROR_INTERNO']], [$r->estado, $r->cuerpo], 'inesperada');
});
