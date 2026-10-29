<?php
declare(strict_types=1);

// Pruebas sin dependencias:  php tests/ejecutar.php   (usa SQLite en memoria como base de prueba)
use Traslados\Aplicacion\{ErrorPersistencia, RegistrarMovimiento, Usuario};
use Traslados\Persistencia\{EnMemoria, MovimientosPdo};
use Traslados\Presentacion\{Enrutador, MovimientosControlador, Peticion};

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
function almacen(PDO|EnMemoria $r): MovimientosPdo|EnMemoria { return $r instanceof PDO ? new MovimientosPdo($r) : $r; } // el mismo contrato con cualquiera
function caso(PDO|EnMemoria $r): RegistrarMovimiento {
    return new RegistrarMovimiento(almacen($r), almacen($r), fn () => new DateTimeImmutable('2026-10-27 09:00'), fn () => 'stay-nueva');
}
const MOV = '/api/v1/traslados-altas/movimientos';
function http(PDO|EnMemoria $r, string $metodo, string $ruta, string $usuario = 'enf-ana', string $cuerpo = ''): array {
    $usuarios = ['enf-ana' => new Usuario('enf-ana', 'enfermeria'), 'dr-luis' => new Usuario('dr-luis', 'medico')];
    $enrutador = new Enrutador(new MovimientosControlador(caso($r), $usuarios));
    return $enrutador->atender(new Peticion($metodo, $ruta, [], $usuario, $cuerpo));
}
function mover(PDO|EnMemoria $pdo, string $episodio, string $tipo, ?string $destino = null, ?Usuario $u = null): array {
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
prueba('PER-02', 'Doble en memoria que falla al aplicar → 500 por el Enrutador y nada cambia', function () {
    $m = EnMemoria::demo();
    $m->fallarAlAplicar = true;
    $r = http($m, 'POST', MOV, 'enf-ana', '{"episodio_id":"epi-1","tipo":"traslado","destino_id":"bed-a2"}');
    igual([500, 'error_persistencia'], [$r[0], $r[1]['codigo']], 'respuesta');
    igual(['occupied', 'available', 'stay-1'], [$m->cama('bed-a1')['status'], $m->cama('bed-a2')['status'], $m->estanciaAbierta('epi-1')['bed_stay_id']], 'nada quedó a medias');
});
prueba('REP-01', 'Contrato del Repository: PDO y memoria dan los mismos resultados y el mismo estado final', function () {
    $correr = function (PDO|EnMemoria $r): array {
        $repo = almacen($r);
        $resultados = [mover($r, 'epi-1', 'traslado', 'bed-a2'), mover($r, 'epi-2', 'alta'), mover($r, 'epi-3', 'traslado', 'bed-a2'), mover($r, 'epi-4', 'alta')];
        return [$resultados, array_map(fn ($id) => $repo->cama($id)['status'], ['bed-a1', 'bed-a2', 'bed-a3', 'bed-a4', 'bed-a5', 'bed-u1']),
                $repo->estanciaAbierta('epi-1')['bed_id'], $repo->estanciaAbierta('epi-2'), $repo->episodioAbierto('epi-2'), $repo->ordenVigente('epi-1', 'traslado'), $repo->ordenVigente('epi-4', 'alta')['orden_id']];
    };
    $pdo = $correr(base());
    igual(['maintenance', 'occupied', 'maintenance', 'occupied', 'occupied', 'available'], $pdo[1], 'estado esperado con PDO');
    igual($pdo, $correr(EnMemoria::demo()), 'memoria igual a PDO');
});
prueba('MVC-01', 'MVC con PDO: Enrutador → controlador → vista (201, 401, 400, 403, 404, 405, 409 y 422)', function () {
    $pdo = base();
    $ok = '{"episodio_id":"epi-1","tipo":"traslado","destino_id":"bed-a2"}';
    $estados = [http($pdo, 'POST', MOV, 'enf-ana', $ok)[0], http($pdo, 'POST', MOV, '', $ok)[0], http($pdo, 'POST', MOV, 'enf-ana', '{')[0], http($pdo, 'POST', MOV, 'dr-luis', $ok)[0],
                http($pdo, 'POST', '/x')[0], http($pdo, 'DELETE', MOV)[0],
                http($pdo, 'POST', MOV, 'enf-ana', '{"episodio_id":"epi-3","tipo":"traslado","destino_id":"bed-a3"}')[0],
                http($pdo, 'POST', MOV, 'enf-ana', '{"episodio_id":"epi-4","tipo":"alta"}')[0]];
    igual([201, 401, 400, 403, 404, 405, 409, 422], $estados, 'estados');
});
prueba('ARQ-01', 'Dependencias: Dominio sin otras capas; Aplicación sin Persistencia ni PDO; sin SQL ni reglas en la Presentación', function () {
    $codigo = fn (string $capa) => implode("\n", array_map('file_get_contents', glob(__DIR__ . "/../src/$capa/*.php")));
    igual(0, preg_match('/Aplicacion|Persistencia|Presentacion|PDO/', $codigo('Dominio')), 'Dominio');
    igual(0, preg_match('/Traslados\\\\Persistencia|\\bPDO/', $codigo('Aplicacion')), 'Aplicación depende solo de interfaces');
    igual(0, preg_match('/\b(SELECT|INSERT|UPDATE|DELETE)\b/', $codigo('Aplicacion') . $codigo('Presentacion')), 'SQL fuera de Persistencia');
    igual(0, preg_match('/\b(Traslado|Alta|CamaOrigen|CamaDestino|RequisitoCama|TipoAlta|MovimientoDeCama)\b/', $codigo('Presentacion')), 'reglas de negocio en la Presentación');
});

printf("\nTotal: %d  Pasan: %d  Fallan: %d\n", array_sum($conteo), $conteo['PASA'], $conteo['FALLA']);
exit($conteo['FALLA'] === 0 ? 0 : 1);
