<?php

declare(strict_types=1);

use E2e\Cliente\BandejaPendientes;
use E2e\Cliente\ReportadorCi;
use E2e\Cliente\TransporteHttpStream;
use E2e\Cliente\FallaTransporte;
use E2e\Presentacion\Http\Solicitud;

$armar = static function (): array {
    $e = new Escenario();
    $id = $e->ejecucionEnCurso();
    $t = new TransporteEnProceso($e->kernel());
    $archivo = sys_get_temp_dir() . '/e2e-bandeja-' . getmypid() . '-' . random_int(0, 99999) . '.jsonl';
    $esperas = new ArrayObject();
    $r = new ReportadorCi($t, 'token-pipeline', 'hosp-norte', new BandejaPendientes($archivo), 3, static function (int $ms) use ($esperas): void {
        $esperas[] = $ms;
    });

    return [$e, $id, $t, $r, $archivo, $esperas];
};
$contarResultados = static fn (Escenario $e, int $id): int => count($e->ejecuciones->real->porId('hosp-norte', $id)->resultados());

prueba('CLI-01', 'R-12: los reintentos llevan la MISMA Idempotency-Key; la red cae dos veces y al tercero registra; esperas de 200 y 400 ms', function () use ($armar, $contarResultados): void {
    [$e, $id, $t, $r, , $esperas] = $armar();
    $t->caidas = 2;
    $res = $r->reportar($id, 'E2E-101', 'PASA', 1);
    igual([ReportadorCi::CONFIRMADO, 201, 1, [200, 400]], [$res['estado'], $res['http'], $contarResultados($e, $id), $esperas->getArrayCopy()], 'resultado');
    igual(1, count(array_unique(array_map(static fn (array $p): string => $p['cabeceras']['Idempotency-Key'], $t->enviadas))), 'una sola clave en los 3 envíos');
});

prueba('CLI-02', 'Respuesta perdida: el servidor ya registró, el pipeline reintenta y recibe 200 RESULTADO_YA_REPORTADO; hay UN resultado', function () use ($armar, $contarResultados): void {
    [$e, $id, $t, $r] = $armar();
    $t->perdidas = 1;
    $res = $r->reportar($id, 'E2E-104', 'FALLA', 1, [['tipo' => 'traza', 'bytes' => Escenario::TRAZA]]);
    igual([ReportadorCi::CONFIRMADO, 200, 'RESULTADO_YA_REPORTADO', 1], [$res['estado'], $res['http'], $res['cuerpo']['codigo'], $contarResultados($e, $id)], 'resultado');
});

prueba('CLI-03', 'Sin respuesta en ningún intento → PENDIENTE en la bandeja del job; reenviarPendientes() lo registra UNA vez y vacía la bandeja', function () use ($armar, $contarResultados): void {
    [$e, $id, $t, $r, $archivo] = $armar();
    $t->caidas = 3;
    igual(ReportadorCi::PENDIENTE, $r->reportar($id, 'E2E-102', 'PASA', 1)['estado'], 'pendiente');
    igual([1, 0], [count(file($archivo)), $contarResultados($e, $id)], 'en la bandeja, nada en el servidor');
    igual([ReportadorCi::CONFIRMADO => 1, ReportadorCi::RECHAZADO => 0, ReportadorCi::PENDIENTE => 0], $r->reenviarPendientes(), 'reenvío');
    igual([false, 1], [is_file($archivo), $contarResultados($e, $id)], 'bandeja vacía y un resultado');
});

prueba('CLI-04', 'Un 4xx no se reintenta ni se encola: un escenario fuera del plan es RECHAZADO en una sola llamada', function () use ($armar): void {
    [$e, $id, $t, $r, $archivo, $esperas] = $armar();
    $res = $r->reportar($id, 'E2E-999', 'PASA', 1);
    igual([ReportadorCi::RECHAZADO, 409, 'ESCENARIO_FUERA_DEL_PLAN', 1, [], false], [$res['estado'], $res['http'], $res['cuerpo']['error'], count($t->enviadas), $esperas->getArrayCopy(), is_file($archivo)], 'rechazo');
});

prueba('CLI-05', 'Almacén caído (503): el resultado queda PENDIENTE sin nada escrito; cuando vuelve, el reenvío lo registra con su evidencia una sola vez', function () use ($armar, $contarResultados): void {
    [$e, $id, $t, $r] = $armar();
    $e->almacen->caido = true;
    igual([ReportadorCi::PENDIENTE, 503, 0], [($x = $r->reportar($id, 'E2E-104', 'FALLA', 1, [['tipo' => 'log', 'bytes' => '500 en /epicrisis']]))['estado'], $x['http'],
        $contarResultados($e, $id)], 'pendiente por 503');
    $e->almacen->caido = false;
    $r->reenviarPendientes();
    $r->reenviarPendientes();                                                       // dos veces: la bandeja ya está vacía
    $ev = $e->ejecuciones->real->porId('hosp-norte', $id)->resultados()[0]->evidencias;
    igual([1, hash('sha256', '500 en /epicrisis')], [$contarResultados($e, $id), $ev[0]->sha256], 'registrado una vez con su evidencia');
});

prueba('CLI-06', 'Clave determinística: el mismo escenario en la misma ejecución da la misma clave (un job reiniciado no duplica); otro escenario, otra', function (): void {
    igual([true, false], [ReportadorCi::clave(7, 'E2E-101') === ReportadorCi::clave(7, 'E2E-101'), ReportadorCi::clave(7, 'E2E-101') === ReportadorCi::clave(7, 'E2E-102')], 'claves');
    afirmar(preg_match('/^[A-Za-z0-9._-]{8,80}$/', ReportadorCi::clave(7, 'E2E-101')) === 1, 'cumple el patrón del contrato');
});

prueba('CLI-07', 'TransporteHttpStream contra un puerto cerrado: FallaTransporte en menos de 5 s', function (): void {
    $inicio = microtime(true);
    lanza(FallaTransporte::class, static fn () => (new TransporteHttpStream('http://127.0.0.1:1', 2.0))->enviar('GET', '/', [], ''), 'puerto cerrado');
    afirmar(microtime(true) - $inicio < 5, 'tardó demasiado');
});

prueba('OBS-01', 'X-Request-ID en TODA respuesta (201, 401, 404): se respeta el válido del cliente y se reemplaza el inseguro', function (): void {
    $e = new Escenario();
    $k = $e->kernel();
    $c = ['authorization' => 'Bearer token-pipeline', 'x-tenant-id' => 'hosp-norte'];
    $ids = [$k->manejar(new Solicitud('POST', '/api/v1/e2e/versiones', $c + ['x-request-id' => 'ci-run-42-paso-3'], json_encode(['numero' => '2.4.0', 'commit' => 'c56f169',
        'digest' => Escenario::DIGEST, 'modulos' => ['admision']])))->cabeceras['X-Request-ID'], $k->manejar(new Solicitud('GET', '/api/v1/e2e/nada'))->cabeceras['X-Request-ID'],
        $k->manejar(new Solicitud('GET', '/api/v1/e2e/nada', $c + ['x-request-id' => "x\r\nSet-Cookie: a"]))->cabeceras['X-Request-ID']];
    igual('ci-run-42-paso-3', $ids[0], 'respetado');
    afirmar(preg_match('/^[0-9a-f]{16}$/', $ids[1]) === 1 && preg_match('/^[0-9a-f]{16}$/', $ids[2]) === 1, 'generados y reemplazado el inseguro');
});

prueba('OBS-02', 'Registro de acceso: una línea JSON por petición con request_id, estado, código, ms, usuario y tenant; sin token, cuerpo ni artefactos', function (): void {
    $e = new Escenario();
    $id = $e->ejecucionEnCurso();
    $e->kernel()->manejar(new Solicitud('POST', "/api/v1/e2e/ejecuciones/$id/resultados", ['authorization' => 'Bearer token-pipeline', 'x-tenant-id' => 'hosp-norte',
        'idempotency-key' => 'obs-02-clave-1'], json_encode(['escenario' => 'E2E-104', 'resultado' => 'FALLA', 'intentos' => 1,
        'evidencias' => [['tipo' => 'captura', 'contenido_base64' => base64_encode('pantalla de SINT-0042')]]])));
    $linea = json_decode($e->accesos[0], true);
    igual(['POST', 201, 'RESULTADO_REPORTADO', 'pipeline-ci', 'hosp-norte'], [$linea['metodo'], $linea['estado'], $linea['codigo'], $linea['usuario'], $linea['tenant']], 'campos');
    afirmar(!str_contains($e->accesos[0], 'token-pipeline') && !str_contains($e->accesos[0], 'SINT-0042') && !str_contains($e->accesos[0], 'contenido'), 'filtra datos');
});

prueba('OBS-03', 'Si el registro de acceso falla, la respuesta no cambia', function (): void {
    $e = new Escenario();
    igual(201, $e->kernel(registroQueFalla: true)->manejar(new Solicitud('POST', '/api/v1/e2e/versiones', ['authorization' => 'Bearer token-pipeline', 'x-tenant-id' => 'hosp-norte'],
        json_encode(['numero' => '2.4.0', 'commit' => 'c56f169', 'digest' => Escenario::DIGEST, 'modulos' => ['admision']])))->estado, 'estado');
});
