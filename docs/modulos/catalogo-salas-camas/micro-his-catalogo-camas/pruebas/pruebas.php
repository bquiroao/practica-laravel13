<?php
declare(strict_types=1);

// Las mismas pruebas de camino feliz y reglas contra los dos adaptadores del Repository (PDO con SQLite de prueba e InMemory),
// error de persistencia, entrada MVC y regla de dependencias. Datos ficticios.
require __DIR__ . '/../bootstrap.php';

use MicroHis\Catalogo\Aplicacion\{ErrorPersistencia, RepositorioCatalogo};
use MicroHis\Catalogo\Persistencia\{RepositorioEnMemoria, RepositorioPdo};
use MicroHis\Catalogo\Presentacion\Rutas;
use function MicroHis\Catalogo\crearControlador;

$fallos = 0;
function prueba(string $nombre, bool $ok): void { global $fallos; $fallos += $ok ? 0 : 1; echo ($ok ? 'OK    ' : 'FALLA ') . "$nombre\n"; }
function baseDePrueba(): PDO { $db = new PDO('sqlite::memory:'); $db->exec(file_get_contents(__DIR__ . '/../database/schema.sql')); return $db; }
$error = fn (array $r) => "{$r['status']} " . ($r['error'] ?? '');

// 1 y 2. Camino feliz y reglas de dominio: el mismo guion con cada adaptador (contrato del Repository).
$db = baseDePrueba();
foreach (['PDO' => new RepositorioPdo($db), 'InMemory' => new RepositorioEnMemoria()] as $adaptador => $repo) {
    $app = crearControlador($repo);
    prueba("[$adaptador] feliz: registrar sala A y cama A/101 = 201",
           $app->atender('registrarSala', ['codigo' => 'A', 'nombre' => 'Medicina interna'])['status'] === 201 && $app->atender('registrarCama', ['sala' => 'A', 'codigo' => '101'])['status'] === 201);
    prueba("[$adaptador] feliz: la cama nace disponible (R-04)", $repo->estadoCama('A', '101') === 'available');
    prueba("[$adaptador] feliz: A/101 a mantenimiento = 200", $app->atender('cambiarEstado', ['sala' => 'A', 'cama' => '101', 'estado' => 'maintenance', 'motivo' => 'limpieza profunda'])['status'] === 200);
    prueba("[$adaptador] regla: sala A repetida = 409 E3 (R-01)", $error($app->atender('registrarSala', ['codigo' => 'A', 'nombre' => 'Otra'])) === '409 E3');
    prueba("[$adaptador] regla: cama en sala inexistente = 422 E4 (R-03)", $error($app->atender('registrarCama', ['sala' => 'Z', 'codigo' => '1'])) === '422 E4');
    $app->atender('registrarCama', ['sala' => 'A', 'codigo' => '102']); $app->atender('cambiarEstado', ['sala' => 'A', 'cama' => '102', 'estado' => 'inactive', 'motivo' => 'dañada']);
    prueba("[$adaptador] regla: inactiva -> mantenimiento = 422 E6 (R-05)", $error($app->atender('cambiarEstado', ['sala' => 'A', 'cama' => '102', 'estado' => 'maintenance', 'motivo' => 'x'])) === '422 E6');
    $repo->cambiarEstado('A', '101', 'maintenance', 'occupied', 'admisión');   // admisión ocupa la cama por el mismo puerto
    prueba("[$adaptador] regla: cama ocupada no cambia a mano = 409 E5 (R-06)", $error($app->atender('cambiarEstado', ['sala' => 'A', 'cama' => '101', 'estado' => 'available', 'motivo' => 'x'])) === '409 E5');
    prueba("[$adaptador] contrato: cambiar desde un estado que ya no es el actual devuelve false (E7)", $repo->cambiarEstado('A', '101', 'available', 'inactive', 'x') === false);
}
prueba('[PDO] feliz: el cambio queda en bed_status_history con su motivo (R-09)', $db->query('SELECT reason FROM bed_status_history ORDER BY id')->fetchColumn() === 'limpieza profunda');
$memoria = new RepositorioEnMemoria(); $memoria->guardarSala('A', 'x'); $memoria->guardarCama('A', '1'); $memoria->cambiarEstado('A', '1', 'available', 'maintenance', 'limpieza');
prueba('[InMemory] feliz: el cambio queda en el historial con su motivo (R-09)', $memoria->historial()[0]['motivo'] === 'limpieza');

// 3. Error de persistencia: un doble que simula la base caída y una PDOException real.
$caida = new class implements RepositorioCatalogo {
    public function salaActiva(string $s): ?bool { throw new ErrorPersistencia('SQLSTATE[08006] conexión perdida'); }
    public function guardarSala(string $s, string $n): void {}
    public function estadoCama(string $s, string $c): ?string { return null; }
    public function guardarCama(string $s, string $c): void {}
    public function cambiarEstado(string $s, string $c, string $de, string $a, string $m): bool { return false; }
};
$r = crearControlador($caida)->atender('registrarSala', ['codigo' => 'B', 'nombre' => 'Cirugía']);
prueba('persistencia (doble): base caída = 503 E8 sin detalles técnicos', $error($r) === '503 E8' && !str_contains($r['mensaje'], 'SQLSTATE'));
$rota = baseDePrueba(); $rota->exec('DROP TABLE wards');
prueba('persistencia (PDO real): PDOException se traduce a 503 E8', $error(crearControlador(new RepositorioPdo($rota))->atender('registrarSala', ['codigo' => 'B', 'nombre' => 'Cirugía'])) === '503 E8');

// 4. Entrada MVC: rutas, controlador sin SQL ni reglas, y regla de dependencias.
prueba('MVC: las rutas llevan a su acción con los parámetros de la ruta', [Rutas::resolver('POST', '/salas'), Rutas::resolver('PATCH', '/salas/A/camas/101/estado'), Rutas::resolver('GET', '/salas')]
       === [['registrarSala', []], ['cambiarEstado', ['sala' => 'A', 'cama' => '101']], ['', []]]);
prueba('MVC: ruta desconocida = 404', crearControlador(new RepositorioEnMemoria())->atender('', [])['status'] === 404);
prueba('MVC: el controlador no tiene SQL, PDO, estados de cama ni throw', !preg_match('/\b(SELECT|INSERT|UPDATE|DELETE)\b|PDO|EstadoCama|throw /', file_get_contents(__DIR__ . '/../src/Presentacion/ControladorCatalogo.php')));
foreach (['Dominio' => ['Aplicacion', 'Persistencia', 'Presentacion'], 'Aplicacion' => ['Persistencia', 'Presentacion'],
          'Persistencia' => ['Presentacion'], 'Presentacion' => ['Persistencia']] as $capa => $prohibidas) {
    $codigo = implode('', array_map('file_get_contents', glob(__DIR__ . "/../src/$capa/*.php")));
    prueba("dependencias: $capa no usa " . implode(', ', $prohibidas), array_filter($prohibidas, fn ($p) => str_contains($codigo, "MicroHis\\Catalogo\\$p")) === []);
}
prueba('PDO: ninguna consulta concatena variables en el SQL', !preg_match('/(prepare|query|exec)\(\s*"[^"]*\$/', file_get_contents(__DIR__ . '/../src/Persistencia/RepositorioPdo.php')));

echo $fallos === 0 ? "RESULTADO: todo correcto\n" : "RESULTADO: $fallos fallos\n";
exit($fallos === 0 ? 0 : 1);
