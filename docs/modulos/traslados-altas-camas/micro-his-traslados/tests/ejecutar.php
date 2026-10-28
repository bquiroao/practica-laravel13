<?php
declare(strict_types=1);

// Pruebas sin dependencias:  php tests/ejecutar.php   (usa SQLite en memoria como base de prueba)
use Traslados\Aplicacion\{ErrorPersistencia, RegistrarMovimiento, Usuario};
use Traslados\Persistencia\MovimientosPdo;
use Traslados\Presentacion\MovimientosControlador;

require __DIR__ . '/../bootstrap.php';

$conteo = ['PASA' => 0, 'FALLA' => 0];
function prueba(string $id, string $que, callable $cuerpo): void {
    try { $cuerpo(); $estado = 'PASA'; $detalle = ''; } catch (\Throwable $e) { $estado = 'FALLA'; $detalle = ' → ' . $e->getMessage(); }
    $GLOBALS['conteo'][$estado]++;
    printf("%-7s %-5s %s%s\n", $id, $estado, $que, $detalle);
}
function igual(mixed $esperado, mixed $real, string $que = 'valor'): void {
    if ($esperado !== $real) { throw new \RuntimeException("$que: esperado " . json_encode($esperado) . ', obtenido ' . json_encode($real)); } }
function errorDe(callable $f): \Throwable { try { $f(); } catch (\Throwable $e) { return $e; } throw new \RuntimeException('se esperaba un error'); }
function base(): PDO { $pdo = new PDO('sqlite::memory:'); $pdo->exec((string) file_get_contents(__DIR__ . '/../sql/esquema.sql')); return $pdo; }
function valor(PDO $pdo, string $sql): mixed { return $pdo->query($sql)->fetchColumn(); }
function cama(PDO $pdo, string $id): string { return (string) valor($pdo, "SELECT status FROM beds WHERE bed_id = '$id'"); }
function caso(PDO $pdo): RegistrarMovimiento {
    return new RegistrarMovimiento(new MovimientosPdo($pdo), fn () => new DateTimeImmutable('2026-10-27 09:00'), fn () => 'stay-nueva');
}
function mover(PDO $pdo, string $episodio, string $tipo, ?string $destino = null, ?Usuario $u = null): array {
    return caso($pdo)->ejecutar(['episodio_id' => $episodio, 'tipo' => $tipo, 'destino_id' => $destino], $u ?? new Usuario('enf-ana', 'enfermeria'));
}
$rechazo = fn (string $codigo) => ['aplicado' => false, 'rechazo' => $codigo, 'origen_id' => null, 'destino_id' => null];

prueba('APP-01', 'Camino feliz traslado: epi-1 a A2 libera A1 y ocupa A2 en una sola transacción', function () {
    $pdo = base();
    igual(['aplicado' => true, 'rechazo' => null, 'origen_id' => 'bed-a1', 'destino_id' => 'bed-a2'], mover($pdo, 'epi-1', 'traslado', 'bed-a2'), 'resultado');
    igual(['maintenance', 'occupied'], [cama($pdo, 'bed-a1'), cama($pdo, 'bed-a2')], 'camas');
    igual([1, 'stay-nueva'], [(int) valor($pdo, "SELECT ended_at IS NOT NULL FROM bed_stays WHERE bed_stay_id = 'stay-1'"),
                              valor($pdo, "SELECT bed_stay_id FROM bed_stays WHERE care_episode_id = 'epi-1' AND ended_at IS NULL")], 'estancias');
    igual('aplicada', valor($pdo, "SELECT estado FROM ordenes_movimiento WHERE orden_id = 'ord-1'"), 'orden');
});
prueba('APP-02', 'Camino feliz alta: epi-2 libera A3 y cierra el episodio', function () {
    $pdo = base();
    igual(['aplicado' => true, 'rechazo' => null, 'origen_id' => 'bed-a3', 'destino_id' => null], mover($pdo, 'epi-2', 'alta'), 'resultado');
    igual(['maintenance', 'closed', 'aplicada'], [cama($pdo, 'bed-a3'), valor($pdo, "SELECT status FROM care_episodes WHERE care_episode_id = 'epi-2'"),
                                                  valor($pdo, "SELECT estado FROM ordenes_movimiento WHERE orden_id = 'ord-2'")], 'cama, episodio y orden');
});
prueba('DOM-01', 'Regla de dominio: A3 ocupada → cama_no_disponible; UCI pedida con cama general → cama_no_cumple_requisito; nada cambia', function () use ($rechazo) {
    $pdo = base();
    igual($rechazo('cama_no_disponible'), mover($pdo, 'epi-1', 'traslado', 'bed-a3'));
    igual($rechazo('cama_no_cumple_requisito'), mover($pdo, 'epi-3', 'traslado', 'bed-a2'));
    igual(['occupied', 'available', 'occupied'], [cama($pdo, 'bed-a1'), cama($pdo, 'bed-a2'), cama($pdo, 'bed-a4')], 'camas sin tocar');
});
prueba('DOM-02', 'Regla de dominio: alta sin motivo → alta_invalida; paciente sin cama → sin_cama; sin filas nuevas', function () use ($rechazo) {
    $pdo = base();
    igual([$rechazo('alta_invalida'), $rechazo('sin_cama')], [mover($pdo, 'epi-4', 'alta'), mover($pdo, 'epi-9', 'traslado', 'bed-a2')]);
    igual(4, (int) valor($pdo, 'SELECT COUNT(*) FROM bed_stays'), 'sin estancias nuevas');
});
prueba('APP-03', 'Guardas: episodio cerrado → 409; sin orden vigente de ese tipo → 409; rol no autorizado → 403', function () {
    $pdo = base();
    igual(['episodio_no_disponible', 'orden_requerida', 'acceso_denegado'], [
        errorDe(fn () => mover($pdo, 'epi-cerrado', 'alta'))->codigo,
        errorDe(fn () => mover($pdo, 'epi-2', 'traslado', 'bed-a2'))->codigo, // epi-2 solo tiene orden de alta
        errorDe(fn () => mover($pdo, 'epi-1', 'traslado', 'bed-a2', new Usuario('dr-luis', 'medico')))->codigo]);
});
prueba('PER-01', 'Error de persistencia a mitad de la transacción (falla el último UPDATE) → ErrorPersistencia y rollback completo', function () {
    $pdo = base();
    $pdo->exec("CREATE TRIGGER falla BEFORE UPDATE ON ordenes_movimiento BEGIN SELECT RAISE(ABORT, 'falla simulada'); END");
    igual(ErrorPersistencia::class, errorDe(fn () => mover($pdo, 'epi-1', 'traslado', 'bed-a2'))::class);
    igual([1, 'occupied', 'available', 4], [(int) valor($pdo, "SELECT ended_at IS NULL FROM bed_stays WHERE bed_stay_id = 'stay-1'"),
          cama($pdo, 'bed-a1'), cama($pdo, 'bed-a2'), (int) valor($pdo, 'SELECT COUNT(*) FROM bed_stays')], 'nada quedó a medias');
});
prueba('HTTP-01', 'Presentación: 201, 401, 400, 404, 405 y 409 con su código', function () {
    $api = new MovimientosControlador(caso(base()), ['enf-ana' => new Usuario('enf-ana', 'enfermeria')]);
    $ruta = '/api/v1/traslados-altas/movimientos';
    $ok = '{"episodio_id":"epi-1","tipo":"traslado","destino_id":"bed-a2"}';
    igual(201, $api->atender('POST', $ruta, 'enf-ana', $ok)[0], 'registro');
    igual([401, 400, 404, 405], [$api->atender('POST', $ruta, '', $ok)[0], $api->atender('POST', $ruta, 'enf-ana', '{')[0],
                                 $api->atender('POST', '/x', 'enf-ana', $ok)[0], $api->atender('GET', $ruta, 'enf-ana', '')[0]], 'errores');
    $r = $api->atender('POST', $ruta, 'enf-ana', '{"episodio_id":"epi-3","tipo":"traslado","destino_id":"bed-a3"}');
    igual([409, 'cama_no_disponible'], [$r[0], $r[1]['codigo']], '409');
});
prueba('ARQ-01', 'Dependencias: Dominio no usa otras capas ni PDO; Aplicación y Presentación no tienen SQL', function () {
    $codigo = fn (string $capa) => implode("\n", array_map('file_get_contents', glob(__DIR__ . "/../src/$capa/*.php")));
    igual(0, preg_match('/Aplicacion|Persistencia|Presentacion|PDO/', $codigo('Dominio')), 'Dominio');
    igual(0, preg_match('/\b(SELECT|INSERT|UPDATE|DELETE)\b/', $codigo('Aplicacion') . $codigo('Presentacion')), 'SQL fuera de Persistencia');
});

printf("\nTotal: %d  Pasan: %d  Fallan: %d\n", array_sum($conteo), $conteo['PASA'], $conteo['FALLA']);
exit($conteo['FALLA'] === 0 ? 0 : 1);
