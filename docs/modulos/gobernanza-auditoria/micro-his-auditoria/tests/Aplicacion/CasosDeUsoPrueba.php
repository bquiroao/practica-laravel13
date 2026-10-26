<?php

declare(strict_types=1);

use Auditoria\Aplicacion\Comandos\ComandoAncla;
use Auditoria\Aplicacion\Comandos\ComandoDetalle;
use Auditoria\Aplicacion\Comandos\ComandoVerificar;
use Auditoria\Aplicacion\Resultados\Resultado;
use Auditoria\Dominio\Modelo\Movimiento;
use Auditoria\Dominio\Modelo\Sello;
use Auditoria\Dominio\Modelo\SolicitudRegistro;
use Auditoria\Pruebas\Dobles\Escenario;

$appHoy = ['desde' => '2026-10-21', 'hasta' => '2026-10-21'];
$appPar = static fn (Resultado $r): array => [$r->estado, $r->codigo];

prueba('APP-01', 'Camino feliz: registrar → consultar → detalle → verificar; cada lectura deja su evento sellado', function () use ($appHoy): void {
    $e = Escenario::crear();
    $r = Escenario::registrar($e, Escenario::mov());
    igual([Resultado::CREADO, 1, 1], [$r->estado, $r->datos['id'], $r->datos['secuencia']], 'registro');
    $c = Escenario::consultar($e, $appHoy + ['usuario' => 'u-ana']);
    igual([[1], null], [Escenario::ids($c), $c->datos['cursor_siguiente']], 'consulta');
    igual(['ocurrido_en' => '2026-10-21T15:15:00Z', 'recurso' => 'paciente:P-000001'],
        array_intersect_key($c->datos['movimientos'][0], ['ocurrido_en' => 1, 'recurso' => 1]), 'resumen');
    afirmar(!isset($c->datos['movimientos'][0]['detalle']), 'la lista incluye el detalle');
    $d = $e->casos['detalle']->ejecutar(new ComandoDetalle('auditor-oriente', Escenario::H_ORIENTE, 1));
    igual(['P-000001', 1], [$d->datos['movimiento']['detalle']['codigo'], $d->datos['movimiento']['secuencia']], 'detalle');
    $v = $e->casos['verificar']->ejecutar(new ComandoVerificar('superadmin', Escenario::H_ORIENTE));
    igual(['estado' => 'INTEGRA', 'verificados' => 3], $v->datos, 'verificación (registro + 2 lecturas en la cadena de oriente)');
    $acciones = array_map(static fn (Movimiento $m): string => "{$m->eventId}:{$m->accion}:" . ($m->hospital ?? 'red'), $e->bitacora->todos());
    igual(['ev-1:PACIENTE_REGISTRADO:hosp-oriente', 'sis-1:AUDITORIA_CONSULTADA:hosp-oriente', 'sis-2:AUDITORIA_CONSULTADA:hosp-oriente',
        'sis-3:INTEGRIDAD_VERIFICADA:red'], $acciones, 'la bitácora completa');
});

prueba('APP-02', 'Regla de dominio: otro hospital (403) o datos inválidos (422) se rechazan SIN abrir transacción ni guardar', function () use ($appPar): void {
    $e = Escenario::crear();
    igual([Resultado::PROHIBIDO, 'TENANT_NO_COINCIDE'], $appPar(Escenario::registrar($e, Escenario::mov(['hospital' => Escenario::H_NORTE]))), 'tenant');
    $r = Escenario::registrar($e, Escenario::mov(['accion' => 'ROL_ASIGNADO']));
    igual([Resultado::INVALIDO, 'DATOS_INVALIDOS', ['accion']], [$r->estado, $r->codigo, array_keys($r->datos['campos'])], 'catálogo');
    igual([Resultado::PROHIBIDO, 'MODULO_NO_PERMITIDO'], $appPar(Escenario::registrar($e, Escenario::mov(['modulo' => 'rbac', 'accion' => 'ROL_ASIGNADO']))), 'módulo ajeno');
    igual([Resultado::PROHIBIDO, 'ACCESO_DENEGADO'], $appPar(Escenario::registrar($e, Escenario::mov(), 'svc-citas')), 'sin auditoria.registrar');
    igual([0, 0], [count($e->bitacora->todos()), $e->transaccion->confirmadas + $e->transaccion->revertidas], 'filas y transacciones');
});

prueba('APP-03', 'Idempotencia: el reintento idéntico devuelve el MISMO id y sello sin alargar la cadena; con otro contenido, 409', function () use ($appPar): void {
    $e = Escenario::crear();
    $original = Escenario::registrar($e, Escenario::mov());
    $reintento = Escenario::registrar($e, Escenario::mov(['detalle' => ['codigo' => 'P-000001']]));
    igual([Resultado::OK, 'YA_REGISTRADO', $original->datos], [$reintento->estado, $reintento->codigo, $reintento->datos], 'reintento');
    igual([Resultado::CONFLICTO, 'EVENTO_EN_CONFLICTO'], $appPar(Escenario::registrar($e, Escenario::mov(['resultado' => 'error']))), 'conflicto');
    igual(1, count($e->bitacora->todos()), 'filas');
});

prueba('APP-04', 'Error de persistencia al registrar: 503, ROLLBACK, nada guardado; el reintento con el mismo event_id lo crea', function () use ($appPar): void {
    $e = Escenario::crear();
    $e->bitacora->fallarEn = ['agregar'];
    igual([Resultado::NO_DISPONIBLE, 'SERVICIO_NO_DISPONIBLE'], $appPar(Escenario::registrar($e, Escenario::mov())), 'caída');
    igual([0, 1], [count($e->bitacora->todos()), $e->transaccion->revertidas], 'filas y rollback');
    $e->bitacora->fallarEn = [];
    igual(Resultado::CREADO, Escenario::registrar($e, Escenario::mov())->estado, 'reintento');
});

prueba('APP-05', 'Error de persistencia en la meta-auditoría: la consulta responde 503 y NO entrega resultados (R-08)', function () use ($appHoy, $appPar): void {
    $e = Escenario::crear();
    Escenario::registrar($e, Escenario::mov());
    $e->bitacora->fallarEn = ['agregar'];
    $r = Escenario::consultar($e, $appHoy);
    igual([[Resultado::NO_DISPONIBLE, 'SERVICIO_NO_DISPONIBLE'], []], [$appPar($r), $r->datos], 'sin resultados');
    $d = $e->casos['detalle']->ejecutar(new ComandoDetalle('auditor-oriente', Escenario::H_ORIENTE, 1));
    igual([Resultado::NO_DISPONIBLE, 'SERVICIO_NO_DISPONIBLE'], $appPar($d), 'detalle');
});

prueba('APP-06', 'Error de persistencia al leer (buscar, porEvento, porId, cadena): 503 genérico en cada caso de uso', function () use ($appHoy, $appPar): void {
    foreach (['buscar' => fn ($e) => Escenario::consultar($e, $appHoy), 'porEvento' => fn ($e) => Escenario::registrar($e, Escenario::mov()),
        'porId' => fn ($e) => $e->casos['detalle']->ejecutar(new ComandoDetalle('auditor-oriente', Escenario::H_ORIENTE, 1)),
        'cadena' => fn ($e) => $e->casos['verificar']->ejecutar(new ComandoVerificar('superadmin', Escenario::H_ORIENTE))] as $operacion => $caso) {
        $e = Escenario::crear();
        $e->bitacora->fallarEn = [$operacion];
        igual([Resultado::NO_DISPONIBLE, 'SERVICIO_NO_DISPONIBLE'], $appPar($caso($e)), $operacion);
    }
});

prueba('APP-07', 'Alcance: el auditor ve solo su hospital; la red y otro hospital le dan 403; el super administrador ve la red', function () use ($appHoy, $appPar): void {
    $e = Escenario::crear();
    Escenario::registrar($e, Escenario::mov());
    // event_id único por emisor: el mismo «ev-1» desde norte sería un conflicto (409), no un movimiento nuevo.
    Escenario::registrar($e, Escenario::mov(['event_id' => 'ev-2', 'hospital' => Escenario::H_NORTE]), 'svc-pacientes', Escenario::H_NORTE);
    igual([1], Escenario::ids(Escenario::consultar($e, $appHoy)), 'auditor de oriente');
    igual([Resultado::PROHIBIDO, 'ACCESO_DENEGADO'], $appPar(Escenario::consultar($e, $appHoy, 'auditor-oriente', '*')), 'auditor pide la red');
    igual([Resultado::PROHIBIDO, 'ACCESO_DENEGADO'], $appPar(Escenario::consultar($e, $appHoy, 'auditor-oriente', Escenario::H_NORTE)), 'auditor pide norte');
    $red = Escenario::ids(Escenario::consultar($e, $appHoy + ['modulo' => 'pacientes'], 'superadmin', '*'));
    sort($red);
    igual([1, 2], $red, 'super administrador, red');
    $ajeno = $e->casos['detalle']->ejecutar(new ComandoDetalle('auditor-oriente', Escenario::H_ORIENTE, 2));
    $inexistente = $e->casos['detalle']->ejecutar(new ComandoDetalle('auditor-oriente', Escenario::H_ORIENTE, 99));
    igual($appPar($inexistente) + [2 => $inexistente->datos], $appPar($ajeno) + [2 => $ajeno->datos], 'detalle ajeno = inexistente');
});

prueba('APP-08', 'Paginación: 51 movimientos → 50 + cursor; con el cursor, el que falta; cursor alterado → 422', function () use ($appHoy, $appPar): void {
    $e = Escenario::crear();
    for ($i = 1; $i <= 51; $i++) {
        Escenario::registrar($e, Escenario::mov(['event_id' => "ev-$i", 'ocurrido_en' => '2026-10-21T08:00:00-06:00']));
    }
    $p1 = Escenario::consultar($e, $appHoy);
    $ids = Escenario::ids($p1);
    igual([50, 51, 2], [count($ids), $ids[0], $ids[49]], 'primera página (misma hora: desempata el id)');
    $p2 = Escenario::consultar($e, $appHoy + ['cursor' => $p1->datos['cursor_siguiente']]);
    igual([[1], null], [Escenario::ids($p2), $p2->datos['cursor_siguiente']], 'segunda página');
    igual([Resultado::INVALIDO, 'DATOS_INVALIDOS'], $appPar(Escenario::consultar($e, $appHoy + ['cursor' => 'no-es-un-cursor'])), 'cursor alterado');
});

prueba('APP-09', 'Integridad: alterar la fila 2 → ALTERADA en 2; recalcular su sello → ALTERADA en 3; el auditor no puede verificar', function () use ($appPar): void {
    $e = Escenario::crear();
    for ($i = 1; $i <= 4; $i++) {
        Escenario::registrar($e, Escenario::mov(['event_id' => "ev-$i"]));
    }
    $verificar = static fn (): array => $e->casos['verificar']->ejecutar(new ComandoVerificar('superadmin', Escenario::H_ORIENTE))->datos;
    igual('INTEGRA', $verificar()['estado'], 'antes');
    $original = $e->bitacora->porId(2);
    $alterado = Movimiento::desdeSolicitud(new SolicitudRegistro('svc-pacientes', Escenario::H_ORIENTE, Escenario::mov(['event_id' => 'ev-2', 'usuario' => 'u-encubierto'])));
    $e->bitacora->alterarParaPrueba(2, $alterado->sellado(2, $original->sello));
    igual(['ALTERADA', 2], [$verificar()['estado'], $verificar()['primera_secuencia']], 'sello viejo');
    $e->bitacora->alterarParaPrueba(2, $alterado->sellado(2, Sello::calcular($e->bitacora->porId(1)->sello, 2, $alterado)));
    igual(['ALTERADA', 3], [$verificar()['estado'], $verificar()['primera_secuencia']], 'sello recalculado: lo delata la fila 3');
    igual([Resultado::PROHIBIDO, 'ACCESO_DENEGADO'], $appPar($e->casos['verificar']->ejecutar(new ComandoVerificar('auditor-oriente', Escenario::H_ORIENTE))), 'auditor');
});

prueba('APP-11', 'Ancla externa (semana 5): borrar la ÚLTIMA fila no se ve sin ancla; con el ancla publicada antes, TRUNCADA; el auditor no obtiene anclas', function () use ($appPar): void {
    $e = Escenario::crear();
    for ($i = 1; $i <= 4; $i++) {
        Escenario::registrar($e, Escenario::mov(['event_id' => "ev-$i"]));
    }
    $ancla = $e->casos['ancla']->ejecutar(new ComandoAncla('superadmin', Escenario::H_ORIENTE));
    igual([4, 64], [$ancla->datos['secuencia'], strlen($ancla->datos['sello'])], 'ancla');
    $verificar = static fn (): array => $e->casos['verificar']->ejecutar(new ComandoVerificar('superadmin', Escenario::H_ORIENTE,
        ['secuencia' => $ancla->datos['secuencia'], 'sello' => $ancla->datos['sello']]))->datos;
    igual(['estado' => 'INTEGRA', 'verificados' => 4, 'ancla_confirmada' => 4], $verificar(), 'íntegra');
    $e->bitacora->ocultarParaPrueba(4);                      // DELETE de la última fila con el trigger desactivado
    $sinAncla = $e->casos['verificar']->ejecutar(new ComandoVerificar('superadmin', Escenario::H_ORIENTE))->datos;
    igual('INTEGRA', $sinAncla['estado'], 'SIN ancla el truncamiento no se ve (la cadena que queda es coherente)');
    igual(['estado' => 'TRUNCADA', 'esperada_hasta' => 4, 'verificados' => 3], $verificar(), 'con ancla');
    igual([Resultado::PROHIBIDO, 'ACCESO_DENEGADO'], $appPar($e->casos['ancla']->ejecutar(new ComandoAncla('auditor-oriente', Escenario::H_ORIENTE))), 'auditor');
});

prueba('APP-10', 'La cadena se bloquea ANTES de leer el último sello, una vez por movimiento y por la cadena correcta', function () use ($appHoy): void {
    $e = Escenario::crear();
    Escenario::registrar($e, Escenario::mov());
    Escenario::consultar($e, $appHoy, 'superadmin', '*');
    igual([Escenario::H_ORIENTE, null], $e->bitacora->bloqueos, 'registro en oriente y meta-auditoría de red');
});
