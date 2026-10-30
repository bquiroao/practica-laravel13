<?php

declare(strict_types=1);

/*
 * Semana 7 — Pruebas de CARACTERIZACIÓN del refactor de la consulta. Se escribieron y ejecutaron
 * sobre el código ANTERIOR al refactor: fijan lo que se iba a mover (orden de las comprobaciones,
 * meta-auditoría exacta, forma de la salida y formato del cursor) para demostrar que no cambió.
 */

use Auditoria\Aplicacion\Comandos\ComandoDetalle;
use Auditoria\Aplicacion\Resultados\Resultado;
use Auditoria\Pruebas\Dobles\Escenario;

const REF_RANGO = ['desde' => '2026-10-21', 'hasta' => '2026-10-21'];

$refError = static fn (Resultado $r): array => [$r->estado, $r->codigo, array_keys($r->datos['campos'] ?? [])];

prueba('REF-01', 'Orden de las comprobaciones de la consulta: permiso → cursor → filtro no soportado → datos inválidos', function () use ($refError): void {
    $e = Escenario::crear();
    $todo = ['cursor' => '%%%', 'color' => 'rojo', 'desde' => 'ayer'];
    igual([Resultado::PROHIBIDO, 'ACCESO_DENEGADO', []], $refError(Escenario::consultar($e, $todo, 'auditor-oriente', '*')),
        'sin permiso de red: 403 antes que cualquier error de los parámetros');
    igual([Resultado::INVALIDO, 'DATOS_INVALIDOS', ['cursor']], $refError(Escenario::consultar($e, $todo)), 'cursor antes que el filtro');
    unset($todo['cursor']);
    igual([Resultado::INVALIDO, 'FILTRO_NO_SOPORTADO', ['color']], $refError(Escenario::consultar($e, $todo)), 'filtro antes que los datos');
    igual([Resultado::INVALIDO, 'DATOS_INVALIDOS', ['desde']], $refError(Escenario::consultar($e, ['usuario' => 'u-ana'])),
        'el rango es obligatorio aunque haya otro filtro');
    igual(0, count($e->bitacora->todos()), 'una consulta rechazada no deja meta-auditoría');
});

prueba('REF-02', 'Meta-auditoría exacta de consulta y detalle, por hospital y por red (R-08)', function (): void {
    $e = Escenario::crear();
    Escenario::registrar($e, Escenario::mov());
    $ultima = static fn (): array => (static fn (array $t): array => (static fn ($m): array =>
        [$m->hospital, $m->usuario, $m->accion, $m->detalle])(end($t)))($e->bitacora->todos());

    Escenario::consultar($e, REF_RANGO + ['usuario' => 'u-ana']);
    igual([Escenario::H_ORIENTE, 'auditor-oriente', 'AUDITORIA_CONSULTADA',
        ['alcance' => 'hospital', 'filtros' => REF_RANGO + ['usuario' => 'u-ana'], 'resultados' => 1]], $ultima(), 'consulta por hospital');
    Escenario::consultar($e, REF_RANGO, 'superadmin', '*');
    // La red ve 2: el movimiento y la meta-auditoría de la consulta anterior (también es un movimiento auditable).
    igual([null, 'superadmin', 'AUDITORIA_CONSULTADA', ['alcance' => 'red', 'filtros' => REF_RANGO, 'resultados' => 2]], $ultima(),
        'consulta de red: la evidencia va a la cadena de red');
    $e->casos['detalle']->ejecutar(new ComandoDetalle('auditor-oriente', Escenario::H_ORIENTE, 1));
    igual([Escenario::H_ORIENTE, 'auditor-oriente', 'AUDITORIA_CONSULTADA', ['alcance' => 'hospital', 'detalle' => 1]], $ultima(), 'detalle');
    $e->casos['detalle']->ejecutar(new ComandoDetalle('superadmin', '*', 1));
    igual([null, 'superadmin', 'AUDITORIA_CONSULTADA', ['alcance' => 'red', 'detalle' => 1]], $ultima(), 'detalle de red');

    $antes = count($e->bitacora->todos());
    $fuera = $e->casos['detalle']->ejecutar(new ComandoDetalle('superadmin', Escenario::H_NORTE, 1));
    $inexistente = $e->casos['detalle']->ejecutar(new ComandoDetalle('superadmin', Escenario::H_NORTE, 999));
    igual([Resultado::NO_ENCONTRADO, 'MOVIMIENTO_NO_ENCONTRADO'], [$fuera->estado, $fuera->codigo], 'fuera del alcance = 404');
    igual([$inexistente->estado, $inexistente->codigo, $inexistente->datos], [$fuera->estado, $fuera->codigo, $fuera->datos], 'igual que inexistente');
    igual($antes, count($e->bitacora->todos()), 'un 404 no deja meta-auditoría');
});

prueba('REF-03', 'Forma de la salida y formato del cursor (un cliente puede tener guardado un cursor de una página anterior)', function (): void {
    $e = Escenario::crear();
    for ($i = 1; $i <= 51; $i++) {
        Escenario::registrar($e, Escenario::mov(['event_id' => "ev-$i"]));
    }
    $pagina1 = Escenario::consultar($e, REF_RANGO);
    $movimientos = $pagina1->datos['movimientos'];
    igual([50, 51, 2], [count($movimientos), $movimientos[0]['id'], $movimientos[49]['id']], 'página de 50, más reciente primero');
    igual($e->bitacora->porId(51)->resumen() + ['corregido_por' => null], $movimientos[0], 'fila = resumen + corregido_por, en ese orden');
    $esperado = rtrim(strtr(base64_encode('2026-10-21T15:15:00Z|2'), '+/', '-_'), '=');
    igual($esperado, $pagina1->datos['cursor_siguiente'], 'cursor = base64url(«ocurrido_en UTC|id») sin relleno');
    $pagina2 = Escenario::consultar($e, REF_RANGO + ['cursor' => $esperado]);
    igual([[1], null], [Escenario::ids($pagina2), $pagina2->datos['cursor_siguiente']], 'la segunda página continúa y termina');

    $detalle = $e->casos['detalle']->ejecutar(new ComandoDetalle('auditor-oriente', Escenario::H_ORIENTE, 51));
    igual(['movimiento' => $e->bitacora->porId(51)->completo() + ['corregido_por' => null]], $detalle->datos, 'detalle = completo + corregido_por');
});
