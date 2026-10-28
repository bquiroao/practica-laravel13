<?php

declare(strict_types=1);

/*
 * Semana 15 — Pruebas de CAJA NEGRA: se diseñan SOLO desde el contrato (api/openapi.yaml) y los requisitos, sin mirar el código.
 * Todas entran por HTTP (kernel real, adaptadores InMemory) con las técnicas pedidas: particiones de equivalencia, valores
 * límite, permisos (tabla de decisión) y errores. RBAC y la identidad del HIS se simulan con la configuración de
 * demostración: no se prueba ningún módulo ajeno.
 */

use Auditoria\Pruebas\Dobles\Escenario;

const CN_DIA = ['desde' => '2026-10-20', 'hasta' => '2026-10-20'];
const CN_BASE = '/api/v1/auditoria';

$cnHttp = static fn (object $e, string $metodo, string $ruta, ?string $token, string $hospital = Escenario::H_ORIENTE, ?array $cuerpo = null, array $consulta = []) =>
    $e->kernel->manejar(Escenario::http($metodo, CN_BASE . $ruta, $token, $hospital, $cuerpo, $consulta));
$cnRegistrar = static fn (object $e, array $cambios, string $token = 'token-svc-pacientes-oriente', string $hospital = Escenario::H_ORIENTE) =>
    $e->kernel->manejar(Escenario::http('POST', CN_BASE . '/movimientos', $token, $hospital, Escenario::mov($cambios)));
$cnConsultar = static fn (object $e, array $consulta, string $token = 'token-auditor-oriente', string $hospital = Escenario::H_ORIENTE) =>
    $e->kernel->manejar(Escenario::http('GET', CN_BASE . '/movimientos', $token, $hospital, null, $consulta));
$cnRes = static fn ($r): array => [$r->estado, $r->cuerpo['error'] ?? null, isset($r->cuerpo['campos']) ? array_keys($r->cuerpo['campos']) : null];

prueba('CN-01', 'Particiones de equivalencia del filtro «resultado»: 3 clases válidas → 200 con solo esa clase; mayúsculas, sinónimo y vacío → 422', function () use ($cnRegistrar, $cnConsultar, $cnRes): void {
    $e = Escenario::crear();
    foreach (['exito', 'denegado', 'error'] as $i => $resultado) {
        $cnRegistrar($e, ['event_id' => "r-$i", 'resultado' => $resultado, 'ocurrido_en' => '2026-10-20T10:00:00-06:00']);
    }
    foreach (['exito', 'denegado', 'error'] as $valido) {
        $r = $cnConsultar($e, CN_DIA + ['resultado' => $valido]);
        igual([200, [$valido]], [$r->estado, array_values(array_unique(array_column($r->cuerpo['movimientos'], 'resultado')))], "clase válida $valido");
    }
    foreach (['EXITO', 'ok', ''] as $invalido) {
        igual([422, 'DATOS_INVALIDOS', ['resultado']], $cnRes($cnConsultar($e, CN_DIA + ['resultado' => $invalido])), "clase inválida «{$invalido}»");
    }
});

prueba('CN-02', 'Valores límite del rango de fechas: 00:00:00 y 23:59:59 locales dentro, 00:00 del día siguiente fuera; desde = hasta; desde = hasta + 1; 29/02 y 30/02', function () use ($cnRegistrar, $cnConsultar, $cnRes): void {
    $e = Escenario::crear();
    foreach (['b-1' => '2026-10-20T00:00:00-06:00', 'b-2' => '2026-10-20T23:59:59-06:00', 'b-3' => '2026-10-21T00:00:00-06:00', 'b-4' => '2026-10-19T23:59:59-06:00'] as $ev => $cuando) {
        $cnRegistrar($e, ['event_id' => $ev, 'ocurrido_en' => $cuando]);
    }
    $r = $cnConsultar($e, CN_DIA + ['modulo' => 'pacientes']);
    igual([200, ['2026-10-21T05:59:59Z', '2026-10-20T06:00:00Z']], [$r->estado, array_column($r->cuerpo['movimientos'], 'ocurrido_en')], 'solo los dos del 20 (hora local)');
    igual([422, 'DATOS_INVALIDOS', ['desde']], $cnRes($cnConsultar($e, ['desde' => '2026-10-21', 'hasta' => '2026-10-20'])), 'desde = hasta + 1');
    igual(200, $cnConsultar($e, ['desde' => '2028-02-29', 'hasta' => '2028-02-29'])->estado, '29/02 de un año bisiesto');
    igual([422, 'DATOS_INVALIDOS', ['desde']], $cnRes($cnConsultar($e, ['desde' => '2026-02-30', 'hasta' => '2026-03-01'])), '30/02 no existe');
    igual([422, 'DATOS_INVALIDOS', ['desde']], $cnRes($cnConsultar($e, ['hasta' => '2026-10-20'])), 'falta desde');
});

prueba('CN-03', 'Valores límite de longitud: usuario 64/65 al registrar y al filtrar; recurso 128/129; detalle de 4096/4097 bytes', function () use ($cnRegistrar, $cnConsultar, $cnRes): void {
    $e = Escenario::crear();
    $casos = [
        ['usuario 64', ['event_id' => 'l-1', 'usuario' => str_repeat('u', 64)], 201],
        ['usuario 65', ['event_id' => 'l-2', 'usuario' => str_repeat('u', 65)], 422],
        ['recurso 128', ['event_id' => 'l-3', 'recurso' => str_repeat('r', 128)], 201],
        ['recurso 129', ['event_id' => 'l-4', 'recurso' => str_repeat('r', 129)], 422],
        ['detalle 4096 B', ['event_id' => 'l-5', 'detalle' => ['x' => str_repeat('a', 4088)]], 201],   // {"x":"…"} = 4088 + 8
        ['detalle 4097 B', ['event_id' => 'l-6', 'detalle' => ['x' => str_repeat('a', 4089)]], 422],
    ];
    foreach ($casos as [$nombre, $cambios, $esperado]) {
        igual($esperado, $cnRegistrar($e, $cambios)->estado, $nombre);
    }
    igual(200, $cnConsultar($e, CN_DIA + ['usuario' => str_repeat('u', 64)])->estado, 'filtro usuario 64');
    igual([422, 'DATOS_INVALIDOS', ['usuario']], $cnRes($cnConsultar($e, CN_DIA + ['usuario' => str_repeat('u', 65)])), 'filtro usuario 65');
});

prueba('CN-04', 'Permisos (tabla de decisión rol × operación): cada rol hace exactamente lo suyo; todo lo demás → 403', function () use ($cnHttp): void {
    $e = Escenario::crear();
    $cnHttp($e, 'POST', '/movimientos', 'token-svc-pacientes-oriente', Escenario::H_ORIENTE, Escenario::mov());
    $op = [
        'registrar' => static fn (string $t, string $h) => $cnHttp($e, 'POST', '/movimientos', $t, $h, Escenario::mov(['event_id' => 'p-' . bin2hex(random_bytes(3))])),
        'consultar' => static fn (string $t, string $h) => $cnHttp($e, 'GET', '/movimientos', $t, $h, null, CN_DIA),
        'detalle' => static fn (string $t, string $h) => $cnHttp($e, 'GET', '/movimientos/1', $t, $h),
        'verificar' => static fn (string $t, string $h) => $cnHttp($e, 'POST', '/verificaciones', $t, $h, ['hospital' => Escenario::H_ORIENTE]),
        'ancla' => static fn (string $t, string $h) => $cnHttp($e, 'GET', '/anclas', $t, $h),
    ];
    $tabla = [ //                                                registrar consultar detalle verificar ancla
        'emisor (svc-pacientes)' => ['token-svc-pacientes-oriente', Escenario::H_ORIENTE, [201, 403, 403, 403, 403]],
        'auditor de Oriente' => ['token-auditor-oriente', Escenario::H_ORIENTE, [403, 200, 200, 403, 403]],
        // null = no se evalúa aquí: con X-Tenant-ID de un hospital, verificar y el ancla ignoran la cabecera (DEF-15-01, grupo Defectos)
        'super admin en un hospital' => ['token-superadmin', Escenario::H_ORIENTE, [403, 200, 200, null, null]],
        'super admin en la red' => ['token-superadmin', '*', [403, 200, 200, 200, 200]],
    ];
    foreach ($tabla as $rol => [$token, $hospital, $esperados]) {
        $obtenidos = array_map(static fn (callable $f, ?int $esperado): ?int => $esperado === null ? null : $f($token, $hospital)->estado, array_values($op), $esperados);
        igual($esperados, $obtenidos, $rol . ' (' . implode(', ', array_keys($op)) . ')');
    }
});

prueba('CN-05', 'Tenant: sin X-Tenant-ID → 400; tenant ajeno al token → 403; «*» solo con token de red; un detalle de otro hospital = uno inexistente', function () use ($cnHttp, $cnRes): void {
    $e = Escenario::crear();
    $cnHttp($e, 'POST', '/movimientos', 'token-svc-pacientes-norte', Escenario::H_NORTE, Escenario::mov(['event_id' => 'n-1', 'hospital' => Escenario::H_NORTE]));
    igual([400, 'HOSPITAL_REQUERIDO', null], $cnRes($cnHttp($e, 'GET', '/movimientos', 'token-auditor-oriente', '', null, CN_DIA)), 'sin cabecera');
    igual([403, 'TENANT_NO_COINCIDE', null], $cnRes($cnHttp($e, 'GET', '/movimientos', 'token-auditor-oriente', Escenario::H_NORTE, null, CN_DIA)), 'tenant ajeno');
    igual([403, 'TENANT_NO_COINCIDE', null], $cnRes($cnHttp($e, 'GET', '/movimientos', 'token-auditor-oriente', '*', null, CN_DIA)), '«*» sin token de red');
    $otro = $cnHttp($e, 'GET', '/movimientos/1', 'token-auditor-oriente');
    $inexistente = $cnHttp($e, 'GET', '/movimientos/999', 'token-auditor-oriente');
    igual([404, 'MOVIMIENTO_NO_ENCONTRADO'], [$otro->estado, $otro->cuerpo['error']], 'detalle de otro hospital');
    igual([$inexistente->estado, $inexistente->cuerpo], [$otro->estado, $otro->cuerpo], 'idéntico a uno inexistente');
});

prueba('CN-06', 'Errores: cuerpo ausente → 400; parámetro repetido → 400; método no permitido → 405; ruta inexistente → 404; cursor manipulado → 422; base caída → 503 genérico', function () use ($cnHttp, $cnConsultar, $cnRes): void {
    $e = Escenario::crear();
    igual([400, 'CUERPO_INVALIDO', null], $cnRes($cnHttp($e, 'POST', '/movimientos', 'token-svc-pacientes-oriente')), 'POST sin cuerpo');
    igual(400, $e->kernel->manejar(Escenario::http('GET', CN_BASE . '/movimientos', 'token-auditor-oriente', Escenario::H_ORIENTE, null, CN_DIA + ['usuario' => ['a', 'b']]))->estado,
        'parámetro repetido (arreglo)');
    foreach (['PUT', 'PATCH', 'DELETE'] as $metodo) {
        igual([405, 'METODO_NO_PERMITIDO', null], $cnRes($cnHttp($e, $metodo, '/movimientos/1', 'token-superadmin', '*')), "$metodo (inmutable)");
    }
    igual([404, 'RUTA_NO_ENCONTRADA', null], $cnRes($cnHttp($e, 'GET', '/movimientos/abc', 'token-auditor-oriente')), 'id no numérico');
    igual([422, 'DATOS_INVALIDOS', ['cursor']], $cnRes($cnConsultar($e, CN_DIA + ['cursor' => 'no-es-un-cursor'])), 'cursor manipulado');
    $e->bitacora->fallarEn = ['buscar'];
    $caida = $cnConsultar($e, CN_DIA);
    igual([503, ['error']], [$caida->estado, array_keys($caida->cuerpo)], 'base caída: 503 sin detalles internos');
});

prueba('CN-07', 'Valores límite de la paginación: 50 movimientos → una página sin cursor; 51 → 50 + cursor, y la 2.ª página trae 1', function () use ($cnRegistrar, $cnConsultar): void {
    foreach ([50 => [50, false], 51 => [50, true]] as $n => [$filas, $hayCursor]) {
        $e = Escenario::crear();
        for ($i = 1; $i <= $n; $i++) {
            $cnRegistrar($e, ['event_id' => "g-$i", 'ocurrido_en' => sprintf('2026-10-20T%02d:%02d:00-06:00', intdiv($i, 60), $i % 60)]);
        }
        $r = $cnConsultar($e, CN_DIA + ['modulo' => 'pacientes']);
        igual([$filas, $hayCursor], [count($r->cuerpo['movimientos']), $r->cuerpo['cursor_siguiente'] !== null], "$n movimientos");
        if ($hayCursor) {
            $p2 = $cnConsultar($e, CN_DIA + ['modulo' => 'pacientes', 'cursor' => $r->cuerpo['cursor_siguiente']]);
            igual([1, null], [count($p2->cuerpo['movimientos']), $p2->cuerpo['cursor_siguiente']], '2.ª página');
        }
    }
});
