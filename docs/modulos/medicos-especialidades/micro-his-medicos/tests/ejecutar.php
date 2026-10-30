<?php
declare(strict_types=1);

// Pruebas sin dependencias:  php tests/ejecutar.php   (usa SQLite en memoria como base de prueba)
use Medicos\Aplicacion\{AsociarEspecialidad, ConsultarDisponibilidad, ErrorPersistencia, RegistrarMedico, Usuario};
use Medicos\Dominio\{Agenda, ErrorDominio};
use Medicos\Persistencia\{EnMemoria, MedicosPdo};
use Medicos\Presentacion\{Enrutador, MedicosControlador, Peticion};
require __DIR__ . '/../bootstrap.php';
$conteo = ['PASA' => 0, 'FALLA' => 0];
function prueba(string $id, string $que, callable $cuerpo): void {
    try { $cuerpo(); $estado = 'PASA'; $detalle = ''; } catch (\Throwable $e) { $estado = 'FALLA'; $detalle = ' → ' . $e->getMessage(); }
    $GLOBALS['conteo'][$estado]++; printf("%-7s %-5s %s%s\n", $id, $estado, $que, $detalle); }
function igual(mixed $esperado, mixed $real, string $que = 'valor'): void { if ($esperado !== $real) { throw new \RuntimeException("$que: esperado " . json_encode($esperado) . ', obtenido ' . json_encode($real)); } }
function codigoDe(callable $f): string { try { $f(); } catch (ErrorDominio $e) { return $e->codigo; } catch (\Throwable $e) { return $e::class; } return 'sin error'; }
function base(): PDO { $pdo = new PDO('sqlite::memory:'); $pdo->exec((string) file_get_contents(__DIR__ . '/../sql/esquema.sql')); return $pdo; }
function valor(PDO $pdo, string $sql): mixed { return $pdo->query($sql)->fetchColumn(); }
$hoy = fn () => '2026-10-28';
$ids = function () { static $n = 0; return 'id-' . ++$n; };
[$admin, $recepcion] = [new Usuario('adm-1', 'admin'), new Usuario('rec-1', 'recepcion')];
$repo = fn (PDO|MedicosPdo|EnMemoria $b) => $b instanceof PDO ? new MedicosPdo($b) : $b;   // los casos de uso aceptan cualquiera de los dos adaptadores
$registrar = fn ($b) => new RegistrarMedico($repo($b), $repo($b), $hoy, $ids);
$asociar = fn ($b) => new AsociarEspecialidad($r = $repo($b), $r, $ids);
$consultar = fn ($b) => new ConsultarDisponibilidad($r = $repo($b), $r, $hoy);
$app = fn (EnMemoria $m) => new Enrutador(new MedicosControlador($registrar($m), $asociar($m), $consultar($m), ['adm' => $admin, 'rec' => $recepcion]));

prueba('APP-01', 'Camino feliz registro: Luis con colegiado 33333 y Pediatría principal → 1 perfil y 1 especialidad en la misma transacción', function () use ($registrar, $admin) {
    $pdo = base(); $r = $registrar($pdo)->ejecutar(['usuario_id' => 'u-luis', 'colegiado' => '33333', 'especialidad_id' => 'esp-ped'], $admin);
    igual(['33333|active|2026-10-28', 'esp-ped|1|2026-10-28'], [valor($pdo, "SELECT license_number || '|' || status || '|' || valid_from FROM doctor_profiles WHERE local_user_ref_id = 'u-luis'"),
          valor($pdo, "SELECT specialty_id || '|' || is_primary || '|' || valid_from FROM doctor_specialties WHERE doctor_profile_id = '{$r['medico_id']}'")], 'filas');
});
prueba('APP-02', 'Camino feliz disponibilidad (CA-07): horario 08:00–10:00 y cita a las 08:00 → 08:30, 09:00, 09:30; la cancelada no ocupa; Eva (inactiva) no aparece', function () use ($consultar, $recepcion) {
    $f = $consultar(base())->ejecutar('MI', '2026-11-02', '2026-11-02', $recepcion);
    igual(['med-ana|2026-11-02 08:30', 'med-ana|2026-11-02 09:00', 'med-ana|2026-11-02 09:30'], array_map(fn ($x) => $x['medico_id'] . '|' . $x['inicio'], $f));
    igual('Dra. Ana Gómez', $f[0]['medico'], 'nombre desde local_user_refs');
    igual(['2026-11-02 08:00', '2026-11-02 08:30'], Agenda::franjas('2026-11-02', [['08:00:00', '09:00:00']], []), 'horas con segundos, como las devuelve PostgreSQL');
    igual(['2026-11-09 09:00', '2026-11-09 09:30'], array_column($consultar(base())->ejecutar('MI', '2026-11-09', '2026-11-09', $recepcion), 'inicio'), 'cita 08:15–08:45 tapa 08:00 y 08:30');
});
prueba('DOM-01', 'Regla de dominio (CA-04, CA-05): MI otra vez o segunda principal → especialidad_duplicada; Cardiología inactiva → especialidad_invalida; PED no principal sí', function () use ($asociar, $admin) {
    $pdo = base(); $pide = fn (array $e) => codigoDe(fn () => $asociar($pdo)->ejecutar('med-ana', $e, $admin));
    igual(['especialidad_duplicada', 'especialidad_duplicada', 'especialidad_invalida', 'sin error'], [
        $pide(['especialidad_id' => 'esp-mi', 'desde' => '2026-11-01']), $pide(['especialidad_id' => 'esp-ped', 'desde' => '2026-11-01', 'principal' => true]),
        $pide(['especialidad_id' => 'esp-car', 'desde' => '2026-11-01']), $pide(['especialidad_id' => 'esp-ped', 'desde' => '2026-11-01'])]);
    igual(2, (int) valor($pdo, "SELECT COUNT(*) FROM doctor_specialties WHERE doctor_profile_id = 'med-ana'"), 'solo se guardó la válida');
});
prueba('DOM-02', 'Regla de dominio (CA-02, CA-10): colegiado repetido o usuario que ya es médico → medico_duplicado; 25 días o rango pasado → rango_invalido', function () use ($registrar, $consultar, $admin) {
    $pdo = base(); igual(['medico_duplicado', 'medico_duplicado', 'rango_invalido', 'rango_invalido'], [
        codigoDe(fn () => $registrar($pdo)->ejecutar(['usuario_id' => 'u-luis', 'colegiado' => '11111'], $admin)),
        codigoDe(fn () => $registrar($pdo)->ejecutar(['usuario_id' => 'u-ana', 'colegiado' => '99999'], $admin)),
        codigoDe(fn () => $consultar($pdo)->ejecutar('MI', '2026-11-01', '2026-11-26', $admin)), codigoDe(fn () => $consultar($pdo)->ejecutar('MI', '2026-10-01', '2026-10-02', $admin))]);
    igual(2, (int) valor($pdo, 'SELECT COUNT(*) FROM doctor_profiles'), 'sin perfiles nuevos');
});
prueba('APP-03', 'Guardas (CA-01): recepción no registra → acceso_denegado; usuario revocado → usuario_no_disponible; médico inexistente → medico_no_encontrado', function () use ($registrar, $asociar, $admin, $recepcion) {
    $pdo = base(); igual(['acceso_denegado', 'usuario_no_disponible', 'medico_no_encontrado'], [
        codigoDe(fn () => $registrar($pdo)->ejecutar(['usuario_id' => 'u-luis', 'colegiado' => '33333'], $recepcion)),
        codigoDe(fn () => $registrar($pdo)->ejecutar(['usuario_id' => 'u-rev', 'colegiado' => '33333'], $admin)),
        codigoDe(fn () => $asociar($pdo)->ejecutar('med-x', ['especialidad_id' => 'esp-ped', 'desde' => '2026-11-01'], $admin))]);
});
prueba('PER-01', 'Error de persistencia: falla el INSERT de la especialidad → ErrorPersistencia y rollback (el perfil tampoco queda)', function () use ($registrar, $admin) {
    $pdo = base(); $pdo->exec("CREATE TRIGGER falla BEFORE INSERT ON doctor_specialties BEGIN SELECT RAISE(ABORT, 'falla simulada'); END");
    igual(ErrorPersistencia::class, codigoDe(fn () => $registrar($pdo)->ejecutar(['usuario_id' => 'u-luis', 'colegiado' => '33333', 'especialidad_id' => 'esp-ped'], $admin)));
    igual(0, (int) valor($pdo, "SELECT COUNT(*) FROM doctor_profiles WHERE local_user_ref_id = 'u-luis'"), 'sin perfil a medias');
});
prueba('REP-01', 'Contrato Repository: PDO y memoria responden igual a la misma secuencia (registro, asociación, rechazo, consultas)', function () use ($hoy, $asociar, $admin) {
    $vistas = [];
    foreach (['pdo' => new MedicosPdo(base()), 'memoria' => EnMemoria::desdePdo(base())] as $nombre => $r) {
        $n = 0; $id = (new RegistrarMedico($r, $r, $hoy, function () use (&$n) { return 'rep-' . ++$n; }))->ejecutar(['usuario_id' => 'u-luis', 'colegiado' => '33333', 'especialidad_id' => 'esp-ped', 'desde' => '2026-11-01'], $admin)['medico_id'];
        [$asociar($r)->ejecutar('med-ana', ['especialidad_id' => 'esp-ped', 'desde' => '2026-11-01'], $admin), codigoDe(fn () => $asociar($r)->ejecutar('med-ana', ['especialidad_id' => 'esp-mi', 'desde' => '2026-11-01'], $admin))];
        $vistas[$nombre] = json_encode([$r->usuarioActivo('u-rev'), $r->especialidadActiva('esp-car'), $r->colegiadoRegistrado('33333'), $r->tienePerfilActivo('u-luis'), $r->medicoActivo('med-eva'),
            $r->asignaciones('med-ana'), $r->asignaciones($id), $r->medicosConEspecialidad('PED'), $r->medicosConEspecialidad('MI'), $r->horarios(['med-ana', 'med-eva']), $r->citasActivas(['med-ana'], '2026-11-02', '2026-11-09')]);
    }
    igual($vistas['pdo'], $vistas['memoria'], 'pdo frente a memoria');
});
prueba('MVC-01', 'Enrutador → controlador → vista: 201, 200, 401, 400, 403, 404, 405, 409 y 422', function () use ($app) {
    $a = $app(EnMemoria::desdePdo(base()));
    $pide = fn (string $m, string $ruta, string $u = 'adm', string $cuerpo = '', array $q = ['especialidad' => 'MI', 'desde' => '2026-11-02', 'hasta' => '2026-11-02']) => $a->atender(new Peticion($m, $ruta, $q, $u, $cuerpo))[0];
    igual([201, 201, 200], [$pide('POST', '/api/v1/medicos', 'adm', '{"usuario_id":"u-luis","colegiado":"33333"}'),
        $pide('POST', '/api/v1/medicos/med-ana/especialidades', 'adm', '{"especialidad_id":"esp-ped","desde":"2026-11-01"}'), $pide('GET', '/api/v1/disponibilidad', 'rec')], 'éxitos');
    igual([401, 400, 403, 404, 404, 405, 409, 422], [$pide('GET', '/api/v1/disponibilidad', ''), $pide('POST', '/api/v1/medicos', 'adm', '{'), $pide('POST', '/api/v1/medicos', 'rec', '{}'),
        $pide('GET', '/x'), $pide('POST', '/api/v1/medicos/med-x/especialidades', 'adm', '{"especialidad_id":"esp-ped","desde":"2026-11-01"}'), $pide('DELETE', '/api/v1/medicos'),
        $pide('POST', '/api/v1/medicos', 'adm', '{"usuario_id":"u-eva","colegiado":"22222"}'), $pide('GET', '/api/v1/disponibilidad', 'rec', '', ['especialidad' => 'MI', 'desde' => 'x', 'hasta' => 'x'])], 'errores');
});
prueba('PER-02', 'Doble en memoria que falla al guardar → 500 error_persistencia por HTTP y no queda el médico', function () use ($app) {
    $m = EnMemoria::desdePdo(base()); $m->fallarAlGuardar = true;
    $r = $app($m)->atender(new Peticion('POST', '/api/v1/medicos', [], 'adm', '{"usuario_id":"u-luis","colegiado":"33333"}'));
    igual([500, 'error_persistencia', false], [$r[0], $r[1]['codigo'], $m->colegiadoRegistrado('33333')]);
});
prueba('ARQ-01', 'Dependencias: Dominio aislado; Aplicación sin Persistencia ni SQL; controlador sin SQL ni reglas', function () {
    $codigo = fn (string $capa) => implode("\n", array_map('file_get_contents', glob(__DIR__ . "/../src/$capa/*.php")));
    igual(0, preg_match('/Aplicacion|Persistencia|Presentacion|PDO/', $codigo('Dominio')), 'Dominio');
    igual(0, preg_match('/\b(SELECT|INSERT|UPDATE|DELETE)\b/', $codigo('Aplicacion') . $codigo('Presentacion')), 'SQL fuera de Persistencia');
    igual(0, preg_match('/Medicos\\\\Persistencia|\\\\PDO\b/', $codigo('Aplicacion')), 'Aplicación → Persistencia');
    igual(0, preg_match('/Vigencia|Agenda|ReglasEspecialidad|active|scheduled|MINUTOS/', (string) file_get_contents(__DIR__ . '/../src/Presentacion/MedicosControlador.php')), 'reglas en el controlador');
});

printf("\nTotal: %d  Pasan: %d  Fallan: %d\n", array_sum($conteo), $conteo['PASA'], $conteo['FALLA']);
exit($conteo['FALLA'] === 0 ? 0 : 1);
