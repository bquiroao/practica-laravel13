<?php

declare(strict_types=1);

/*
 * Semana 15 · Pruebas de CAJA NEGRA: diseñadas SOLO desde el contrato (api/openapi.yaml 1.2.0) y los requisitos,
 * sin mirar el código. Entran por HTTP con el kernel real. Los módulos ajenos (Expediente, RBAC, identidad) se simulan
 * con los adaptadores de demostración, como pide la tarea.
 */

use Prescripciones\Presentacion\Http\Solicitud;

const CN_JUSTIFICACION = 'Sin alternativa: cultivo sensible solo a cefalosporinas';

$cnItem = static fn (array $cambios = []): array => $cambios + ['medicamento' => 'PARA500', 'dosis' => 500, 'unidad' => 'mg', 'frecuencia_horas' => 8, 'via' => 'oral', 'dias' => 5];
$cnPrevia = static fn ($k, array $items, string $paciente = 'P-005', string $token = 'token-dr-lopez')
    => $k->manejar(Escenario::http('POST', '/api/v1/prescripciones/verificaciones', ['paciente' => $paciente, 'items' => $items], $token));
$cnEmitir = static fn ($k, array $cuerpo, string $clave, string $token = 'token-dr-lopez')
    => $k->manejar(Escenario::http('POST', '/api/v1/prescripciones', $cuerpo, $token, cabeceras: ['idempotency-key' => $clave]));
$cnRes = static fn ($r): string => $r->estado . ' ' . ($r->cuerpo['error'] ?? $r->cuerpo['codigo'] ?? '');

prueba('CN-01', 'Particiones de equivalencia · frecuencia_horas: las 5 clases válidas (4, 6, 8, 12, 24) dan 200; las inválidas (0, 5, 48, "8", null) dan 422 en ese campo', function () use ($cnItem, $cnPrevia): void {
    $k = (new Escenario())->kernel();
    $validas = array_map(static fn (int $f): int => $cnPrevia($k, [$cnItem(['frecuencia_horas' => $f])])->estado, [4, 6, 8, 12, 24]);
    igual([200, 200, 200, 200, 200], $validas, 'válidas');
    foreach ([0, 5, 48, '8', null] as $f) {
        $r = $cnPrevia($k, [$cnItem(['frecuencia_horas' => $f])]);
        igual([422, 'DATOS_INVALIDOS', ['items[0].frecuencia_horas']], [$r->estado, $r->cuerpo['error'] ?? null, $r->cuerpo['campos'] ?? null], 'inválida ' . var_export($f, true));
    }
});

prueba('CN-02', 'Valores límite · dosis diaria de Paracetamol (máx. 4000 mg): 1000 c/6 h = 4000 → 200; 1000,5 → 422 DOSIS; 0 y −1 → 422 DATOS; 0,001 → 200', function () use ($cnItem, $cnPrevia, $cnRes): void {
    $k = (new Escenario())->kernel();
    $casos = [[1000, '200 SIN_HALLAZGOS'], [1000.5, '422 DOSIS_EXCEDE_MAXIMO'], [0, '422 DATOS_INVALIDOS'], [-1, '422 DATOS_INVALIDOS'], [0.001, '200 SIN_HALLAZGOS']];
    foreach ($casos as [$dosis, $esperado]) {
        igual($esperado, $cnRes($cnPrevia($k, [$cnItem(['dosis' => $dosis, 'frecuencia_horas' => 6])])), "dosis $dosis c/6 h");
    }
    // El límite que se repite en dos ítems del mismo medicamento (8000 mg/día) se registra como DEF-08: ver tests/Defectos.
});

prueba('CN-03', 'Valores límite · días 1 y 90 sí, 0 y 91 no; justificación 19/20 caracteres sin contar espacios; motivo de anulación 9/10', function () use ($cnItem, $cnPrevia, $cnEmitir, $cnRes): void {
    $k = (new Escenario())->kernel();
    igual(['200', '200', '422', '422'], array_map(static fn (int $d): string => (string) $cnPrevia($k, [$cnItem(['dias' => $d])])->estado, [1, 90, 0, 91]), 'días');
    $cefa = [$cnItem(['medicamento' => 'CEFA500'])];
    igual('409 JUSTIFICACION_REQUERIDA', $cnRes($cnEmitir($k, ['paciente' => 'P-002', 'items' => $cefa, 'justificacion' => '   ' . str_repeat('j', 19) . '   '], 'cn3-a')), '19 con espacios');
    $ok = $cnEmitir($k, ['paciente' => 'P-002', 'items' => $cefa, 'justificacion' => str_repeat('j', 20)], 'cn3-b');
    igual('201 EMITIDA', $cnRes($ok), '20');
    $id = $ok->cuerpo['id'];
    igual('422 MOTIVO_REQUERIDO', $cnRes($k->manejar(Escenario::http('POST', "/api/v1/prescripciones/$id/anulacion", ['motivo' => ' ' . str_repeat('m', 9) . ' ']))), 'motivo de 9');
    igual('200 ANULADA', $cnRes($k->manejar(Escenario::http('POST', "/api/v1/prescripciones/$id/anulacion", ['motivo' => str_repeat('m', 10)]))), 'motivo de 10');
});

prueba('CN-04', 'Permisos · tabla de decisión: 3 roles × 5 operaciones (verificar, emitir, emitir con advertencia justificada, consultar, anular)', function () use ($cnItem, $cnPrevia, $cnEmitir): void {
    $esperado = [
        'token-dr-lopez' => [200, 201, 201, 200, 200],        // emitir + override + consultar + anular
        'token-dr-ruiz' => [200, 201, 409, 200, 200],         // sin override: la advertencia justificada da 409
        'token-farmacia-norte' => [403, 403, 403, 200, 403],  // solo consultar
    ];
    $obtenido = [];
    foreach (array_keys($esperado) as $n => $token) {
        $k = (new Escenario())->kernel();
        $id = $cnEmitir($k, ['paciente' => 'P-005', 'items' => [$cnItem()]], "base-$n")->cuerpo['id'];
        $obtenido[$token] = [
            $cnPrevia($k, [$cnItem()], token: $token)->estado,
            $cnEmitir($k, ['paciente' => 'P-005', 'items' => [$cnItem(['dias' => 3])]], "e-$n", $token)->estado,
            $cnEmitir($k, ['paciente' => 'P-002', 'items' => [$cnItem(['medicamento' => 'CEFA500'])], 'justificacion' => CN_JUSTIFICACION], "a-$n", $token)->estado,
            $k->manejar(Escenario::http('GET', "/api/v1/prescripciones/$id", token: $token))->estado,
            $k->manejar(Escenario::http('POST', "/api/v1/prescripciones/$id/anulacion", ['motivo' => 'Pauta duplicada con la de urgencias'], $token))->estado,
        ];
    }
    igual($esperado, $obtenido, 'tabla de decisión');
});

prueba('CN-05', 'Permisos / tenant · sin X-Tenant-ID o vacío → 400; de otro hospital → 403; una prescripción o un paciente de otro hospital = inexistente (404 idéntico)', function () use ($cnItem, $cnPrevia, $cnEmitir, $cnRes): void {
    $k = (new Escenario())->kernel();
    $id = $cnEmitir($k, ['paciente' => 'P-005', 'items' => [$cnItem()]], 'cn5')->cuerpo['id'];
    igual('400 TENANT_REQUERIDO', $cnRes($k->manejar(Escenario::http('GET', "/api/v1/prescripciones/$id", tenant: null))), 'sin cabecera');
    igual('400 TENANT_REQUERIDO', $cnRes($k->manejar(Escenario::http('GET', "/api/v1/prescripciones/$id", tenant: ''))), 'cabecera vacía');
    igual('403 TENANT_NO_COINCIDE', $cnRes($k->manejar(Escenario::http('GET', "/api/v1/prescripciones/$id", tenant: 'hosp-sur'))), 'tenant ajeno');
    $ajena = $k->manejar(Escenario::http('GET', "/api/v1/prescripciones/$id", token: 'token-dr-sur', tenant: 'hosp-sur'));
    $inexistente = $k->manejar(Escenario::http('GET', '/api/v1/prescripciones/999', token: 'token-dr-sur', tenant: 'hosp-sur'));
    igual([404, ['error' => 'NO_ENCONTRADO']], [$ajena->estado, $ajena->cuerpo], 'de otro hospital');
    igual([$inexistente->estado, $inexistente->cuerpo], [$ajena->estado, $ajena->cuerpo], 'indistinguible de una inexistente');
    igual('404 NO_ENCONTRADO', $cnRes($cnPrevia($k, [$cnItem()], 'P-900')), 'paciente de otro hospital');
});

prueba('CN-06', 'Errores · JSON roto, cuerpo sin paciente, Idempotency-Key ausente/inválida/129, PUT y DELETE, ruta e id desconocidos, sin token y Expediente caído', function () use ($cnItem, $cnEmitir, $cnRes): void {
    $fuente = new FuenteConmutable(Escenario::fuenteContada());
    $k = (new Escenario($fuente))->kernel();
    $base = ['authorization' => 'Bearer token-dr-lopez', 'x-tenant-id' => 'hosp-norte'];
    $crudo = static fn (string $metodo, string $ruta, string $cuerpo, array $extra = []) => $k->manejar(new Solicitud($metodo, $ruta, $extra + $base, $cuerpo));
    $ok = ['paciente' => 'P-005', 'items' => [$cnItem()]];
    igual('400 JSON_INVALIDO', $cnRes($crudo('POST', '/api/v1/prescripciones/verificaciones', '{"paciente": ')), 'JSON roto');
    igual('400 CUERPO_INVALIDO', $cnRes($crudo('POST', '/api/v1/prescripciones/verificaciones', '{"items": []}')), 'sin paciente');
    igual('400 IDEMPOTENCY_KEY_REQUERIDA', $cnRes($crudo('POST', '/api/v1/prescripciones', (string) json_encode($ok))), 'sin Idempotency-Key');
    igual('400 IDEMPOTENCY_KEY_REQUERIDA', $cnRes($cnEmitir($k, $ok, 'con espacio')), 'clave fuera del patrón');
    igual('400 IDEMPOTENCY_KEY_REQUERIDA', $cnRes($cnEmitir($k, $ok, str_repeat('k', 129))), 'clave de 129');
    igual('201 EMITIDA', $cnRes($cnEmitir($k, $ok, str_repeat('k', 128))), 'clave de 128');
    igual('405 METODO_NO_PERMITIDO', $cnRes($crudo('PUT', '/api/v1/prescripciones/1', '{}')), 'PUT');
    igual('405 METODO_NO_PERMITIDO', $cnRes($crudo('DELETE', '/api/v1/prescripciones/1', '')), 'DELETE');
    igual('404 RUTA_NO_ENCONTRADA', $cnRes($crudo('GET', '/api/v1/prescripciones/abc', '')), 'id no numérico');
    igual('404 RUTA_NO_ENCONTRADA', $cnRes($crudo('GET', '/api/v1/prescripciones/1/historial', '')), 'ruta desconocida');
    $sinToken = $k->manejar(Escenario::http('GET', '/api/v1/prescripciones/1', token: ''));
    igual(['401 NO_AUTENTICADO', 'Bearer'], [$cnRes($sinToken), $sinToken->cabeceras['WWW-Authenticate'] ?? null], 'sin token');
    $fuente->caida = true;
    $caido = $cnEmitir($k, ['paciente' => 'P-005', 'items' => [$cnItem(['dias' => 2])]], 'cn6-caido');
    igual([503, ['error' => 'VERIFICACION_NO_DISPONIBLE']], [$caido->estado, $caido->cuerpo], 'Expediente caído: 503 sin detalles internos');
});

prueba('CN-07', 'Idempotencia según el contrato · 201 con Location; la misma clave y contenido → 200 YA_EMITIDA, mismo id, sin Location y SIN volver a verificar; otro contenido → 409', function () use ($cnItem, $cnEmitir, $cnRes): void {
    $fuente = new FuenteConmutable(Escenario::fuenteContada());
    $k = (new Escenario($fuente))->kernel();
    $cuerpo = ['paciente' => 'P-005', 'items' => [$cnItem()]];
    $a = $cnEmitir($k, $cuerpo, 'cn7');
    igual(['201 EMITIDA', '/api/v1/prescripciones/' . $a->cuerpo['id']], [$cnRes($a), $a->cabeceras['Location'] ?? null], 'primera');
    $fuente->caida = true; // «sin volver a verificar»: aunque el Expediente esté caído, el reintento responde
    $b = $cnEmitir($k, $cuerpo, 'cn7');
    igual(['200 YA_EMITIDA', $a->cuerpo['id'], false], [$cnRes($b), $b->cuerpo['id'] ?? null, isset($b->cabeceras['Location'])], 'reintento');
    $fuente->caida = false;
    igual('409 CLAVE_REUTILIZADA', $cnRes($cnEmitir($k, ['paciente' => 'P-005', 'items' => [$cnItem(['dias' => 9])]], 'cn7')), 'otro contenido');
});
