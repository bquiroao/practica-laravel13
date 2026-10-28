<?php
declare(strict_types=1);

// El mismo guion (camino feliz y reglas) contra los adaptadores PDO e InMemory, error de persistencia, MVC y dependencias. Datos ficticios.
require __DIR__ . '/../bootstrap.php';

use MicroHis\DashboardOcupacion\Aplicacion\{ConsolidadorOcupacion, ConsultaOcupacion, ErrorPersistencia, FuenteCamas, Reloj};
use MicroHis\DashboardOcupacion\Persistencia\{RepositorioEnMemoria, RepositorioPdo};
use MicroHis\DashboardOcupacion\Presentacion\{ControladorOcupacion, Rutas, VistaJson};
use function MicroHis\DashboardOcupacion\crearControlador;

$fallos = 0;
function prueba(string $nombre, bool $ok): void { global $fallos; $fallos += $ok ? 0 : 1; echo ($ok ? 'OK    ' : 'FALLA ') . "$nombre\n"; }
// Sala A: A1 y A2 disponibles (A2 con estancia abierta), A3 y A4 ocupadas (A4 sin estancia), A5 en mantenimiento.
// Sala B: solo mantenimiento e inactiva. Sala C: inactiva.
function baseDePrueba(): PDO { $db = new PDO('sqlite::memory:'); $db->exec(file_get_contents(__DIR__ . '/../database/schema.sql'));
    $db->exec("INSERT INTO wards VALUES (1, 'A', 'active'), (2, 'B', 'active'), (3, 'C', 'inactive');
               INSERT INTO beds VALUES (1, 1, 'A1', 'available'), (2, 1, 'A2', 'available'), (3, 1, 'A3', 'occupied'), (4, 1, 'A4', 'occupied'),
                                       (5, 1, 'A5', 'maintenance'), (6, 2, 'B1', 'maintenance'), (7, 2, 'B2', 'inactive'), (8, 3, 'C1', 'occupied');
               INSERT INTO bed_stays (bed_id, ended_at) VALUES (2, NULL), (3, NULL), (4, '2026-10-25 10:00'), (8, NULL);"); return $db; }
$memoria = new RepositorioEnMemoria(['A' => true, 'B' => true, 'C' => false], [['A', 'A1', 'available', false], ['A', 'A2', 'available', true], ['A', 'A3', 'occupied', true],
    ['A', 'A4', 'occupied', false], ['A', 'A5', 'maintenance', false], ['B', 'B1', 'maintenance', false], ['B', 'B2', 'inactive', false], ['C', 'C1', 'occupied', true]]);   // los mismos datos
$reloj = new class implements Reloj { public int $t = 1000; public function ahora(): int { return $this->t; } };   // «ahora» controlado

// 1 y 2. Camino feliz y reglas de dominio: el mismo guion con cada adaptador (contrato del Repository).
$db = baseDePrueba();
foreach (['PDO' => new RepositorioPdo($db), 'InMemory' => $memoria] as $adaptador => $repo) {
    $reloj->t = 1000; $app = crearControlador($repo, $reloj);
    prueba("[$adaptador] feliz: sin corte todavía, la consulta muestra el estado vacío (CA-04)", $app->atender('consultar', []) === ['status' => 200, 'vacio' => true]);
    prueba("[$adaptador] feliz: consolidar = 201 con la hora y 2 salas activas (CU-01, R-03, R-05)", $app->atender('consolidar', []) === ['status' => 201, 'hora' => 1000, 'salas' => 2]);
    $a = $app->atender('consultar', [])['salas']['A'];
    prueba("[$adaptador] feliz: sala A = 50.0 % con 2 disponibles, 2 ocupadas y 1 en mantenimiento aparte (CA-01, R-01)", [$a['ocupacion'], $a['available'], $a['occupied'], $a['maintenance']] === [50.0, 2, 2, 1]);
    prueba("[$adaptador] feliz: filtrar por «available» deja solo la sala A (CA-06, CU-03)", array_keys($app->atender('consultar', ['estado' => 'available'])['salas']) === ['A']);
    $salas = $app->atender('consultar', [])['salas'];
    prueba("[$adaptador] regla: B sin camas operativas = «no aplica» y la sala inactiva C no aparece (CA-02, R-02, R-03)", $salas['B']['ocupacion'] === 'no aplica' && !isset($salas['C']));
    prueba("[$adaptador] regla: A2 y A4 quedan marcadas como inconsistentes (CA-03, R-04)", $a['inconsistentes'] === ['A2', 'A4']);
    $reloj->t = 1000 + 5 * 60; $fresco = $app->atender('consultar', [])['aviso_viejo'];
    $reloj->t = 1000 + 20 * 60;
    prueba("[$adaptador] regla: sin aviso a los 5 minutos y con aviso a los 20 (CA-05, R-06)", $fresco === false && $app->atender('consultar', [])['aviso_viejo'] === true);
    prueba("[$adaptador] regla: filtrar por un estado que no existe = 422 E3", $app->atender('consultar', ['estado' => 'libre'])['error'] === 'E3');
    prueba("[$adaptador] regla: el corte solo guarda cifras por sala (R-07)", array_keys($a) === ['available', 'occupied', 'maintenance', 'inactive', 'ocupacion', 'inconsistentes']);
    prueba("[$adaptador] contrato: las filas de camas son las de A y B, y el último corte vuelve igual que se guardó",
           array_column($repo->camasDeSalasActivas(), 'cama') === ['A1', 'A2', 'A3', 'A4', 'A5', 'B1', 'B2'] && $repo->ultimoCorte()->salas['A']['ocupacion'] === 50.0);
}
prueba('[PDO] regla: consolidar no cambia ningún estado de cama (R-09)', $db->query("SELECT group_concat(status) FROM beds")->fetchColumn() === 'available,available,occupied,occupied,maintenance,maintenance,inactive,occupied');
prueba('[InMemory] contrato: una sala activa sin camas aparece con cama null, como en el LEFT JOIN', (new RepositorioEnMemoria(['D' => true]))->camasDeSalasActivas() === [['sala' => 'D', 'cama' => null, 'estado' => null, 'con_estancia' => false]]);

// 3. Error de persistencia: un doble que simula la base caída y una PDOException real.
$caida = new class implements FuenteCamas { public function camasDeSalasActivas(): array { throw new ErrorPersistencia('SQLSTATE[08006] conexión perdida'); } };
$repo = new RepositorioPdo($db);
$r = (new ControladorOcupacion(new ConsolidadorOcupacion($caida, $repo, $reloj), new ConsultaOcupacion($repo, $reloj), new VistaJson()))->atender('consolidar', []);
prueba('persistencia (doble): base caída = 503 E8 sin detalles técnicos', $r['error'] === 'E8' && !str_contains($r['mensaje'], 'SQLSTATE'));
$app = crearControlador($repo, $reloj);
$db->exec('DROP TABLE bed_stays');
prueba('persistencia (PDO real): PDOException al leer = 503 E8', $app->atender('consolidar', [])['status'] === 503);
prueba('persistencia: tras ambos fallos se conserva el corte anterior (CA-07, R-09)', $app->atender('consultar', [])['hora'] === 1000);

// 4. Entrada MVC: rutas, controlador sin SQL ni reglas, y regla de dependencias.
prueba('MVC: las rutas llevan a su acción, y una ruta desconocida responde 404', [Rutas::resolver('POST', '/ocupacion/cortes'), Rutas::resolver('GET', '/ocupacion'), Rutas::resolver('DELETE', '/ocupacion')]
       === [['consolidar', []], ['consultar', []], ['', []]] && crearControlador($memoria, $reloj)->atender('', [])['status'] === 404);
prueba('MVC: el controlador no tiene SQL, PDO, cálculo de ocupación ni throw', !preg_match('/\b(SELECT|INSERT|UPDATE|DELETE)\b|PDO|OcupacionSala|throw /', file_get_contents(__DIR__ . '/../src/Presentacion/ControladorOcupacion.php')));
$haciaAfuera = [];
foreach (['Dominio' => ['Aplicacion', 'Persistencia', 'Presentacion'], 'Aplicacion' => ['Persistencia', 'Presentacion'], 'Persistencia' => ['Presentacion'], 'Presentacion' => ['Persistencia']] as $capa => $prohibidas)
    foreach ($prohibidas as $p) if (str_contains(implode('', array_map('file_get_contents', glob(__DIR__ . "/../src/$capa/*.php"))), "MicroHis\\DashboardOcupacion\\$p")) $haciaAfuera[] = "$capa -> $p";
prueba('dependencias: el Dominio no usa otra capa, Aplicación solo usa Dominio, Persistencia y Presentación no se usan entre sí', $haciaAfuera === []);
prueba('PDO: ninguna consulta concatena variables en el SQL', !preg_match('/(prepare|query|exec)\(\s*"[^"]*\$/', file_get_contents(__DIR__ . '/../src/Persistencia/RepositorioPdo.php')));

echo $fallos === 0 ? "RESULTADO: todo correcto\n" : "RESULTADO: $fallos fallos\n";
exit($fallos === 0 ? 0 : 1);
