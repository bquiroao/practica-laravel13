<?php
declare(strict_types=1);

// Semana 15: pruebas unitarias (U), de caja blanca (W) y de caja negra (B) trazadas a RF/RNF.  php tests/semana-15.php
// Un defecto conocido (acta de la semana 13) se prueba con defecto(): ABIERTO mientras se reproduce; si deja de reproducirse, FALLA y pide pasarlo a prueba normal.
use Traslados\Aplicacion\{RegistrarMovimiento, Usuario};
use Traslados\Dominio\{Alta, CamaDestino, CamaOrigen, RequisitoCama, Resultado, TipoAlta, Traslado};
use Traslados\Persistencia\MovimientosPdo;
use Traslados\Presentacion\{Enrutador, MovimientosControlador, Peticion, VistaJson};

require __DIR__ . '/../bootstrap.php';

$conteo = ['PASA' => 0, 'FALLA' => 0, 'ABIERTO' => 0];
function prueba(string $id, string $trazas, string $que, callable $cuerpo): void {
    try { $cuerpo(); $estado = 'PASA'; $detalle = ''; } catch (\Throwable $e) { $estado = 'FALLA'; $detalle = ' → ' . $e->getMessage(); }
    $GLOBALS['conteo'][$estado]++; printf("%-5s %-8s %-21s %s%s\n", $id, $estado, $trazas, $que, $detalle);
}
function defecto(string $id, string $hallazgo, string $trazas, string $que, callable $cuerpo): void {
    try { $cuerpo(); $estado = 'ABIERTO'; $detalle = " ($hallazgo)"; } catch (\Throwable $e) { $estado = 'FALLA'; $detalle = " → ya no se reproduce ($hallazgo): conviértalo en prueba normal. " . $e->getMessage(); }
    $GLOBALS['conteo'][$estado]++; printf("%-5s %-8s %-21s %s%s\n", $id, $estado, $trazas, $que, $detalle);
}
function igual(mixed $esperado, mixed $real, string $que = 'valor'): void { if ($esperado !== $real) { throw new \RuntimeException("$que: esperado " . json_encode($esperado) . ', obtenido ' . json_encode($real)); } }
function errorDe(callable $f): \Throwable { try { $f(); } catch (\Throwable $e) { return $e; } throw new \RuntimeException('se esperaba un error'); }
function base(): PDO { $pdo = new PDO('sqlite::memory:'); $pdo->exec((string) file_get_contents(__DIR__ . '/../sql/esquema.sql')); return $pdo; }
function caso(PDO $pdo): RegistrarMovimiento { $r = new MovimientosPdo($pdo); return new RegistrarMovimiento($r, $r, fn () => new DateTimeImmutable('2026-10-25 12:00'), fn () => 'estancia-nueva'); }
function api(PDO $pdo): Enrutador { return new Enrutador(new MovimientosControlador(caso($pdo), ['ana' => new Usuario('enf-ana', 'enfermeria'), 'eva' => new Usuario('adm-eva', 'admision'), 'luis' => new Usuario('dr-luis', 'medico')])); }
function http(Enrutador $e, array|string $cuerpo, string $usuario = 'ana', string $metodo = 'POST', string $ruta = '/api/v1/traslados-altas/movimientos'): array { return $e->atender(new Peticion($metodo, $ruta, [], $usuario, is_string($cuerpo) ? $cuerpo : json_encode($cuerpo))); }
function mover(Enrutador $e, string $episodio, string $tipo, string $destino = '', string $usuario = 'ana'): array { return http($e, ['episodio_id' => $episodio, 'tipo' => $tipo, 'destino_id' => $destino], $usuario); }
function foto(PDO $pdo): string {   // estado completo de camas, estancias, órdenes y episodios
    return json_encode([$pdo->query('SELECT bed_id, status FROM beds ORDER BY 1')->fetchAll(PDO::FETCH_NUM), $pdo->query('SELECT bed_stay_id, bed_id, ended_at FROM bed_stays ORDER BY 1')->fetchAll(PDO::FETCH_NUM),
        $pdo->query('SELECT orden_id, estado FROM ordenes_movimiento ORDER BY 1')->fetchAll(PDO::FETCH_NUM), $pdo->query('SELECT care_episode_id, status FROM care_episodes ORDER BY 1')->fetchAll(PDO::FETCH_NUM)]);
}
function estadoCama(PDO $pdo, string $cama): string { return (string) $pdo->query("SELECT status FROM beds WHERE bed_id = '$cama'")->fetchColumn(); }
function abiertas(PDO $pdo, string $cama): int { return (int) $pdo->query("SELECT COUNT(*) FROM bed_stays WHERE bed_id = '$cama' AND ended_at IS NULL")->fetchColumn(); }
$origen = new CamaOrigen('bed-a1', 'stay-1');

// ── Unitarias ─────────────────────────────────────────────────────────────────
prueba('U-01', 'RF-04, RF-09', 'Traslado: sin origen, destino nulo, misma cama o no disponible (ocupada, mantenimiento, inactiva) → rechazo con su código; disponible → aplicado', function () use ($origen) {
    $t = new Traslado(); $r = fn (?CamaOrigen $o, ?CamaDestino $d) => $t->aplicar($o, $d)->rechazo;
    igual('sin_cama', $r(null, new CamaDestino('bed-a2', 'available', 'general'))); igual('cama_no_disponible', $r($origen, null)); igual('cama_no_disponible', $r($origen, new CamaDestino('bed-a1', 'available', 'general')));
    foreach (['occupied', 'maintenance', 'inactive'] as $estado) { igual('cama_no_disponible', $r($origen, new CamaDestino('bed-x', $estado, 'general')), $estado); }
    $ok = $t->aplicar($origen, new CamaDestino('bed-a2', 'available', 'general')); igual([null, 'bed-a1', 'bed-a2'], [$ok->rechazo, $ok->origenId, $ok->destinoId]);
});
prueba('U-02', 'RF-04', 'Traslado con requisito UCI: cama general disponible → cama_no_cumple_requisito; cama UCI disponible → aplicado; cama UCI ocupada → cama_no_disponible', function () use ($origen) {
    $t = new Traslado(RequisitoCama::Uci);
    igual('cama_no_cumple_requisito', $t->aplicar($origen, new CamaDestino('bed-a2', 'available', 'general'))->rechazo); igual(true, $t->aplicar($origen, new CamaDestino('bed-u1', 'available', 'uci'))->fueAplicado());
    igual('cama_no_disponible', $t->aplicar($origen, new CamaDestino('bed-u1', 'occupied', 'uci'))->rechazo);
});
prueba('U-03', 'RF-03, RF-05', 'Alta: motivo vacío o de espacios → alta_invalida; sin origen → sin_cama; las cuatro altas aplican, liberan el origen y no ocupan destino', function () use ($origen) {
    foreach (['', '   '] as $motivo) { igual('alta_invalida', (new Alta(TipoAlta::Medica, $motivo))->aplicar($origen, null)->rechazo, "motivo={$motivo}"); }
    igual('sin_cama', (new Alta(TipoAlta::Medica, 'x'))->aplicar(null, null)->rechazo);
    foreach (TipoAlta::cases() as $tipo) { $r = (new Alta($tipo, 'motivo'))->aplicar($origen, new CamaDestino('bed-a2', 'available', 'general')); igual([null, 'bed-a1', null], [$r->rechazo, $r->origenId, $r->destinoId], $tipo->value); }
});
prueba('U-04', 'RF-09, RNF-06', 'VistaJson: cada código de rechazo tiene su estado HTTP (400, 401, 403, 404, 405, 409, 422, 500) y un código desconocido cae en 422', function () {
    foreach (['json_invalido' => 400, 'sin_sesion' => 401, 'acceso_denegado' => 403, 'ruta_no_encontrada' => 404, 'metodo_no_permitido' => 405, 'episodio_no_disponible' => 409, 'orden_requerida' => 409,
              'sin_cama' => 409, 'cama_no_disponible' => 409, 'cama_no_cumple_requisito' => 409, 'alta_invalida' => 422, 'error_persistencia' => 500, 'otro_codigo' => 422] as $codigo => $estado) { igual($estado, VistaJson::error($codigo)[0], $codigo); }
    igual(201, VistaJson::creada(['aplicado' => true])[0]);
});

// ── Caja blanca (ramas del código) ────────────────────────────────────────────
prueba('W-01', 'RF-01, RF-09, RNF-03', 'RegistrarMovimiento recorre sus guardas en orden (rol → episodio cerrado → sin orden → sin cama → destino) y ninguna cambia la base', function () {
    $pdo = base(); $e = api($pdo); $antes = foto($pdo);
    igual([403, 'acceso_denegado'], [mover($e, 'epi-1', 'traslado', 'bed-a2', 'luis')[0], mover($e, 'epi-1', 'traslado', 'bed-a2', 'luis')[1]['codigo']]);
    igual('episodio_no_disponible', mover($e, 'epi-cerrado', 'alta')[1]['codigo']); igual('orden_requerida', mover($e, 'epi-1', 'alta')[1]['codigo']);
    igual('sin_cama', mover($e, 'epi-9', 'traslado', 'bed-a2')[1]['codigo']); igual('cama_no_disponible', mover($e, 'epi-1', 'traslado', 'bed-a3')[1]['codigo']);
    igual($antes, foto($pdo), 'la base no cambió');
});
prueba('W-02', 'RF-02, RF-03', 'La rama del destino: traslado sin destino_id o con cama inexistente → cama_no_disponible; un alta ignora destino_id', function () {
    $e = api(base());
    igual('cama_no_disponible', mover($e, 'epi-1', 'traslado', '')[1]['codigo']); igual('cama_no_disponible', mover($e, 'epi-1', 'traslado', 'bed-zzz')[1]['codigo']);
    [$estado, $c] = mover($e, 'epi-2', 'alta', 'bed-a2'); igual([201, null], [$estado, $c['destino_id']]);
});
prueba('W-03', 'RF-05, RNF-01', 'MovimientosPdo::aplicar: el traslado cierra la estancia, deja el origen en mantenimiento, abre otra y ocupa el destino; el alta cierra el episodio sin abrir estancia', function () {
    $pdo = base(); $r = new MovimientosPdo($pdo); $en = new DateTimeImmutable('2026-10-25 12:00');
    $r->aplicar('epi-1', 'ord-1', 'traslado', Resultado::aplicado('bed-a1', 'bed-a2'), 'stay-1', 'stay-nueva', $en);
    igual(['maintenance', 'occupied'], [estadoCama($pdo, 'bed-a1'), estadoCama($pdo, 'bed-a2')]); igual([0, 1], [abiertas($pdo, 'bed-a1'), abiertas($pdo, 'bed-a2')]);
    igual('aplicada', (string) $pdo->query("SELECT estado FROM ordenes_movimiento WHERE orden_id = 'ord-1'")->fetchColumn());
    $r->aplicar('epi-2', 'ord-2', 'alta', Resultado::aplicado('bed-a3', null), 'stay-2', 'sin-uso', $en);
    igual(['closed', 'maintenance', 0], [(string) $pdo->query("SELECT status FROM care_episodes WHERE care_episode_id = 'epi-2'")->fetchColumn(), estadoCama($pdo, 'bed-a3'), (int) $pdo->query("SELECT COUNT(*) FROM bed_stays WHERE care_episode_id = 'epi-2' AND ended_at IS NULL")->fetchColumn()]);
});

// ── Caja negra (solo la API HTTP) ─────────────────────────────────────────────
prueba('B-01', 'RF-02, RF-05', 'Traslado de epi-1 a A2 por HTTP: 201; A1 en mantenimiento, A2 ocupada, orden aplicada y una sola estancia abierta del paciente', function () {
    $pdo = base(); [$estado, $c] = mover(api($pdo), 'epi-1', 'traslado', 'bed-a2');
    igual([201, true, 'bed-a1', 'bed-a2'], [$estado, $c['aplicado'], $c['origen_id'], $c['destino_id']]); igual(['maintenance', 'occupied'], [estadoCama($pdo, 'bed-a1'), estadoCama($pdo, 'bed-a2')]);
    igual(1, (int) $pdo->query("SELECT COUNT(*) FROM bed_stays WHERE care_episode_id = 'epi-1' AND ended_at IS NULL")->fetchColumn());
});
prueba('B-02', 'RF-03, RF-09', 'Alta: epi-2 → 201 y episodio cerrado; repetirla → 409; un alta sin motivo (epi-4) → 422 alta_invalida y la cama sigue ocupada', function () {
    $pdo = base(); $e = api($pdo); igual(201, mover($e, 'epi-2', 'alta')[0]); igual('closed', (string) $pdo->query("SELECT status FROM care_episodes WHERE care_episode_id = 'epi-2'")->fetchColumn());
    igual(409, mover($e, 'epi-2', 'alta')[0]); igual([422, 'alta_invalida'], [mover($e, 'epi-4', 'alta')[0], mover($e, 'epi-4', 'alta')[1]['codigo']]); igual('occupied', estadoCama($pdo, 'bed-a5'));
});
prueba('B-03', 'RF-04, RF-09', 'Destino ocupado, en mantenimiento, inexistente o no UCI → 409 con su código y la base queda idéntica', function () {
    $pdo = base(); $e = api($pdo); $antes = foto($pdo);
    foreach ([['epi-1', 'bed-a3', 'cama_no_disponible'], ['epi-1', 'bed-m1', 'cama_no_disponible'], ['epi-1', 'bed-a1', 'cama_no_disponible'], ['epi-3', 'bed-a2', 'cama_no_cumple_requisito']] as [$epi, $cama, $codigo]) {
        [$estado, $c] = mover($e, $epi, 'traslado', $cama); igual([409, $codigo], [$estado, $c['codigo']], "$epi → $cama");
    }
    igual($antes, foto($pdo), 'la base no cambió');
});
prueba('B-04', 'RF-09, RNF-03', 'Códigos de la puerta de entrada: JSON roto 400, sin sesión 401, médico 403, ruta 404, GET 405, sin cama 409, sin orden 409', function () {
    $e = api(base());
    igual([400, 401, 403, 404, 405, 409, 409], [http($e, '{roto')[0], http($e, [], 'nadie')[0], mover($e, 'epi-1', 'traslado', 'bed-a2', 'luis')[0], http($e, [], 'ana', 'POST', '/otra')[0], http($e, [], 'ana', 'GET')[0],
        mover($e, 'epi-9', 'traslado', 'bed-a2')[0], mover($e, 'epi-1', 'alta')[0]]);
    igual(201, mover($e, 'epi-1', 'traslado', 'bed-a2', 'eva')[0], 'admisión también registra');
});
prueba('B-05', 'RF-10, RNF-02', 'Sin PUT ni DELETE (405); tras una secuencia de movimientos ninguna cama tiene dos estancias abiertas ni ningún paciente dos camas', function () {
    $pdo = base(); $e = api($pdo); mover($e, 'epi-1', 'traslado', 'bed-a2'); mover($e, 'epi-3', 'traslado', 'bed-u1'); mover($e, 'epi-2', 'alta');
    igual([405, 405], [http($e, [], 'ana', 'PUT')[0], http($e, [], 'ana', 'DELETE')[0]]);
    igual([], $pdo->query('SELECT bed_id FROM bed_stays WHERE ended_at IS NULL GROUP BY bed_id HAVING COUNT(*) > 1')->fetchAll(PDO::FETCH_COLUMN), 'camas con dos estancias');
    igual([], $pdo->query('SELECT care_episode_id FROM bed_stays WHERE ended_at IS NULL GROUP BY care_episode_id HAVING COUNT(*) > 1')->fetchAll(PDO::FETCH_COLUMN), 'pacientes en dos camas');
    igual([], $pdo->query("SELECT bed_id FROM beds WHERE status = 'occupied' AND bed_id NOT IN (SELECT bed_id FROM bed_stays WHERE ended_at IS NULL)")->fetchAll(PDO::FETCH_COLUMN), 'camas ocupadas sin paciente');
});

// ── Defectos conocidos (acta de la semana 13) ─────────────────────────────────
defecto('D-01', 'H-01', 'RNF-02', 'Una cama «available» con un paciente adentro se acepta como destino (dos estancias abiertas)', function () {
    $pdo = base(); $pdo->exec("INSERT INTO bed_stays VALUES ('stay-x', 'epi-9', 'bed-a2', '2026-10-23 08:00', NULL, NULL)"); igual(201, mover(api($pdo), 'epi-1', 'traslado', 'bed-a2')[0]); igual(2, abiertas($pdo, 'bed-a2'));
});
defecto('D-02', 'H-02', 'RNF-01', 'El Repository aplica un traslado sobre una cama ocupada sin volver a comprobarla', function () {
    $pdo = base(); (new MovimientosPdo($pdo))->aplicar('epi-1', 'ord-1', 'traslado', Resultado::aplicado('bed-a1', 'bed-a3'), 'stay-1', 'estancia-nueva', new DateTimeImmutable('2026-10-25 12:00')); igual(2, abiertas($pdo, 'bed-a3'));
});
defecto('D-03', 'H-03', 'RF-09', "tipo='otro' responde 409 orden_requerida en lugar de un error de validación", function () { igual([409, 'orden_requerida'], [mover(api(base()), 'epi-1', 'otro')[0], mover(api(base()), 'epi-1', 'otro')[1]['codigo']]); });

printf("\nTotal: %d  Pasan: %d  Fallan: %d  Defectos abiertos: %d\n", array_sum($conteo), $conteo['PASA'], $conteo['FALLA'], $conteo['ABIERTO']);
exit($conteo['FALLA'] === 0 ? 0 : 1);
