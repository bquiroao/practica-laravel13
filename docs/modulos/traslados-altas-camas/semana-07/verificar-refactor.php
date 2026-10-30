<?php
declare(strict_types=1);

// Semana 7 — Demuestra el refactor con el código real del Micro-HIS (semana 4) y el ejemplo «después».
//   CAR-xx: dónde viven las reglas.  EQV/ATO: el resultado en la base es el mismo y sigue siendo todo o nada.  CAM: un cambio de regla.  CON/DOC: contratos y entregables.
// Uso desde la raíz del repo: php docs/modulos/traslados-altas-camas/semana-07/verificar-refactor.php
use Traslados\Persistencia\MovimientosPdo;
use Traslados\Dominio\Resultado;
use Traslados\Refactor\{AplicadorMemoria, AplicadorPdo, MovimientoConfirmado, PlanDeMovimiento};

$micro = dirname(__DIR__) . '/micro-his-traslados'; $fallas = 0;
function ver(string $id, bool $ok, string $que): void { $GLOBALS['fallas'] += $ok ? 0 : 1; printf("%-7s %-5s %s\n", $id, $ok ? 'PASA' : 'FALLA', $que); }
require "$micro/bootstrap.php"; require __DIR__ . '/ejemplo-refactor/despues/Plan.php'; require __DIR__ . '/ejemplo-refactor/despues/Aplicadores.php';

// Dónde viven las reglas: los 5 valores que deciden qué le pasa a cada tabla
$reglas = ["'maintenance'", "'occupied'", "'aplicada'", "'alta'", "'closed'"];
$cuenta = function (array $archivos) use ($reglas): array {
    $t = implode("\n", array_map(fn ($a) => (string) file_get_contents($a), $archivos));
    return array_map(fn ($r) => substr_count($t, $r), $reglas);
};
$antesPdo = $cuenta(["$micro/src/Persistencia/MovimientosPdo.php"]); $antesMem = $cuenta(["$micro/src/Persistencia/EnMemoria.php"]);
ver('CAR-01', min($antesPdo) >= 1 && min($antesMem) >= 1, sprintf('ANTES: las 5 reglas están escritas en MovimientosPdo (%s veces) y otra vez en EnMemoria (%s veces)', array_sum($antesPdo), array_sum($antesMem)));
$plan = $cuenta([__DIR__ . '/ejemplo-refactor/despues/Plan.php']);
$apl = (string) file_get_contents(__DIR__ . '/ejemplo-refactor/despues/Aplicadores.php');
$enAplicadores = array_sum(array_map(fn ($r) => substr_count($apl, $r), $reglas));
ver('CAR-02', min($plan) >= 1 && $enAplicadores === 0, sprintf('DESPUÉS: las 5 reglas salen solo de PlanDeMovimiento; los aplicadores no los contienen (%d apariciones)', $enAplicadores));

// Escenarios sobre la base de demostración (sql/esquema.sql del Micro-HIS)
$base = function () use ($micro): PDO { $p = new PDO('sqlite::memory:'); $p->exec((string) file_get_contents("$micro/sql/esquema.sql")); return $p; };
$volcado = function (PDO $p): array {
    $g = fn (string $s) => $p->query($s)->fetchAll(PDO::FETCH_ASSOC);
    return ['camas' => array_column($g('SELECT bed_id, status FROM beds ORDER BY 1'), 'status', 'bed_id'),
            'estancias' => $g('SELECT bed_stay_id, care_episode_id, bed_id, started_at, ended_at, reason FROM bed_stays ORDER BY 1'),
            'episodios' => array_column($g('SELECT care_episode_id, status || coalesce(ended_at, \'\') AS s FROM care_episodes ORDER BY 1'), 's', 'care_episode_id'),
            'ordenes' => array_column($g('SELECT orden_id, estado FROM ordenes_movimiento ORDER BY 1'), 'estado', 'orden_id')];
};
$desdeMemoria = function (AplicadorMemoria $m): array {
    $e = []; foreach ($m->estancias as $id => $s) { $e[] = ['bed_stay_id' => $id] + array_intersect_key($s, array_flip(['care_episode_id', 'bed_id', 'started_at', 'ended_at', 'reason'])); }
    usort($e, fn ($a, $b) => $a['bed_stay_id'] <=> $b['bed_stay_id']); ksort($m->camas); ksort($m->ordenes); ksort($m->episodios);
    return ['camas' => array_map(fn ($c) => $c['status'], $m->camas), 'estancias' => $e,
            'episodios' => array_map(fn ($x) => $x['status'] . ($x['ended_at'] ?? ''), $m->episodios), 'ordenes' => array_map(fn ($o) => $o['estado'], $m->ordenes)];
};
$memoriaDe = function (PDO $p): AplicadorMemoria {
    $t = fn (string $tabla, string $llave) => array_column($p->query("SELECT * FROM $tabla")->fetchAll(PDO::FETCH_ASSOC), null, $llave);
    return new AplicadorMemoria($t('beds', 'bed_id'), $t('bed_stays', 'bed_stay_id'), $t('care_episodes', 'care_episode_id'), $t('ordenes_movimiento', 'orden_id'));
};
$m = fn (string $tipo, string $epi, string $orden, string $origenCama, string $estanciaOrigen, ?string $destino) => new MovimientoConfirmado(
    $epi, $orden, $tipo, Resultado::aplicado($origenCama, $destino), $estanciaOrigen, 'stay-nueva', new DateTimeImmutable('2026-10-27 09:00'));
$antes = fn (PDO $p, MovimientoConfirmado $x) => (new MovimientosPdo($p))->aplicar($x->episodioId, $x->ordenId, $x->tipo, $x->resultado, $x->estanciaOrigenId, $x->estanciaNuevaId, $x->en);
$traslado = $m('traslado', 'epi-1', 'ord-1', 'bed-a1', 'stay-1', 'bed-a2'); $alta = $m('alta', 'epi-2', 'ord-2', 'bed-a3', 'stay-2', null);

foreach ([['EQV-01', 'traslado (epi-1: A1 → A2)', $traslado], ['EQV-02', 'alta (epi-2: libera A3 y cierra el episodio)', $alta]] as [$id, $nombre, $mov]) {
    $a = $base(); $antes($a, $mov);                                                    // ANTES: el código real de la semana 4
    $b = $base(); (new AplicadorPdo($b))->aplicar((new PlanDeMovimiento())->para($mov)); // DESPUÉS: plan + aplicador PDO
    $c = $memoriaDe($base()); $c->aplicar((new PlanDeMovimiento())->para($mov));          // DESPUÉS: plan + aplicador en memoria
    ver($id, $volcado($a) === $volcado($b) && $volcado($a) === $desdeMemoria($c), "Tras un $nombre, ANTES, DESPUÉS-PDO y DESPUÉS-memoria dejan las mismas 4 tablas");
}

// Todo o nada: la estancia nueva repite una llave que ya existe → falla al insertar, después de haber cerrado y cambiado camas; todo se deshace
$malo = new MovimientoConfirmado('epi-1', 'ord-1', 'traslado', Resultado::aplicado('bed-a1', 'bed-a2'), 'stay-1', 'stay-2', new DateTimeImmutable('2026-10-27 09:00'));
$inicial = $volcado($base());
$falla = function (callable $f): bool { try { $f(); return false; } catch (\Traslados\Aplicacion\ErrorPersistencia) { return true; } };
$p1 = $base(); $p2 = $base(); $mem = $memoriaDe($base());
$ok = $falla(fn () => $antes($p1, $malo)) && $falla(fn () => (new AplicadorPdo($p2))->aplicar((new PlanDeMovimiento())->para($malo))) && $falla(fn () => $mem->aplicar((new PlanDeMovimiento())->para($malo)));
ver('ATO-01', $ok && $volcado($p1) === $inicial && $volcado($p2) === $inicial && $desdeMemoria($mem) === $inicial, 'Con una llave de estancia repetida los tres fallan con ErrorPersistencia y ninguna tabla cambia (todo o nada)');

// Observación (no se corrige en esta semana): el código de la semana 4 no revisa que la estancia de origen siga abierta
$q = $base(); $q->exec("INSERT INTO bed_stays VALUES ('stay-x', 'epi-1', 'bed-a1', '2026-10-01 08:00', '2026-10-02 08:00', 'ingreso')");
$cerrada = new MovimientoConfirmado('epi-1', 'ord-1', 'traslado', Resultado::aplicado('bed-a1', 'bed-a2'), 'stay-x', 'stay-nueva', new DateTimeImmutable('2026-10-27 09:00'));
try { $antes($q, $cerrada); $sinError = true; } catch (\Throwable) { $sinError = false; }
ver('OBS-01', $sinError && $q->query("SELECT COUNT(*) FROM bed_stays WHERE care_episode_id = 'epi-1' AND ended_at IS NULL")->fetchColumn() === 2, 'Observación: con la estancia de origen ya cerrada, MovimientosPdo::aplicar no falla y deja 2 estancias abiertas en epi-1 (no se revisa cuántas filas actualizó el UPDATE)');

// Un cambio de regla, en un solo lugar: la cama liberada queda 'available' en vez de 'maintenance'
$pl = new PlanDeMovimiento('available'); $x = $base(); (new AplicadorPdo($x))->aplicar($pl->para($traslado)); $y = $memoriaDe($base()); $y->aplicar($pl->para($traslado));
ver('CAM-01', $volcado($x)['camas']['bed-a1'] === 'available' && $y->camas['bed-a1']['status'] === 'available', 'Cambiar la política en PlanDeMovimiento (1 argumento) cambia el resultado de los dos adaptadores sin tocarlos');

// Contratos del documento = contratos del código
$doc = (string) @file_get_contents(__DIR__ . '/semana-07-componentes-refactor.md');
$app = (string) file_get_contents("$micro/src/Aplicacion/RegistrarMovimiento.php");
preg_match_all("/return \['aplicado' => .*?\];/", $app, $rs); $r = [end($rs[0]) ?: '']; preg_match_all("/'(\w+)' =>/", $r[0] ?? '', $claves);
preg_match('/\$entrada: ([^.]*)\./', $app, $e); preg_match_all('/\b(episodio_id|tipo|destino_id)\b/', $e[1] ?? '', $ent);
$enDoc = fn (array $ks) => array_filter($ks, fn ($k) => !str_contains($doc, "`$k`"));
ver('CON-01', count(array_unique($claves[1])) === 4 && count(array_unique($ent[0])) === 3 && $enDoc(array_merge($claves[1], $ent[0])) === [] && str_contains($doc, 'PlanDeMovimiento'), 'El documento describe las 3 claves de entrada y las 4 de salida que tiene RegistrarMovimiento, y el contrato del plan');
$img = array_filter(['componentes', 'refactor-antes-despues'], fn ($n) => is_file(__DIR__ . "/diagramas/semana-07-$n.png") && is_file(__DIR__ . "/diagramas/semana-07-$n.puml"));
ver('DOC-01', count($img) === 2 && $doc !== '', 'Existen el documento y los 2 diagramas (componentes y antes/después) con su fuente');
echo $fallas === 0 ? "\nResultado: el refactor conserva el comportamiento y concentra las reglas.\n" : "\nResultado: $fallas comprobaciones fallan.\n";
exit($fallas ? 1 : 0);
