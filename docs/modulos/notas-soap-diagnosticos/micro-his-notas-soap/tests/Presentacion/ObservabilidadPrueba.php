<?php

declare(strict_types=1);

use NotasSoap\Pruebas\Dobles\Escenario;
use NotasSoap\Pruebas\Dobles\RegistroAccesoQueFalla;

prueba('OBS-01', 'X-Request-ID: se devuelve el recibido si es válido; si falta o es inválido (por ejemplo "<script>") se genera uno nuevo', function (): void {
    $k = (new Escenario())->kernel;
    igual('traza-expediente-0001', $k->manejar(Escenario::peticion([], ['x-request-id' => 'traza-expediente-0001']))->cabecera('X-Request-ID'), 'respetado');
    $generado = (string) $k->manejar(Escenario::peticion())->cabecera('X-Request-ID');
    afirmar(preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $generado) === 1, 'UUID generado');
    $malo = (string) $k->manejar(Escenario::peticion([], ['x-request-id' => '<script>alert(1)</script>']))->cabecera('X-Request-ID');
    afirmar(!str_contains($malo, '<'), 'no refleja un valor peligroso');
    afirmar($k->manejar(Escenario::peticion([], [], 'GET', '/api/v1/otra'))->cabecera('X-Request-ID') !== null, 'también en un 404');
});

prueba('OBS-02', 'Registro de acceso: una línea por petición con request_id, ruta, estado y duración; sin token ni texto clínico', function (): void {
    $e = new Escenario();
    $e->kernel->manejar(Escenario::peticion([], ['x-request-id' => 'traza-obs-0002']));
    $e->kernel->manejar(Escenario::peticion([], ['authorization' => 'Bearer token-ana']));
    $lineas = $e->registroAcceso->lineas();
    igual(2, count($lineas), 'dos líneas');
    igual(['traza-obs-0002', 'POST', '/api/v1/notas-soap', 201, 'dra-mendez', 'hosp-oriente'],
        [$lineas[0]['request_id'], $lineas[0]['metodo'], $lineas[0]['ruta'], $lineas[0]['estado'], $lineas[0]['usuario'], $lineas[0]['tenant']], 'campos');
    igual(403, $lineas[1]['estado'], 'la denegación también queda');
    afirmar(is_float($lineas[0]['duracion_ms']) && $lineas[0]['duracion_ms'] >= 0, 'duración medida');
    $texto = (string) json_encode($lineas, JSON_UNESCAPED_UNICODE);
    afirmar(!str_contains($texto, 'token-mendez') && !str_contains($texto, 'Tos productiva') && !str_contains($texto, 'J18.9'), 'sin secretos ni datos clínicos');
});

prueba('OBS-03', 'Si el registro de acceso falla, la respuesta al cliente no cambia', function (): void {
    $r = (new Escenario(registroAcceso: new RegistroAccesoQueFalla()))->kernel->manejar(Escenario::peticion());
    igual(201, $r->estado, 'estado');
    afirmar($r->cabecera('X-Request-ID') !== null, 'con X-Request-ID');
});
