<?php

declare(strict_types=1);

use Qa\Presentacion\Http\Solicitud;

$pedir = static function (Escenario $e, string $metodo, string $ruta, string $token = 'token-lider-qa', ?array $cuerpo = null, array $cabeceras = []): \Qa\Presentacion\Http\Respuesta {
    return $e->kernel()->manejar(new Solicitud($metodo, $ruta, $cabeceras + ['authorization' => "Bearer $token", 'x-tenant-id' => 'hosp-norte'],
        $cuerpo === null ? '' : json_encode($cuerpo)));
};

prueba('HTTP-01', 'Recorrido HTTP: planificar 201 + Location, iniciar 200, resultado 201 con evidencia base64, trazabilidad 200', function () use ($pedir): void {
    $e = new Escenario();
    $p = $pedir($e, 'POST', '/api/v1/qa/campanas', cuerpo: ['nombre' => 'H4', 'funcionalidades' => ['expediente'], 'casos' => ['TC-107', 'TC-108']]);
    igual([201, '/api/v1/qa/campanas/1/trazabilidad'], [$p->estado, $p->cabeceras['Location']], 'planificar');
    igual(200, $pedir($e, 'POST', '/api/v1/qa/campanas/1/inicio', cuerpo: ['build' => '1.4.0'])->estado, 'iniciar');
    $r = $pedir($e, 'POST', '/api/v1/qa/campanas/1/resultados', 'token-tito-tester', ['caso' => 'TC-107', 'resultado' => 'FALLA',
        'evidencias' => [['tipo' => 'captura', 'contenido_base64' => base64_encode(Escenario::PNG)]],
        'defecto' => ['titulo' => 'Nota firmada editable', 'pasos' => ['Abrir', 'Editar'], 'severidad' => 'CRITICA']], ['idempotency-key' => 'http-0000001']);
    igual([201, 'RESULTADO_REGISTRADO', 1], [$r->estado, $r->cuerpo['codigo'], $r->cuerpo['defecto']], 'resultado');
    $t = $pedir($e, 'GET', '/api/v1/qa/campanas/1/trazabilidad', 'token-producto');
    igual([200, 'FALLA'], [$t->estado, $t->cuerpo['casos'][0]['ultimo_resultado']], 'trazabilidad');
});

prueba('HTTP-02', 'Autenticación y tenant: sin token 401 (WWW-Authenticate), sin X-Tenant-ID 400, tenant ajeno 403', function (): void {
    $e = new Escenario();
    $k = $e->kernel();
    $sin = $k->manejar(new Solicitud('GET', '/api/v1/qa/campanas/1/trazabilidad'));
    $sinTenant = $k->manejar(new Solicitud('GET', '/api/v1/qa/campanas/1/trazabilidad', ['authorization' => 'Bearer token-producto']));
    $ajeno = $k->manejar(new Solicitud('GET', '/api/v1/qa/campanas/1/trazabilidad', ['authorization' => 'Bearer token-producto', 'x-tenant-id' => 'hosp-sur']));
    igual([401, 'Bearer', 400, 403], [$sin->estado, $sin->cabeceras['WWW-Authenticate'], $sinTenant->estado, $ajeno->estado], 'estados');
});

prueba('HTTP-03', 'Rutas: 404 desconocida, 405 método equivocado, y toda respuesta lleva X-Request-ID', function () use ($pedir): void {
    $e = new Escenario();
    $no = $pedir($e, 'GET', '/api/v1/qa/nada');
    $metodo = $pedir($e, 'DELETE', '/api/v1/qa/campanas/1/trazabilidad');
    $propio = $pedir($e, 'GET', '/api/v1/qa/nada', cabeceras: ['x-request-id' => 'mi-peticion-123']);
    igual([404, 405, 'mi-peticion-123'], [$no->estado, $metodo->estado, $propio->cabeceras['X-Request-ID']], 'estados');
    afirmar(preg_match('/^[0-9a-f]{16}$/', $no->cabeceras['X-Request-ID']) === 1, 'X-Request-ID generado');
});

prueba('HTTP-04', 'Formato: JSON roto 400, lista en vez de objeto 400, base64 inválido 400, sin Idempotency-Key 400', function () use ($pedir): void {
    $e = new Escenario();
    $id = $e->campanaEnCurso();
    $roto = $e->kernel()->manejar(new Solicitud('POST', '/api/v1/qa/campanas', ['authorization' => 'Bearer token-lider-qa', 'x-tenant-id' => 'hosp-norte'], '{"nombre":'));
    $lista = $pedir($e, 'POST', '/api/v1/qa/campanas', cuerpo: ['a', 'b']);
    $b64 = $pedir($e, 'POST', "/api/v1/qa/campanas/$id/resultados", 'token-tito-tester', ['caso' => 'TC-107', 'resultado' => 'FALLA',
        'evidencias' => [['tipo' => 'captura', 'contenido_base64' => '%%no-base64%%']]], ['idempotency-key' => 'http-0000002']);
    $sinClave = $pedir($e, 'POST', "/api/v1/qa/campanas/$id/resultados", 'token-tito-tester', ['caso' => 'TC-107', 'resultado' => 'PASA']);
    igual([400, 400, 'EVIDENCIA_BASE64_INVALIDA', 'IDEMPOTENCY_KEY_REQUERIDA'], [$roto->estado, $lista->estado, $b64->cuerpo['error'], $sinClave->cuerpo['error']], 'formato');
});

prueba('HTTP-05', 'Traducción de la salida: 403 sin permiso, 404 inexistente, 409 regla de estado, 422 regla de datos, 503 base caída', function () use ($pedir): void {
    $e = new Escenario();
    $sinPermiso = $pedir($e, 'POST', '/api/v1/qa/casos/TC-111/aprobacion', 'token-tito-tester');
    $inexistente = $pedir($e, 'GET', '/api/v1/qa/campanas/99/trazabilidad');
    $autor = $pedir($e, 'POST', '/api/v1/qa/casos', 'token-lider-qa', ['id' => 'TC-300', 'titulo' => 'X', 'pasos' => [['accion' => 'a', 'esperado' => 'b']],
        'prioridad' => 'ALTA', 'requisitos' => ['RF-01']]);
    $conflicto = $pedir($e, 'POST', '/api/v1/qa/casos/TC-300/aprobacion');
    $datos = $pedir($e, 'POST', '/api/v1/qa/casos', 'token-ana-qa', ['id' => 'TC-301', 'titulo' => '', 'pasos' => [], 'prioridad' => 'ALTA', 'requisitos' => []]);
    $e->campanas->fallarEn = 'porId';
    $caida = $pedir($e, 'GET', '/api/v1/qa/campanas/1/trazabilidad');
    igual([403, 404, 201, 409, 422, 503], [$sinPermiso->estado, $inexistente->estado, $autor->estado, $conflicto->estado, $datos->estado, $caida->estado], 'estados');
    igual(['titulo', 'pasos', 'requisitos'], $datos->cuerpo['detalle'], 'el 422 dice qué campos corregir');
});

prueba('HTTP-06', 'Una excepción inesperada se convierte en 500 genérico sin detalles internos', function (): void {
    $kernel = new \Qa\Presentacion\Http\Kernel([new \Qa\Presentacion\Http\Ruta('GET', '#^/explota$#', static fn () => throw new \LogicException('SQLSTATE secreto'))],
        new \Qa\Presentacion\Http\Autenticacion\AutenticadorTokenDemo(['t' => ['u', 'hosp-norte']]));
    $r = $kernel->manejar(new Solicitud('GET', '/explota', ['authorization' => 'Bearer t', 'x-tenant-id' => 'hosp-norte']));
    igual([500, ['error' => 'ERROR_INTERNO']], [$r->estado, $r->cuerpo], '500');
});
