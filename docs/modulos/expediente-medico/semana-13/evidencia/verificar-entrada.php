<?php
declare(strict_types=1);

// Autorrevisión del autor (criterio de entrada E-03): corre las pruebas del Micro-HIS y reproduce cada hallazgo
// sobre el código tal como está. Si alguien corrige un hallazgo, su línea pasa a «no reproducido». Datos ficticios.
$raiz = __DIR__ . '/../../micro-his-expediente';
if (!is_dir($raiz)) { echo "ENTRADA: el Micro-HIS no está en esta rama; llega con el PR #191 (semana 3).\n"; exit(1); }
require $raiz . '/bootstrap.php';

use MicroHis\Expediente\Aplicacion\Reloj;
use MicroHis\Expediente\Persistencia\{CentralPdo, HospitalPdo};
use MicroHis\Expediente\Presentacion\VistaJson;
use function MicroHis\Expediente\crearControlador;

function linea(string $id, bool $reproducido, string $detalle): void { printf("%-5s %-15s %s\n", $id, $reproducido ? 'reproducido' : 'no reproducido', $detalle); }
function columnas(PDO $db, string $tabla): array { return array_column($db->query("PRAGMA table_info($tabla)")->fetchAll(), 'name'); }
$resultado = fn (array $r) => rtrim("{$r['status']} " . ($r['error'] ?? ''));

exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg("$raiz/pruebas/pruebas.php"), $salida, $codigo);
printf("ENTRADA pruebas del Micro-HIS: %s (%s)\n\n", $codigo === 0 ? 'en verde' : 'FALLAN', end($salida));

// p1 activo y vinculado al MPI, con consentimiento vigente; p5 activo sin expediente. m1 atiende a p1; m2 no atiende a nadie.
$hospital = new PDO('sqlite::memory:');
$hospital->exec(file_get_contents("$raiz/database/schema.sql") . "INSERT INTO local_patients VALUES ('p1', 'g1', 'active', 'linked'), ('p5', 'g5', 'active', 'pending');
    INSERT INTO care_assignments VALUES ('p1', 'm1', 'active'); INSERT INTO medical_records VALUES ('p1', 'EXP-p1');");
$central = new PDO('sqlite::memory:');
$central->exec(file_get_contents("$raiz/database/schema-central.sql") . "INSERT INTO consents VALUES ('g1', 'h1', 0, NULL, 'active');
    INSERT INTO mpi_records VALUES ('g1', 'h1', 'EXP-p1'), ('g1', 'h2', 'EXP-H2-77');");
$reloj = new class implements Reloj { public function ahora(): int { return 1000; } };
$app = crearControlador(new HospitalPdo($hospital), new CentralPdo($central), $reloj, 'h1');
$http = (new ReflectionClassConstant(VistaJson::class, 'HTTP'))->getValue();   // desde la semana 4, la vista es dueña de los códigos
$eventos = fn () => (int) $hospital->query('SELECT COUNT(*) FROM audit_events')->fetchColumn();

// H-01: los códigos no son los de la semana 1, donde E1 es «sin asignación activa» (403) y E4 «solo historia local».
$r = $app->atender('consultar', ['paciente' => 'p1', 'profesional' => 'm2']);
linea('H-01', $resultado($r) !== '403 E1', "m2 sin asignación consulta p1 = {$resultado($r)} (la semana 1 dice 403 E1); códigos: " . implode(' ', array_keys($http)));

// H-02: CU-01 es de admisión, pero abrir no comprueba rol ni permiso: m2, un médico sin relación con p5, abre su expediente.
$r = $app->atender('abrir', ['paciente' => 'p5', 'profesional' => 'm2']);
linea('H-02', $r['status'] === 201, "abrir p5 con profesional = m2 (escrito por el cliente) = {$resultado($r)}");

// H-03: R-09. No hay alcance: la asignación y el consentimiento no tienen scope, y la consulta externa devuelve todo.
$alcance = array_intersect(['scope', 'data_scope'], array_merge(columnas($hospital, 'care_assignments'), columnas($central, 'consents')));
$r = $app->atender('consultar', ['paciente' => 'p1', 'profesional' => 'm1', 'longitudinal' => true]);
linea('H-03', $alcance === [], 'columnas de alcance: ninguna; externos devueltos: ' . json_encode($r['externos'] ?? []));

// H-04: R-08. La auditoría es una tabla común: un UPDATE cambia el resultado de un evento.
try { $cambiados = $hospital->exec("UPDATE audit_events SET outcome = 'ok' WHERE outcome <> 'ok'"); } catch (PDOException) { $cambiados = 0; }   // 0 = la base lo impide
linea('H-04', $cambiados > 0, "UPDATE de eventos rechazados a «ok»: $cambiados fila(s) cambiada(s)");

// H-05: RNF-04. Si CENTRAL falla, la excepción técnica no pasa por la auditoría: el intento no queda registrado.
$antes = $eventos();
$caida = new PDO('sqlite::memory:');   // CENTRAL sin tablas
$r = crearControlador(new HospitalPdo($hospital), new CentralPdo($caida), $reloj, 'h1')->atender('consultar', ['paciente' => 'p1', 'profesional' => 'm1', 'longitudinal' => true]);
linea('H-05', $eventos() === $antes, "consulta longitudinal con CENTRAL caída = {$resultado($r)}; eventos nuevos en la auditoría: " . ($eventos() - $antes));
