<?php

declare(strict_types=1);

use E2e\Presentacion\Http\Respuesta;
use E2e\Presentacion\Http\Solicitud;

$pedir = static function (Escenario $e, string $metodo, string $ruta, string $token = 'token-pipeline', ?array $cuerpo = null, array $cabeceras = []): Respuesta {
    return $e->kernel()->manejar(new Solicitud($metodo, $ruta, $cabeceras + ['authorization' => "Bearer $token", 'x-tenant-id' => 'hosp-norte'],
        $cuerpo === null ? '' : json_encode($cuerpo)));
};

prueba('HTTP-01', 'Recorrido HTTP: versión 201, plan 200, ejecución 201, resultado con evidencia base64 201, cierre 200, gate 200, trazabilidad 200', function () use ($pedir): void {
    $e = new Escenario();
    igual(201, $pedir($e, 'POST', '/api/v1/e2e/versiones', cuerpo: ['numero' => '2.4.0', 'commit' => 'c56f169', 'digest' => Escenario::DIGEST, 'modulos' => ['admision']])->estado, 'versión');
    $todos = ['E2E-101', 'E2E-102', 'E2E-103', 'E2E-104', 'E2E-105', 'E2E-106'];
    igual(200, $pedir($e, 'POST', '/api/v1/e2e/versiones/2.4.0/plan', 'token-ing-qa', ['escenarios' => $todos])->estado, 'plan');
    $ej = $pedir($e, 'POST', '/api/v1/e2e/versiones/2.4.0/ejecuciones', cuerpo: ['digest' => Escenario::DIGEST, 'entorno' => 'staging']);
    igual(201, $ej->estado, 'ejecución');
    $id = $ej->cuerpo['id'];
    foreach ($todos as $i => $esc) {
        $r = $pedir($e, 'POST', "/api/v1/e2e/ejecuciones/$id/resultados", cuerpo: ['escenario' => $esc, 'resultado' => 'PASA', 'intentos' => 1,
            'evidencias' => [['tipo' => 'junit', 'contenido_base64' => base64_encode("<testcase name=\"$esc\"/>")]]], cabeceras: ['idempotency-key' => "http-clave-$i"]);
        igual(201, $r->estado, "resultado $esc");
    }
    igual(200, $pedir($e, 'POST', "/api/v1/e2e/ejecuciones/$id/cierre")->estado, 'cierre');
    $g = $pedir($e, 'POST', '/api/v1/e2e/versiones/2.4.0/gate', 'token-release', ['ejecucion' => $id]);
    igual([200, 'APROBADO'], [$g->estado, $g->cuerpo['decision']], 'gate');
    igual([200, 'APROBADA'], [($t = $pedir($e, 'GET', '/api/v1/e2e/versiones/2.4.0/trazabilidad', 'token-dev'))->estado, $t->cuerpo['estado']], 'trazabilidad');
});

prueba('HTTP-02', 'Autenticación, tenant y forma: sin token 401, sin X-Tenant-ID 400, tenant ajeno 403, JSON roto 400, base64 roto 400, sin Idempotency-Key 400, ruta 404', function () use ($pedir): void {
    $e = new Escenario();
    $k = $e->kernel();
    igual([401, 400, 403], [$k->manejar(new Solicitud('GET', '/api/v1/e2e/versiones/2.4.0/trazabilidad'))->estado,
        $k->manejar(new Solicitud('GET', '/api/v1/e2e/versiones/2.4.0/trazabilidad', ['authorization' => 'Bearer token-dev']))->estado,
        $k->manejar(new Solicitud('GET', '/api/v1/e2e/versiones/2.4.0/trazabilidad', ['authorization' => 'Bearer token-dev', 'x-tenant-id' => 'hosp-sur']))->estado], 'identidad');
    $roto = $k->manejar(new Solicitud('POST', '/api/v1/e2e/versiones', ['authorization' => 'Bearer token-pipeline', 'x-tenant-id' => 'hosp-norte'], '{"numero":'));
    igual([400, 400, 400, 404], [$roto->estado,
        $pedir($e, 'POST', '/api/v1/e2e/ejecuciones/1/resultados', cuerpo: ['evidencias' => [['tipo' => 'log', 'contenido_base64' => '%%no%%']]], cabeceras: ['idempotency-key' => 'clave-00001'])->estado,
        $pedir($e, 'POST', '/api/v1/e2e/ejecuciones/1/resultados', cuerpo: [])->estado, $pedir($e, 'GET', '/api/v1/e2e/nada')->estado], 'forma');
});

prueba('HTTP-03', 'Traducción de la salida: 422 regla de datos, 409 estado, 413 evidencia grande, 503 almacén caído, 403 sin permiso', function () use ($pedir): void {
    $e = new Escenario();
    $id = $e->ejecucionEnCurso();
    $res = static fn (array $c, string $k) => $pedir($e, 'POST', "/api/v1/e2e/ejecuciones/$id/resultados", cuerpo: $c, cabeceras: ['idempotency-key' => $k])->estado;
    $grande = ['escenario' => 'E2E-104', 'resultado' => 'FALLA', 'intentos' => 1, 'evidencias' => [['tipo' => 'video', 'contenido_base64' => base64_encode(str_repeat('v', 20 * 1024 * 1024 + 1))]]];
    $estados = [
        $pedir($e, 'POST', '/api/v1/e2e/escenarios', 'token-ing-qa', ['id' => 'E2E-300', 'titulo' => 'x', 'modulo' => 'citas', 'critico' => false, 'requisitos' => ['RF-01'], 'datos' => 'paciente-real'])->estado,
        $res(['escenario' => 'E2E-999', 'resultado' => 'PASA', 'intentos' => 1], 'clave-http-03a'),
        $res($grande, 'clave-http-03b'),
        $pedir($e, 'POST', '/api/v1/e2e/versiones/2.4.0/gate', 'token-pipeline', ['ejecucion' => $id])->estado,
    ];
    $e->almacen->caido = true;
    $estados[] = $res(['escenario' => 'E2E-104', 'resultado' => 'FALLA', 'intentos' => 1, 'evidencias' => [['tipo' => 'log', 'contenido_base64' => base64_encode('500')]]], 'clave-http-03c');
    igual([422, 409, 413, 403, 503], $estados, 'estados');
});
