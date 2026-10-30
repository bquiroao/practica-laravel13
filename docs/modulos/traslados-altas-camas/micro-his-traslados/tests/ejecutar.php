<?php
declare(strict_types=1);

// Pruebas sin dependencias:  php tests/ejecutar.php   (usa SQLite en memoria como base de prueba)
use Traslados\Aplicacion\{ErrorPersistencia, RegistrarMovimiento, Usuario};
use Traslados\Persistencia\{EnMemoria, MovimientosPdo};
use Traslados\Presentacion\{Enrutador, MovimientosControlador, Peticion, VistaJson};

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
$api = fn (PDO|EnMemoria $r) => new Enrutador(new MovimientosControlador(new RegistrarMovimiento(almacen($r), almacen($r)), ['enf-ana' => new Usuario('enf-ana', 'enfermeria')]));
$post = fn (Enrutador $api, string $cuerpo, ?string $clave = null) => $api->atender(new Peticion('POST', MOV, [], 'enf-ana', $cuerpo, $clave, 'req-00000001'));
$traslado = fn (string $e, string $d) => json_encode(['episodio_id' => $e, 'tipo' => 'traslado', 'destino_id' => $d]);
prueba('RES-01', 'Idempotency-Key (PDO y memoria): el reintento da 200 con el mismo resultado y no mueve otra cama; clave en otro movimiento → 409; clave corta → 400', function () use ($api, $post, $traslado) {
    foreach (['pdo' => $pdo = base(), 'memoria' => EnMemoria::demo()] as $nombre => $r) {
        $a = $api($r);
        [$primera, $reintento] = [$post($a, $traslado('epi-1', 'bed-a2'), 'mov-0800-epi1'), $post($a, $traslado('epi-1', 'bed-a2'), 'mov-0800-epi1')];
        igual([201, 200, 'bed-a2', true], [$primera[0], $reintento[0], $reintento[1]['destino_id'], $reintento[1]['repetida']], "$nombre reintento");
        igual('bed-a2', almacen($r)->estanciaAbierta('epi-1')['bed_id'], "$nombre: sigue una sola estancia abierta");
        igual([409, 400], [$post($a, '{"episodio_id":"epi-2","tipo":"alta"}', 'mov-0800-epi1')[0], $post($a, $traslado('epi-2', 'bed-a2'), 'corta')[0]], "$nombre clave ajena e inválida");
    }
    igual(1, (int) valor($pdo, 'SELECT COUNT(*) FROM claves_idempotencia'), 'una sola clave guardada');
});
prueba('CON-01', 'Contrato api/openapi.yaml: documenta cada código de error y cada estado que devuelve la API', function () {
    $contrato = (string) file_get_contents(__DIR__ . '/../api/openapi.yaml');
    $codigos = array_keys((new ReflectionClassConstant(VistaJson::class, 'ESTADOS'))->getValue());
    igual([], array_values(array_filter($codigos, fn ($c) => !preg_match("/\\b$c\\b/", $contrato))), 'códigos sin documentar');
    igual([], array_values(array_filter(['200', '201', '400', '401', '403', '409', '422', '500'], fn ($e) => !str_contains($contrato, "\"$e\":"))), 'estados sin documentar');
});
prueba('OBS-01', 'Observabilidad: la línea de log lleva id, método, ruta, estado, código y ms; no lleva episodio, cama ni usuario', function () use ($api, $traslado) {
    $p = new Peticion('POST', MOV, [], 'enf-ana', $traslado('epi-3', 'bed-a3'), null, 'req-00000001');
    $linea = json_decode(VistaJson::registro($p, $api(base())->atender($p), 1.234), true);
    igual(['req-00000001', 409, 'cama_no_disponible', 1.23], [$linea['id'], $linea['estado'], $linea['codigo'], $linea['ms']], 'campos');
    igual(0, preg_match('/epi-|bed-|enf-ana/', json_encode($linea)), 'sin datos del paciente');
});
prueba('MED-01', 'Frontera: la transacción escribe 5 tablas y solo 2 son de este módulo (3 de otros dueños)', function () {
    preg_match_all('/(?:UPDATE|INTO)\s+(\w+)/', (string) file_get_contents(__DIR__ . '/../src/Persistencia/MovimientosPdo.php'), $m);
    $tablas = array_values(array_unique($m[1]));
    sort($tablas);
    igual(['bed_stays', 'beds', 'care_episodes', 'claves_idempotencia', 'ordenes_movimiento'], $tablas, 'tablas escritas');
    igual(3, count(array_diff($tablas, ['claves_idempotencia', 'ordenes_movimiento'])), 'tablas de otros módulos');
});
$pdo = base();
for ($i = 0; $i < 200; $i++) {
    $pdo->exec("INSERT INTO local_patients VALUES ('p$i', 'N', 'N'); INSERT INTO care_episodes VALUES ('e$i', 'p$i', 'hospitalizacion', 'open', '2026-10-24 08:00', NULL);
      INSERT INTO beds VALUES ('o$i', 'ward-gen', 'O$i', 'general', 'occupied'), ('d$i', 'ward-gen', 'D$i', 'general', 'available');
      INSERT INTO bed_stays VALUES ('s$i', 'e$i', 'o$i', '2026-10-24 08:00', NULL, NULL); INSERT INTO ordenes_movimiento VALUES ('r$i', 'e$i', 'traslado', 'vigente', 'cualquiera', NULL, NULL)");
}
$tiempos = [];
for ($i = 0, $a = $api($pdo); $i < 200; $i++) { $t = hrtime(true); $post($a, $traslado("e$i", "d$i"), "traslado-$i-0000"); $tiempos[] = (hrtime(true) - $t) / 1e6; }
sort($tiempos);
$p95 = round($tiempos[189], 2);
prueba('RNF-03', "Rendimiento: p95 de 200 traslados por HTTP interno con SQLite = $p95 ms (meta < 2000 ms)", fn () => igual(true, $p95 < 2000 && (int) valor($pdo, "SELECT COUNT(*) FROM beds WHERE status = 'occupied' AND bed_id LIKE 'd%'") === 200, 'p95 y 200 camas ocupadas'));
prueba('ARQ-01', 'Dependencias: Dominio sin otras capas; Aplicación sin Persistencia ni PDO; sin SQL ni reglas en la Presentación', function () {
    $codigo = fn (string $capa) => implode("\n", array_map('file_get_contents', glob(__DIR__ . "/../src/$capa/*.php")));
    igual(0, preg_match('/Aplicacion|Persistencia|Presentacion|PDO/', $codigo('Dominio')), 'Dominio');
    igual(0, preg_match('/Traslados\\\\Persistencia|\\bPDO/', $codigo('Aplicacion')), 'Aplicación depende solo de interfaces');
    igual(0, preg_match('/\b(SELECT|INSERT|UPDATE|DELETE)\b/', $codigo('Aplicacion') . $codigo('Presentacion')), 'SQL fuera de Persistencia');
    igual(0, preg_match('/\b(Traslado|Alta|CamaOrigen|CamaDestino|RequisitoCama|TipoAlta|MovimientoDeCama)\b/', $codigo('Presentacion')), 'reglas de negocio en la Presentación');
});

printf("\nTotal: %d  Pasan: %d  Fallan: %d\n", array_sum($conteo), $conteo['PASA'], $conteo['FALLA']);
exit($conteo['FALLA'] === 0 ? 0 : 1);
