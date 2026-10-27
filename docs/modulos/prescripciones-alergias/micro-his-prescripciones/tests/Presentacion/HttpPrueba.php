<?php

declare(strict_types=1);

use Prescripciones\Presentacion\Http\Solicitud;

$cuerpoEmitir = static fn (string $paciente = 'P-005', array $extra = []): array => ['paciente' => $paciente, 'items' => [Escenario::item('PARA500')]] + $extra;

prueba('HTTP-01', 'POST /prescripciones: 201 con Location; el reintento con la misma Idempotency-Key da 200 y el mismo id', function () use ($cuerpoEmitir): void {
    $k = (new Escenario())->kernel();
    $r = $k->manejar(Escenario::http('POST', '/api/v1/prescripciones', $cuerpoEmitir(), cabeceras: ['idempotency-key' => 'abc-1']));
    igual([201, 'EMITIDA', '/api/v1/prescripciones/1'], [$r->estado, $r->cuerpo['codigo'], $r->cabeceras['Location'] ?? null], 'primera');
    $otra = $k->manejar(Escenario::http('POST', '/api/v1/prescripciones', $cuerpoEmitir(), cabeceras: ['idempotency-key' => 'abc-1']));
    igual([200, 'YA_EMITIDA', 1], [$otra->estado, $otra->cuerpo['codigo'], $otra->cuerpo['id']], 'reintento');
});

prueba('HTTP-02', 'Códigos de las reglas: 422 bloqueo, 409 justificación, 422 confirmación, 404 paciente ajeno', function () use ($cuerpoEmitir): void {
    $k = (new Escenario())->kernel();
    $emitir = static fn (array $cuerpo, string $clave) => $k->manejar(Escenario::http('POST', '/api/v1/prescripciones', $cuerpo, cabeceras: ['idempotency-key' => $clave]));
    $bloqueo = $emitir(['paciente' => 'P-001', 'items' => [Escenario::item('AMOX500')]], 'k1');
    $advertencia = $emitir(['paciente' => 'P-002', 'items' => [Escenario::item('CEFA500')]], 'k2');
    $confirmacion = $emitir(['paciente' => 'P-003', 'items' => [Escenario::item('AMOX500')]], 'k3');
    $ajeno = $emitir($cuerpoEmitir('P-900'), 'k4');
    igual([[422, 'BLOQUEO_ALERGIA'], [409, 'JUSTIFICACION_REQUERIDA'], [422, 'CONFIRMACION_REQUERIDA'], [404, 'NO_ENCONTRADO']],
        array_map(static fn ($r) => [$r->estado, $r->cuerpo['error']], [$bloqueo, $advertencia, $confirmacion, $ajeno]), 'respuestas');
    afirmar(isset($bloqueo->cuerpo['hallazgos'][0]['alergeno']), 'el bloqueo informa el alérgeno');
});

prueba('HTTP-03', 'Fuente de alergias caída: 503 VERIFICACION_NO_DISPONIBLE', function () use ($cuerpoEmitir): void {
    $k = (new Escenario(new FuenteAlergiasCaida()))->kernel();
    $r = $k->manejar(Escenario::http('POST', '/api/v1/prescripciones', $cuerpoEmitir('P-005', ['confirma_sin_alergias' => true]), cabeceras: ['idempotency-key' => 'k']));
    igual([503, 'VERIFICACION_NO_DISPONIBLE'], [$r->estado, $r->cuerpo['error']], 'respuesta');
});

prueba('HTTP-04', 'Autenticación y tenant: 401 sin token, 400 sin X-Tenant-ID, 403 si no coincide con el token', function () use ($cuerpoEmitir): void {
    $k = (new Escenario())->kernel();
    $sinToken = $k->manejar(Escenario::http('GET', '/api/v1/prescripciones/1', token: 'falso'));
    $sinTenant = $k->manejar(Escenario::http('GET', '/api/v1/prescripciones/1', tenant: null));
    $otroTenant = $k->manejar(Escenario::http('GET', '/api/v1/prescripciones/1', tenant: 'hosp-sur'));
    igual([401, 400, 403], [$sinToken->estado, $sinTenant->estado, $otroTenant->estado], 'estados');
    igual(['NO_AUTENTICADO', 'TENANT_REQUERIDO', 'TENANT_NO_COINCIDE'], [$sinToken->cuerpo['error'], $sinTenant->cuerpo['error'], $otroTenant->cuerpo['error']], 'errores');
});

prueba('HTTP-05', 'Entrada mal formada: sin Idempotency-Key, JSON inválido, cuerpo sin paciente o con tipos erróneos → 400', function () use ($cuerpoEmitir): void {
    $k = (new Escenario())->kernel();
    $sinClave = $k->manejar(Escenario::http('POST', '/api/v1/prescripciones', $cuerpoEmitir()));
    $jsonMalo = $k->manejar(new Solicitud('POST', '/api/v1/prescripciones', ['authorization' => 'Bearer token-dr-lopez', 'x-tenant-id' => 'hosp-norte',
        'idempotency-key' => 'k'], '{"paciente":'));
    $sinPaciente = $k->manejar(Escenario::http('POST', '/api/v1/prescripciones', ['items' => []], cabeceras: ['idempotency-key' => 'k']));
    $tipos = $k->manejar(Escenario::http('POST', '/api/v1/prescripciones', $cuerpoEmitir('P-005', ['confirma_sin_alergias' => 'sí']), cabeceras: ['idempotency-key' => 'k']));
    igual([[400, 'IDEMPOTENCY_KEY_REQUERIDA'], [400, 'JSON_INVALIDO'], [400, 'CUERPO_INVALIDO'], [400, 'CUERPO_INVALIDO']],
        array_map(static fn ($r) => [$r->estado, $r->cuerpo['error']], [$sinClave, $jsonMalo, $sinPaciente, $tipos]), 'respuestas');
});

prueba('HTTP-06', 'Rutas: 404 ruta desconocida, 405 método no permitido, id no numérico → 404', function (): void {
    $k = (new Escenario())->kernel();
    igual([404, 405, 404], [
        $k->manejar(Escenario::http('GET', '/api/v1/otra'))->estado,
        $k->manejar(Escenario::http('DELETE', '/api/v1/prescripciones/1'))->estado,
        $k->manejar(Escenario::http('GET', '/api/v1/prescripciones/abc'))->estado,
    ], 'estados');
});

prueba('HTTP-07', 'GET /prescripciones/{id}: farmacia ve la verificación; otro hospital recibe 404', function () use ($cuerpoEmitir): void {
    $k = (new Escenario())->kernel();
    $k->manejar(Escenario::http('POST', '/api/v1/prescripciones', ['paciente' => 'P-002', 'items' => [Escenario::item('CEFA500')],
        'justificacion' => JUSTIFICACION_VALIDA], cabeceras: ['idempotency-key' => 'k']));
    $farmacia = $k->manejar(Escenario::http('GET', '/api/v1/prescripciones/1', token: 'token-farmacia-norte'));
    $sur = $k->manejar(Escenario::http('GET', '/api/v1/prescripciones/1', token: 'token-dr-sur', tenant: 'hosp-sur'));
    igual([200, 'ADVERTENCIA', JUSTIFICACION_VALIDA], [$farmacia->estado, $farmacia->cuerpo['verificacion']['resultado'],
        $farmacia->cuerpo['verificacion']['justificacion']], 'farmacia');
    igual(404, $sur->estado, 'otro hospital');
});

prueba('HTTP-08', 'POST /verificaciones: 200 con los hallazgos y sin efectos', function (): void {
    $e = new Escenario();
    $r = $e->kernel()->manejar(Escenario::http('POST', '/api/v1/prescripciones/verificaciones', ['paciente' => 'P-001', 'items' => [Escenario::item('AMOX500')]]));
    igual([200, 'BLOQUEO', 'J01CA04'], [$r->estado, $r->cuerpo['codigo'], $r->cuerpo['hallazgos'][0]['alergeno']], 'respuesta');
    igual([0, []], [count($e->repositorio->guardadas), $e->auditoria->acciones()], 'sin efectos');
});

prueba('HTTP-10', 'H-1: cada código que responde el kernel tiene su frase de estado (el servidor embebido no conoce la del 422)', function (): void {
    foreach ([200, 201, 400, 401, 403, 404, 405, 409, 422, 500, 503] as $codigo) {
        afirmar(!str_ends_with((new \Prescripciones\Presentacion\Http\Respuesta($codigo, []))->lineaDeEstado(), 'Status'), "sin frase para $codigo");
    }
    igual('HTTP/1.1 422 Unprocessable Content', (new \Prescripciones\Presentacion\Http\Respuesta(422, []))->lineaDeEstado(), '422');
});

prueba('HTTP-09','Una excepción inesperada se responde 500 sin detalles', function (): void {
    $kernel = new \Prescripciones\Presentacion\Http\Kernel([new \Prescripciones\Presentacion\Http\Ruta('GET', '#^/x$#',
        static fn () => throw new \LogicException('detalle interno /var/www'))],
        new \Prescripciones\Presentacion\Http\Autenticacion\AutenticadorTokenDemo(['t' => ['u', 'h']]));
    $r = $kernel->manejar(new Solicitud('GET', '/x', ['authorization' => 'Bearer t', 'x-tenant-id' => 'h']));
    igual([500, ['error' => 'ERROR_INTERNO']], [$r->estado, $r->cuerpo], 'respuesta');
});
