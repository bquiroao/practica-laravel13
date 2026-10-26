<?php
declare(strict_types=1);

// Camino feliz, reglas de dominio y error de persistencia (base SQLite de prueba y un doble), más la regla de dependencias. Datos ficticios.
require __DIR__ . '/../bootstrap.php';

use MicroHis\DashboardOcupacion\Aplicacion\{ConsolidadorOcupacion, ConsultaOcupacion, ErrorPersistencia, FuenteCamas, Reloj};
use MicroHis\DashboardOcupacion\Persistencia\RepositorioPdo;
use MicroHis\DashboardOcupacion\Presentacion\ControladorOcupacion;

$fallos = 0;
function prueba(string $nombre, bool $ok): void { global $fallos; $fallos += $ok ? 0 : 1; echo ($ok ? 'OK    ' : 'FALLA ') . "$nombre\n"; }
// Sala A: A1 y A2 disponibles (A2 con estancia abierta), A3 y A4 ocupadas (A4 sin estancia), A5 en mantenimiento.
// Sala B: solo mantenimiento e inactiva. Sala C: inactiva.
function baseDePrueba(): PDO { $db = new PDO('sqlite::memory:'); $db->exec(file_get_contents(__DIR__ . '/../database/schema.sql'));
    $db->exec("INSERT INTO wards VALUES (1, 'A', 'active'), (2, 'B', 'active'), (3, 'C', 'inactive');
               INSERT INTO beds VALUES (1, 1, 'A1', 'available'), (2, 1, 'A2', 'available'), (3, 1, 'A3', 'occupied'), (4, 1, 'A4', 'occupied'),
                                       (5, 1, 'A5', 'maintenance'), (6, 2, 'B1', 'maintenance'), (7, 2, 'B2', 'inactive'), (8, 3, 'C1', 'occupied');
               INSERT INTO bed_stays (bed_id, ended_at) VALUES (2, NULL), (3, NULL), (4, '2026-10-25 10:00'), (8, NULL);"); return $db; }
$reloj = new class implements Reloj { public int $t = 1000; public function ahora(): int { return $this->t; } };   // «ahora» controlado

// 1. Camino feliz, de la presentación a la base de prueba.
$app = crearControlador($db = baseDePrueba(), $reloj);
prueba('feliz: sin corte todavía, la consulta muestra el estado vacío (CA-04)', $app->atender('consultar', []) === ['status' => 200, 'vacio' => true]);
prueba('feliz: consolidar = 201 con la hora y 2 salas activas (CU-01, R-03, R-05)', $app->atender('consolidar', []) === ['status' => 201, 'hora' => 1000, 'salas' => 2]);
$a = $app->atender('consultar', [])['salas']['A'];
prueba('feliz: sala A = 50.0 % con 2 disponibles, 2 ocupadas y 1 en mantenimiento aparte (CA-01, R-01)',
       [$a['ocupacion'], $a['available'], $a['occupied'], $a['maintenance']] === [50.0, 2, 2, 1]);
prueba('feliz: filtrar por «available» deja solo la sala A (CA-06, CU-03)', array_keys($app->atender('consultar', ['estado' => 'available'])['salas']) === ['A']);
// 2. Reglas de dominio.
$salas = $app->atender('consultar', [])['salas'];
prueba('regla: B sin camas operativas = «no aplica» y la sala inactiva C no aparece (CA-02, R-02, R-03)', $salas['B']['ocupacion'] === 'no aplica' && !isset($salas['C']));
prueba('regla: A2 y A4 quedan marcadas como inconsistentes (CA-03, R-04)', $a['inconsistentes'] === ['A2', 'A4']);
prueba('regla: consolidar no cambia ningún estado de cama (R-09)', $db->query("SELECT group_concat(status) FROM beds")->fetchColumn() === 'available,available,occupied,occupied,maintenance,maintenance,inactive,occupied');
$reloj->t = 1000 + 5 * 60; $fresco = $app->atender('consultar', [])['aviso_viejo'];
$reloj->t = 1000 + 20 * 60;
prueba('regla: sin aviso a los 5 minutos y con aviso a los 20 (CA-05, R-06)', $fresco === false && $app->atender('consultar', [])['aviso_viejo'] === true);
prueba('regla: filtrar por un estado que no existe = 422 E3', $app->atender('consultar', ['estado' => 'libre'])['error'] === 'E3');
prueba('regla: el corte solo guarda cifras por sala (R-07)', array_keys($a) === ['available', 'occupied', 'maintenance', 'inactive', 'ocupacion', 'inconsistentes']);

// 3. Error de persistencia: un doble que simula la base caída y una PDOException real.
$caida = new class implements FuenteCamas { public function camasDeSalasActivas(): array { throw new ErrorPersistencia('SQLSTATE[08006] conexión perdida'); } };
$repo = new RepositorioPdo($db);
$r = (new ControladorOcupacion(new ConsolidadorOcupacion($caida, $repo, $reloj), new ConsultaOcupacion($repo, $reloj)))->atender('consolidar', []);
prueba('persistencia (doble): base caída = 503 E8 sin detalles técnicos', $r['error'] === 'E8' && !str_contains($r['mensaje'], 'SQLSTATE'));
$db->exec('DROP TABLE bed_stays');
prueba('persistencia (PDO real): PDOException al leer = 503 E8', $app->atender('consolidar', [])['status'] === 503);
prueba('persistencia: tras ambos fallos se conserva el corte anterior (CA-07, R-09)', $app->atender('consultar', [])['hora'] === 1000);
// 4. Regla de dependencias: cada capa solo apunta hacia adentro.
$haciaAfuera = [];
foreach (['Dominio' => ['Aplicacion', 'Persistencia', 'Presentacion'], 'Aplicacion' => ['Persistencia', 'Presentacion'], 'Persistencia' => ['Presentacion'], 'Presentacion' => ['Persistencia']] as $capa => $prohibidas)
    foreach ($prohibidas as $p) if (str_contains(implode('', array_map('file_get_contents', glob(__DIR__ . "/../src/$capa/*.php"))), "MicroHis\\DashboardOcupacion\\$p")) $haciaAfuera[] = "$capa -> $p";
prueba('dependencias: el Dominio no usa otra capa, Aplicación solo usa Dominio, Persistencia y Presentación no se usan entre sí', $haciaAfuera === []);
prueba('PDO: ninguna consulta concatena variables en el SQL', !preg_match('/(prepare|query|exec)\(\s*"[^"]*\$/', file_get_contents(__DIR__ . '/../src/Persistencia/RepositorioPdo.php')));

echo $fallos === 0 ? "RESULTADO: todo correcto\n" : "RESULTADO: $fallos fallos\n";
exit($fallos === 0 ? 0 : 1);
