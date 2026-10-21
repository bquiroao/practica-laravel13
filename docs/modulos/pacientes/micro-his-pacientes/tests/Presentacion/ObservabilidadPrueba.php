<?php

declare(strict_types=1);

use Pacientes\Pruebas\Dobles\Escenario;
use Pacientes\Pruebas\Dobles\RegistroAccesoEnMemoria;

prueba('OBS-01', 'X-Request-ID: se respeta el válido, se genera si falta y se reemplaza si es peligroso', function (): void {
    $e = Escenario::crear();
    $r = $e->kernel->manejar(Escenario::http('GET', '/api/v1/pacientes', 'token-clinico-oriente', consulta: ['nombre' => 'lopez'], cabeceras: ['X-Request-ID' => 'traza-admision-0001']));
    igual('traza-admision-0001', $r->cabeceras['X-Request-ID'] ?? null, 'respetado');
    $generado = $e->kernel->manejar(Escenario::http('GET', '/api/v1/pacientes', null))->cabeceras['X-Request-ID'] ?? '';
    afirmar(preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $generado) === 1, "UUID v4 generado incluso en el 401: $generado");
    $peligroso = $e->kernel->manejar(Escenario::http('GET', '/api/v1/pacientes', cabeceras: ['X-Request-ID' => '<script>alert(1)</script>']))->cabeceras['X-Request-ID'] ?? '';
    afirmar(!str_contains($peligroso, '<'), 'se reflejó un X-Request-ID peligroso');
});

prueba('OBS-02', 'Registro de acceso: una línea por petición, sin token, sin query (nombres), sin cuerpo ni documento', function (): void {
    $registro = new RegistroAccesoEnMemoria();
    $e = Escenario::crear($registro);
    $e->kernel->manejar(Escenario::http('POST', '/api/v1/pacientes', cuerpo: Escenario::json(Escenario::datosAna()), cabeceras: ['X-Request-ID' => 'traza-registro-0001']));
    $e->kernel->manejar(Escenario::http('GET', '/api/v1/pacientes', 'token-clinico-oriente', consulta: ['nombre' => 'lopez']));
    $e->kernel->manejar(Escenario::http('GET', '/api/v1/pacientes', null));
    igual(3, count($registro->lineas), 'líneas');
    igual(['request_id', 'metodo', 'ruta', 'estado', 'duracion_ms', 'actor', 'tenant'], array_keys($registro->lineas[0]), 'campos');
    igual(['traza-registro-0001', 'POST', '/api/v1/pacientes', 201, Escenario::ADMISION, Escenario::H_ORIENTE],
        [$registro->lineas[0]['request_id'], $registro->lineas[0]['metodo'], $registro->lineas[0]['ruta'], $registro->lineas[0]['estado'],
            $registro->lineas[0]['actor'], $registro->lineas[0]['tenant']], 'línea del registro');
    igual([401, null], [$registro->lineas[2]['estado'], $registro->lineas[2]['actor']], 'petición sin identidad');
    $todo = (string) json_encode($registro->lineas);
    foreach (['token-', 'lopez', Escenario::DPI_ANA, 'Ana'] as $prohibido) {
        afirmar(!str_contains($todo, $prohibido), "el registro de acceso contiene «$prohibido»");
    }
});

prueba('OBS-03', 'Si el registro de acceso falla, la respuesta al usuario no cambia', function (): void {
    $registro = new RegistroAccesoEnMemoria();
    $registro->fallar = true;
    $e = Escenario::crear($registro);
    $r = $e->kernel->manejar(Escenario::http('POST', '/api/v1/pacientes', cuerpo: Escenario::json(Escenario::datosAna())));
    igual([201, 'P-000001'], [$r->estado, $r->cuerpo['codigo'] ?? null], 'respuesta');
});

prueba('HTTP-09', 'GET condicional: If-None-Match con la versión vigente → 304 sin cuerpo; con otra → 200', function (): void {
    $e = Escenario::crear();
    Escenario::registrar($e, Escenario::datosAna());
    $get = static fn (string $etag) => $e->kernel->manejar(Escenario::http('GET', '/api/v1/pacientes/P-000001', 'token-clinico-oriente', cabeceras: ['If-None-Match' => $etag]));
    igual([304, [], '"1"'], [($r = $get('"1"'))->estado, $r->cuerpo, $r->cabeceras['ETag'] ?? null], 'vigente');
    igual(304, $get('W/"1", "9"')->estado, 'lista con débil');
    igual(200, $get('"0"')->estado, 'otra versión');
});
