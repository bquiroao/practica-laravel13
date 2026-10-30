<?php
declare(strict_types=1);

// Autorrevisión del autor (criterio de entrada E-03): corre las pruebas del Micro-HIS y reproduce cada hallazgo
// sobre el código tal como está. Si alguien corrige un hallazgo, su línea pasa a «no reproducido». Datos ficticios.
$raiz = __DIR__ . '/../../micro-his-dashboard-ocupacion';
require $raiz . '/bootstrap.php';

use MicroHis\DashboardOcupacion\Aplicacion\Reloj;
use MicroHis\DashboardOcupacion\Persistencia\RepositorioPdo;
use MicroHis\DashboardOcupacion\Presentacion\ControladorOcupacion;
use function MicroHis\DashboardOcupacion\crearControlador;

function linea(string $id, bool $reproducido, string $detalle): void { printf("%-5s %-15s %s\n", $id, $reproducido ? 'reproducido' : 'no reproducido', $detalle); }
$resultado = fn (array $r) => rtrim("{$r['status']} " . ($r['error'] ?? ''));

exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg("$raiz/pruebas/pruebas.php"), $salida, $codigo);
printf("ENTRADA pruebas del Micro-HIS: %s (%s)\n\n", $codigo === 0 ? 'en verde' : 'FALLAN', end($salida));

// Sala A activa: A1 ocupada con dos estancias abiertas. Sala C inactiva: C1 ocupada con su estancia abierta.
$db = new PDO('sqlite::memory:');
$db->exec(file_get_contents("$raiz/database/schema.sql"));
$db->exec("INSERT INTO wards VALUES (1, 'A', 'active'), (2, 'C', 'inactive');
           INSERT INTO beds VALUES (1, 1, 'A1', 'occupied'), (2, 1, 'A2', 'available'), (3, 2, 'C1', 'occupied');
           INSERT INTO bed_stays VALUES (1, 1, NULL), (2, 1, NULL), (3, 3, NULL);");
$reloj = new class implements Reloj { public function ahora(): int { return 100000; } };
$app = crearControlador(new RepositorioPdo($db), $reloj);
$app->atender('consolidar', []);
$corte = $app->atender('consultar', []);

// H-01: R-08 / CU-05. La entrada no recibe usuario, permiso ni hospital: cualquiera consulta.
$parametros = array_map(fn ($p) => $p->getName(), (new ReflectionMethod(ControladorOcupacion::class, 'atender'))->getParameters());
$r = $app->atender('consultar', ['permisos' => []]);
linea('H-01', $r['status'] === 200, 'entrada atender(' . implode(', ', $parametros) . "); consultar sin permisos = {$resultado($r)}");

// H-02: códigos distintos de la semana 1, donde E3 es «sin permiso (403)» y E2 «falla la lectura».
$estado = $app->atender('consultar', ['estado' => 'libre']);
$rota = new PDO('sqlite::memory:');
$lectura = crearControlador(new RepositorioPdo($rota), $reloj)->atender('consolidar', []);
linea('H-02', $resultado($estado) === '422 E3' || ($lectura['error'] ?? '') !== 'E2', "estado inexistente = {$resultado($estado)}; falla de lectura = {$resultado($lectura)}");

// H-03: R-04. Dos estancias abiertas en la misma cama (dos pacientes en A1) no se marcan.
linea('H-03', $corte['salas']['A']['inconsistentes'] === [], 'A1 ocupada con 2 estancias abiertas; inconsistentes de A: [' . implode(', ', $corte['salas']['A']['inconsistentes']) . ']');

// H-04: R-03 con R-04. Un paciente en una cama de la sala inactiva C desaparece del corte sin ninguna marca.
linea('H-04', !isset($corte['salas']['C']), 'C1 ocupada con estancia abierta en la sala inactiva C; salas del corte: ' . implode(', ', array_keys($corte['salas'])));

// H-05: RNF-04. Si la consulta no puede leer los cortes, responde 503 diciendo que «se conserva el corte anterior», pero no lo muestra.
$db->exec('DROP TABLE occupancy_snapshots');
$r = $app->atender('consultar', []);
linea('H-05', $r['status'] === 503 && !isset($r['salas']), "consultar con la tabla de cortes caída = {$resultado($r)} «{$r['mensaje']}», sin salas");
