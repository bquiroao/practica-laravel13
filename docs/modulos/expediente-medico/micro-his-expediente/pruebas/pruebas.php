<?php
declare(strict_types=1);

// Camino feliz, reglas de dominio y error de persistencia (bases SQLite de prueba y un doble), más la regla de dependencias. Datos ficticios.
require __DIR__ . '/../bootstrap.php';

use MicroHis\Expediente\Aplicacion\{ErrorPersistencia, IndiceCentral, Reloj, ServicioExpediente};
use MicroHis\Expediente\Persistencia\HospitalPdo;
use MicroHis\Expediente\Presentacion\ControladorExpediente;

$fallos = 0;
function prueba(string $nombre, bool $ok): void { global $fallos; $fallos += $ok ? 0 : 1; echo ($ok ? 'OK    ' : 'FALLA ') . "$nombre\n"; }
function base(string $esquema, string $datos): PDO { $db = new PDO('sqlite::memory:'); $db->exec(file_get_contents(__DIR__ . "/../database/$esquema") . $datos); return $db; }
// p1 activo y vinculado al MPI, con consentimiento hasta t=5000; p2 activo sin vínculo; p3 inactivo; p4 vinculado sin consentimiento. m1 atiende a p1, p2 y p4.
$hospital = fn () => base('schema.sql', "INSERT INTO local_patients VALUES ('p1', 'g1', 'active', 'linked'), ('p2', 'g2', 'active', 'pending'), ('p3', 'g3', 'inactive', 'linked'),
    ('p4', 'g4', 'active', 'linked'); INSERT INTO care_assignments VALUES ('p1', 'm1', 'active'), ('p2', 'm1', 'active'), ('p4', 'm1', 'active');");
$central = fn () => base('schema-central.sql', "INSERT INTO consents VALUES ('g1', 'h1', 0, 5000, 'active');
    INSERT INTO mpi_records VALUES ('g1', 'h1', 'EXP-p1'), ('g1', 'h2', 'EXP-H2-77');");
$reloj = new class implements Reloj { public int $t = 1000; public function ahora(): int { return $this->t; } };
$pide = fn (string $p, string $m = 'm1', bool $longitudinal = false) => ['paciente' => $p, 'profesional' => $m, 'longitudinal' => $longitudinal];

// 1. Camino feliz, de la presentación a las bases de prueba.
$app = crearControlador($h = $hospital(), $central(), $reloj, 'h1');
prueba('feliz: abrir el expediente de p1 = 201 EXP-p1 (CU-01)', $app->atender('abrir', $pide('p1')) === ['status' => 201, 'expediente' => 'EXP-p1']);
prueba('feliz: m1, asignado a p1, lo consulta = 200 (CU-02)', $app->atender('consultar', $pide('p1')) === ['status' => 200, 'expediente' => 'EXP-p1']);
prueba('feliz: con consentimiento vigente ve también el expediente de h2 (CU-03, longitudinal)',
       $app->atender('consultar', $pide('p1', 'm1', true))['externos'] === [['hospital' => 'h2', 'expediente' => 'EXP-H2-77']]);
// 2. Reglas de dominio.
$app->atender('abrir', $pide('p2')); $app->atender('abrir', $pide('p4'));
prueba('regla: abrir otra vez = 409 E3 y abrir a un paciente inactivo = 422 E2 (R-01, R-03)',
       $app->atender('abrir', $pide('p1'))['status'] === 409 && $app->atender('abrir', $pide('p3'))['error'] === 'E2');
prueba('regla: m2, sin asignación activa, consulta p1 = 403 E4 (R-05)', $app->atender('consultar', $pide('p1', 'm2'))['error'] === 'E4');
prueba('regla: p2 sin vínculo MPI = 200 solo con la historia local (R-06)', $app->atender('consultar', $pide('p2', 'm1', true))['solo_local'] === true);
prueba('regla: p4 sin consentimiento = 403 E5 (R-06)', $app->atender('consultar', $pide('p4', 'm1', true))['error'] === 'E5');
$reloj->t = 5000;
prueba('regla: con el consentimiento de p1 vencido = 403 E5 (R-10)', $app->atender('consultar', $pide('p1', 'm1', true))['error'] === 'E5');
$eventos = $h->query("SELECT actor || ' ' || action || ' ' || patient_code || ' ' || outcome FROM audit_events")->fetchAll(PDO::FETCH_COLUMN);
prueba('regla: las 11 operaciones quedan auditadas, también los rechazos (R-08)', count($eventos) === 11 && in_array('m2 consultar p1 E4', $eventos, true));

// 3. Error de persistencia: un doble con CENTRAL caída y una PDOException real en HOSPITAL.
$caida = new class implements IndiceCentral {
    public function consentimiento(string $g, string $h): ?array { throw new ErrorPersistencia('SQLSTATE[08006] conexión perdida'); }
    public function expedientesExternos(string $g, string $h): array { return []; }
};
$local = new HospitalPdo($h); $doble = new ControladorExpediente(new ServicioExpediente($local, $caida, $local, $reloj, 'h1'));
$r = $doble->atender('consultar', $pide('p1', 'm1', true));
prueba('persistencia (doble): CENTRAL caída = 503 E8 sin detalles técnicos', $r['error'] === 'E8' && !str_contains($r['mensaje'], 'SQLSTATE'));
prueba('persistencia (doble): con CENTRAL caída la consulta local sigue = 200', $doble->atender('consultar', $pide('p1'))['status'] === 200);
$h->exec('DROP TABLE medical_records');
prueba('persistencia (PDO real): PDOException en HOSPITAL se traduce a 503 E8', $app->atender('abrir', $pide('p2'))['error'] === 'E8');
// 4. Regla de dependencias: cada capa solo apunta hacia adentro.
$haciaAfuera = [];
foreach (['Dominio' => ['Aplicacion', 'Persistencia', 'Presentacion'], 'Aplicacion' => ['Persistencia', 'Presentacion'], 'Persistencia' => ['Presentacion'], 'Presentacion' => ['Persistencia']] as $capa => $prohibidas)
    foreach ($prohibidas as $p) if (str_contains(implode('', array_map('file_get_contents', glob(__DIR__ . "/../src/$capa/*.php"))), "MicroHis\\Expediente\\$p")) $haciaAfuera[] = "$capa -> $p";
prueba('dependencias: el Dominio no usa otra capa, Aplicación solo usa Dominio, Persistencia y Presentación no se usan entre sí', $haciaAfuera === []);
prueba('PDO: ninguna consulta concatena variables en el SQL', !preg_match('/(prepare|query|exec)\(\s*"[^"]*\$/', file_get_contents(__DIR__ . '/../src/Persistencia/RepositoriosPdo.php')));

echo $fallos === 0 ? "RESULTADO: todo correcto\n" : "RESULTADO: $fallos fallos\n";
exit($fallos === 0 ? 0 : 1);
