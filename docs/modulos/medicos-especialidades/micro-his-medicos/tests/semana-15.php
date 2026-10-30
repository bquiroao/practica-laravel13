<?php
declare(strict_types=1);

// Semana 15: pruebas unitarias (U), de caja blanca (W) y de caja negra (B) trazadas a RF/RNF.  php tests/semana-15.php
// Un defecto conocido (acta de la semana 13) se prueba con defecto(): ABIERTO mientras se reproduce; si deja de reproducirse, FALLA y pide pasarlo a prueba normal.
use Medicos\Aplicacion\{AsociarEspecialidad, ConsultarDisponibilidad, RegistrarMedico, Usuario};
use Medicos\Dominio\{Agenda, Asignacion, ErrorDominio, ReglasEspecialidad, Vigencia};
use Medicos\Persistencia\MedicosPdo;
use Medicos\Presentacion\{Enrutador, MedicosControlador, Peticion};

require __DIR__ . '/../bootstrap.php';

$conteo = ['PASA' => 0, 'FALLA' => 0, 'ABIERTO' => 0];
function prueba(string $id, string $trazas, string $que, callable $cuerpo): void {
    try { $cuerpo(); $estado = 'PASA'; $detalle = ''; } catch (\Throwable $e) { $estado = 'FALLA'; $detalle = ' → ' . $e->getMessage(); }
    $GLOBALS['conteo'][$estado]++; printf("%-5s %-8s %-18s %s%s\n", $id, $estado, $trazas, $que, $detalle);
}
function defecto(string $id, string $hallazgo, string $trazas, string $que, callable $cuerpo): void {
    try { $cuerpo(); $estado = 'ABIERTO'; $detalle = " ($hallazgo)"; } catch (\Throwable $e) { $estado = 'FALLA'; $detalle = " → ya no se reproduce ($hallazgo): conviértalo en prueba normal. " . $e->getMessage(); }
    $GLOBALS['conteo'][$estado]++; printf("%-5s %-8s %-18s %s%s\n", $id, $estado, $trazas, $que, $detalle);
}
function igual(mixed $esperado, mixed $real, string $que = 'valor'): void { if ($esperado !== $real) { throw new \RuntimeException("$que: esperado " . json_encode($esperado) . ', obtenido ' . json_encode($real)); } }
function codigoDe(callable $f): string { try { $f(); } catch (ErrorDominio $e) { return $e->codigo; } return 'sin error'; }
function base(): PDO { $pdo = new PDO('sqlite::memory:'); $pdo->exec((string) file_get_contents(__DIR__ . '/../sql/esquema.sql')); return $pdo; }
function cuantos(PDO $pdo, string $tabla): int { return (int) $pdo->query("SELECT COUNT(*) FROM $tabla")->fetchColumn(); }
function api(PDO $pdo): Enrutador {
    static $n = 0; $ids = function () use (&$n) { return 'id-' . ++$n; }; $hoy = fn () => '2026-10-28'; $r = new MedicosPdo($pdo);
    return new Enrutador(new MedicosControlador(new RegistrarMedico($r, $r, $hoy, $ids), new AsociarEspecialidad($r, $r, $ids), new ConsultarDisponibilidad($r, $r, $hoy),
        ['adm' => new Usuario('adm-1', 'admin'), 'rec' => new Usuario('rec-1', 'recepcion'), 'med' => new Usuario('u-ana', 'medico')]));
}
function http(Enrutador $e, string $metodo, string $ruta, array $cuerpo = [], string $usuario = 'adm', array $q = []): array { return $e->atender(new Peticion($metodo, $ruta, $q, $usuario, json_encode($cuerpo))); }
function franjas(array $r): array { return array_map(fn ($f) => substr($f['inicio'], 5), $r[1]['franjas']); }
$lunes = ['especialidad' => 'MI', 'desde' => '2026-11-02', 'hasta' => '2026-11-02'];

// ── Unitarias ─────────────────────────────────────────────────────────────────
prueba('U-01', 'RF-03, RF-07', 'Vigencia [desde, hasta): fechas imposibles o invertidas se rechazan; el borde «hasta» ya no está vigente', function () {
    foreach ([['2026-02-30', null], ['2026-13-01', null], ['2026-11-01', '2026-11-01'], ['2026-11-01', '2026-10-01']] as [$d, $h]) { igual('vigencia_invalida', codigoDe(fn () => new Vigencia($d, $h)), "$d/$h"); }
    $v = new Vigencia('2026-01-01', '2026-06-01');
    igual([false, true, true, false], [$v->incluye('2025-12-31'), $v->incluye('2026-01-01'), $v->incluye('2026-05-31'), $v->incluye('2026-06-01')]);
});
prueba('U-02', 'RF-03, RF-07', 'Especialidades: un período pegado a otro no se cruza; el mismo código o dos principales a la vez, sí', function () {
    $a = new Asignacion('esp-mi', new Vigencia('2026-01-01', '2026-06-01'), true);
    $nueva = fn (string $esp, string $d, ?string $h, bool $p) => new Asignacion($esp, new Vigencia($d, $h), $p);
    ReglasEspecialidad::exigirAsignable([$a], $nueva('esp-mi', '2026-06-01', null, true));      // empieza justo cuando termina la otra
    ReglasEspecialidad::exigirAsignable([$a], $nueva('esp-mi', '2025-06-01', '2026-01-01', true)); // termina justo cuando empieza la otra
    ReglasEspecialidad::exigirAsignable([$a], $nueva('esp-ped', '2026-03-01', null, false));    // otra especialidad, no principal
    igual('especialidad_duplicada', codigoDe(fn () => ReglasEspecialidad::exigirAsignable([$a], $nueva('esp-mi', '2026-05-31', null, false))), 'mismo código');
    igual('especialidad_duplicada', codigoDe(fn () => ReglasEspecialidad::exigirAsignable([$a], $nueva('esp-ped', '2026-05-31', null, true))), 'dos principales');
});
prueba('U-03', 'RF-06, RF-07', 'Rango de consulta: hoy y 14 días se aceptan; ayer, 15 días, invertido o con formato malo → rango_invalido', function () {
    Agenda::exigirRango('2026-10-28', '2026-10-28', '2026-10-28'); Agenda::exigirRango('2026-10-28', '2026-11-11', '2026-10-28');
    foreach ([['2026-10-27', '2026-10-28'], ['2026-10-28', '2026-11-12'], ['2026-10-29', '2026-10-28'], ['2026-10-28', 'mañana'], ['', '']] as [$d, $h]) { igual('rango_invalido', codigoDe(fn () => Agenda::exigirRango($d, $h, '2026-10-28')), "$d/$h"); }
});
prueba('U-04', 'RF-06', 'Agenda::franjas: una cita que empieza a mitad de franja tapa las dos que toca; una pegada al final no tapa', function () {
    $horario = [['08:00', '10:00']];
    igual(['2026-11-09 08:00', '2026-11-09 08:30', '2026-11-09 09:00', '2026-11-09 09:30'], Agenda::franjas('2026-11-09', $horario, []));
    igual(['2026-11-09 09:00', '2026-11-09 09:30'], Agenda::franjas('2026-11-09', $horario, [['2026-11-09 08:15', '2026-11-09 08:45']]));
    igual(['2026-11-09 08:00', '2026-11-09 09:00', '2026-11-09 09:30'], Agenda::franjas('2026-11-09', $horario, [['2026-11-09 08:30', '2026-11-09 09:00']]));
    igual([], Agenda::franjas('2026-11-09', [], []), 'sin horario ese día');
});

// ── Caja blanca (ramas del código) ────────────────────────────────────────────
prueba('W-01', 'RF-01, RF-07', 'RegistrarMedico recorre sus guardas en orden y ninguna deja filas: rol → colegiado vacío → usuario revocado → duplicados → especialidad inactiva', function () {
    $pdo = base(); $r = new MedicosPdo($pdo); $reg = new RegistrarMedico($r, $r, fn () => '2026-10-28', fn () => 'x' . random_int(1000, 9999));
    [$adm, $rec] = [new Usuario('adm-1', 'admin'), new Usuario('rec-1', 'recepcion')]; $antes = [cuantos($pdo, 'doctor_profiles'), cuantos($pdo, 'doctor_specialties')];
    igual('acceso_denegado', codigoDe(fn () => $reg->ejecutar(['usuario_id' => 'u-luis', 'colegiado' => '3'], $rec)));
    igual('usuario_no_disponible', codigoDe(fn () => $reg->ejecutar(['usuario_id' => 'u-luis', 'colegiado' => '  '], $adm)), 'colegiado vacío');
    igual('usuario_no_disponible', codigoDe(fn () => $reg->ejecutar(['usuario_id' => 'u-rev', 'colegiado' => '3'], $adm)), 'usuario revocado');
    igual('medico_duplicado', codigoDe(fn () => $reg->ejecutar(['usuario_id' => 'u-luis', 'colegiado' => '11111'], $adm)), 'colegiado repetido');
    igual('medico_duplicado', codigoDe(fn () => $reg->ejecutar(['usuario_id' => 'u-ana', 'colegiado' => '3'], $adm)), 'usuario con perfil activo');
    igual('especialidad_invalida', codigoDe(fn () => $reg->ejecutar(['usuario_id' => 'u-luis', 'colegiado' => '3', 'especialidad_id' => 'esp-car'], $adm)), 'especialidad inactiva');
    igual($antes, [cuantos($pdo, 'doctor_profiles'), cuantos($pdo, 'doctor_specialties')], 'ninguna guarda dejó filas');
});
prueba('W-02', 'RF-03, RF-07', 'AsociarEspecialidad: médico inactivo, especialidad inactiva, vigencia mala y cruce se rechazan; un período contiguo se guarda', function () {
    $pdo = base(); $r = new MedicosPdo($pdo); $aso = new AsociarEspecialidad($r, $r, fn () => 'ds-' . random_int(1000, 9999)); $adm = new Usuario('adm-1', 'admin');
    igual('medico_no_encontrado', codigoDe(fn () => $aso->ejecutar('med-eva', ['especialidad_id' => 'esp-ped', 'desde' => '2026-11-01'], $adm)));
    igual('especialidad_invalida', codigoDe(fn () => $aso->ejecutar('med-ana', ['especialidad_id' => 'esp-car', 'desde' => '2026-11-01'], $adm)));
    igual('vigencia_invalida', codigoDe(fn () => $aso->ejecutar('med-ana', ['especialidad_id' => 'esp-ped', 'desde' => '2026-11-31'], $adm)));
    igual('especialidad_duplicada', codigoDe(fn () => $aso->ejecutar('med-ana', ['especialidad_id' => 'esp-mi', 'desde' => '2026-11-01'], $adm)), 'MI sigue vigente sin fecha final');
    igual(1, cuantos($pdo, 'doctor_specialties WHERE doctor_profile_id = \'med-ana\''), 'nada se guardó');
    $aso->ejecutar('med-ana', ['especialidad_id' => 'esp-ped', 'desde' => '2026-11-01', 'hasta' => '2026-12-01'], $adm);
    igual(2, cuantos($pdo, 'doctor_specialties WHERE doctor_profile_id = \'med-ana\''), 'la válida sí');
});
prueba('W-03', 'RF-02, RF-06', 'ConsultarDisponibilidad filtra: médico inactivo fuera, especialidad vencida fuera ese día, cita cancelada no ocupa y la confirmada sí', function () {
    $pdo = base(); $c = fn () => api($pdo);
    igual(['11-02 08:30', '11-02 09:00', '11-02 09:30'], franjas(http($c(), 'GET', '/api/v1/disponibilidad', [], 'rec', ['especialidad' => 'MI', 'desde' => '2026-11-02', 'hasta' => '2026-11-02'])), 'med-eva (inactiva) no aparece; la cita de 08:00 ocupa');
    igual(['11-09 09:00', '11-09 09:30'], franjas(http($c(), 'GET', '/api/v1/disponibilidad', [], 'rec', ['especialidad' => 'MI', 'desde' => '2026-11-09', 'hasta' => '2026-11-09'])), 'la confirmada de 08:15 tapa dos franjas');
    $pdo->exec("UPDATE doctor_specialties SET valid_to = '2026-11-02' WHERE doctor_specialty_id = 'ds-1'");   // vigencia [desde, hasta): el 2 de noviembre ya no cuenta
    igual([], franjas(http($c(), 'GET', '/api/v1/disponibilidad', [], 'rec', ['especialidad' => 'MI', 'desde' => '2026-11-02', 'hasta' => '2026-11-02'])), 'especialidad vencida');
});

// ── Caja negra (solo la API HTTP) ─────────────────────────────────────────────
prueba('B-01', 'RF-01, RF-07', 'POST /medicos: administración registra (201); el mismo colegiado → 409 medico_duplicado; cuerpo que no es JSON → 400', function () {
    $pdo = base(); $e = api($pdo);
    [$estado, $c] = http($e, 'POST', '/api/v1/medicos', ['usuario_id' => 'u-luis', 'colegiado' => '33333', 'especialidad_id' => 'esp-ped']); igual([201, 'esp-ped'], [$estado, $c['especialidad_principal']]);
    $otro = http($e, 'POST', '/api/v1/medicos', ['usuario_id' => 'u-ana', 'colegiado' => '33333']); igual([409, 'medico_duplicado'], [$otro[0], $otro[1]['codigo']]);
    igual(400, $e->atender(new Peticion('POST', '/api/v1/medicos', [], 'adm', 'esto no es json'))[0]);
    igual([3, 3], [cuantos($pdo, 'doctor_profiles'), cuantos($pdo, 'doctor_specialties')], 'sumó un perfil y su especialidad principal, y nada más');
});
prueba('B-02', 'RNF-01', 'Seguridad: sin sesión 401; recepción y médico no escriben (403) pero sí consultan (200); ruta desconocida 404; método no permitido 405', function () use ($lunes) {
    $e = api(base()); $cuerpo = ['usuario_id' => 'u-luis', 'colegiado' => '3'];
    igual(401, http($e, 'POST', '/api/v1/medicos', $cuerpo, 'nadie')[0]);
    foreach (['rec', 'med'] as $u) { igual(403, http($e, 'POST', '/api/v1/medicos', $cuerpo, $u)[0], "$u registra"); igual(403, http($e, 'POST', '/api/v1/medicos/med-ana/especialidades', ['especialidad_id' => 'esp-ped', 'desde' => '2026-11-01'], $u)[0], "$u asocia"); igual(200, http($e, 'GET', '/api/v1/disponibilidad', [], $u, $lunes)[0], "$u consulta"); }
    igual(404, http($e, 'GET', '/api/v1/pacientes')[0]); igual(405, http($e, 'DELETE', '/api/v1/medicos')[0], 'DELETE /medicos'); igual(405, http($e, 'PUT', '/api/v1/disponibilidad')[0], 'PUT /disponibilidad');
});
prueba('B-03', 'RF-03, RF-07', 'POST /medicos/{id}/especialidades: especialidad inactiva 422, médico inexistente 404, principal repetida 409, contigua 201', function () {
    $e = api(base()); $ruta = '/api/v1/medicos/med-ana/especialidades';
    igual([422, 'especialidad_invalida'], (fn ($r) => [$r[0], $r[1]['codigo']])(http($e, 'POST', $ruta, ['especialidad_id' => 'esp-car', 'desde' => '2026-11-01'])));
    igual(404, http($e, 'POST', '/api/v1/medicos/med-nadie/especialidades', ['especialidad_id' => 'esp-ped', 'desde' => '2026-11-01'])[0]);
    igual([409, 'especialidad_duplicada'], (fn ($r) => [$r[0], $r[1]['codigo']])(http($e, 'POST', $ruta, ['especialidad_id' => 'esp-ped', 'desde' => '2026-11-01', 'principal' => true])));
    igual(201, http($e, 'POST', $ruta, ['especialidad_id' => 'esp-ped', 'desde' => '2026-11-01', 'principal' => false])[0]);
});
prueba('B-04', 'RF-06', 'GET /disponibilidad (CA-07): horario 08:00–10:00 con cita a las 08:00 → 08:30, 09:00 y 09:30, con total, nombre y orden por nombre', function () use ($lunes) {
    [$estado, $c] = http(api(base()), 'GET', '/api/v1/disponibilidad', [], 'rec', $lunes);
    igual([200, 3, 'Dra. Ana Gómez', 'med-ana'], [$estado, $c['total'], $c['franjas'][0]['medico'], $c['franjas'][0]['medico_id']]);
    $pdo = base(); $pdo->exec("INSERT INTO doctor_profiles VALUES ('med-luis', 'u-luis', '33333', 'active', '2026-01-01', NULL); INSERT INTO doctor_specialties VALUES ('ds-3', 'med-luis', 'esp-mi', 0, '2026-01-01', NULL, 'active'); INSERT INTO horarios_medico VALUES ('med-luis', 1, '09:00', '09:30')");
    igual(['med-luis', 'med-ana', 'med-ana', 'med-ana'], array_column(http(api($pdo), 'GET', '/api/v1/disponibilidad', [], 'rec', $lunes)[1]['franjas'], 'medico_id'), 'con dos médicos salen por orden alfabético del nombre');
});
prueba('B-05', 'RF-06, RF-07', 'GET /disponibilidad (CA-10): rango de 25 días o en el pasado → 422 rango_invalido, y falta de parámetros también', function () {
    $e = api(base());
    foreach ([['2026-11-02', '2026-11-27'], ['2026-10-01', '2026-10-02'], ['', '']] as [$d, $h]) {
        $r = http($e, 'GET', '/api/v1/disponibilidad', [], 'rec', ['especialidad' => 'MI', 'desde' => $d, 'hasta' => $h]); igual([422, 'rango_invalido'], [$r[0], $r[1]['codigo']], "$d/$h");
    }
});

// ── Defectos conocidos (acta de la semana 13) ─────────────────────────────────
defecto('D-01', 'RT-01', 'RF-04, RF-06', 'Horario que termina a las 09:45: se ofrece la franja 09:30–10:00, que acaba fuera del horario', function () {
    $pdo = base(); $pdo->exec("UPDATE horarios_medico SET fin = '09:45' WHERE doctor_profile_id = 'med-ana'");
    igual(['11-02 08:30', '11-02 09:00', '11-02 09:30'], franjas(http(api($pdo), 'GET', '/api/v1/disponibilidad', [], 'rec', ['especialidad' => 'MI', 'desde' => '2026-11-02', 'hasta' => '2026-11-02'])));
});
defecto('D-02', 'RT-02', 'RF-02, RF-06', 'Perfil del médico vencido (valid_to pasado) sigue apareciendo con franjas', function () {
    $pdo = base(); $pdo->exec("UPDATE doctor_profiles SET valid_to = '2026-06-01' WHERE doctor_profile_id = 'med-ana'");
    igual(3, http(api($pdo), 'GET', '/api/v1/disponibilidad', [], 'rec', ['especialidad' => 'MI', 'desde' => '2026-11-02', 'hasta' => '2026-11-02'])[1]['total']);
});
defecto('D-03', 'RT-03', 'RF-07', 'Un «hasta» numérico (5) en la asociación lanza TypeError en vez de responder con un código del contrato', function () {
    try { http(api(base()), 'POST', '/api/v1/medicos/med-ana/especialidades', ['especialidad_id' => 'esp-ped', 'desde' => '2026-11-01', 'hasta' => 5]); } catch (\TypeError) { return; }
    throw new \RuntimeException('no lanzó TypeError');
});

printf("\nTotal: %d  Pasan: %d  Fallan: %d  Defectos abiertos: %d\n", array_sum($conteo), $conteo['PASA'], $conteo['FALLA'], $conteo['ABIERTO']);
exit($conteo['FALLA'] === 0 ? 0 : 1);
