<?php
declare(strict_types=1);

// Camino feliz, reglas de dominio y error de persistencia (base SQLite de prueba y un doble), más la regla de dependencias. Datos ficticios.
require __DIR__ . '/../bootstrap.php';

use MicroHis\Catalogo\Aplicacion\{ErrorPersistencia, RepositorioCatalogo, ServicioCatalogo};
use MicroHis\Catalogo\Presentacion\ControladorCatalogo;

$fallos = 0;
function prueba(string $nombre, bool $ok): void { global $fallos; $fallos += $ok ? 0 : 1; echo ($ok ? 'OK    ' : 'FALLA ') . "$nombre\n"; }
function baseDePrueba(): PDO { $db = new PDO('sqlite::memory:'); $db->exec(file_get_contents(__DIR__ . '/../database/schema.sql')); return $db; }

// 1. Camino feliz, de la presentación a la base de prueba.
$db = baseDePrueba();
$app = crearControlador($db);
prueba('feliz: registrar sala A = 201', $app->atender('registrarSala', ['codigo' => 'A', 'nombre' => 'Medicina interna'])['status'] === 201);
prueba('feliz: registrar cama A/101 = 201', $app->atender('registrarCama', ['sala' => 'A', 'codigo' => '101'])['status'] === 201);
prueba('feliz: la cama nace disponible (R-04)', $db->query("SELECT status FROM beds WHERE code = '101'")->fetchColumn() === 'available');
prueba('feliz: A/101 a mantenimiento = 200', $app->atender('cambiarEstado', ['sala' => 'A', 'cama' => '101', 'estado' => 'maintenance', 'motivo' => 'limpieza profunda'])['status'] === 200);
prueba('feliz: el cambio queda en el historial con su motivo (R-09)', $db->query('SELECT reason FROM bed_status_history')->fetchColumn() === 'limpieza profunda');

// 2. Reglas de dominio.
$error = fn (array $r) => "{$r['status']} " . ($r['error'] ?? '');
prueba('regla: sala A repetida = 409 E3 (R-01)', $error($app->atender('registrarSala', ['codigo' => 'A', 'nombre' => 'Otra'])) === '409 E3');
prueba('regla: cama en sala inexistente = 422 E4 (R-03)', $error($app->atender('registrarCama', ['sala' => 'Z', 'codigo' => '1'])) === '422 E4');
$app->atender('registrarCama', ['sala' => 'A', 'codigo' => '102']); $app->atender('cambiarEstado', ['sala' => 'A', 'cama' => '102', 'estado' => 'inactive', 'motivo' => 'dañada']);
prueba('regla: inactiva -> mantenimiento = 422 E6 (R-05)', $error($app->atender('cambiarEstado', ['sala' => 'A', 'cama' => '102', 'estado' => 'maintenance', 'motivo' => 'x'])) === '422 E6');
$db->exec("UPDATE beds SET status = 'occupied' WHERE code = '101'");   // admisión ocupa la cama
prueba('regla: cama ocupada no cambia a mano = 409 E5 (R-06)', $error($app->atender('cambiarEstado', ['sala' => 'A', 'cama' => '101', 'estado' => 'available', 'motivo' => 'x'])) === '409 E5');

// 3. Error de persistencia: un doble que simula la base caída y una PDOException real.
$caida = new class implements RepositorioCatalogo {
    public function salaActiva(string $s): ?bool { throw new ErrorPersistencia('SQLSTATE[08006] conexión perdida'); }
    public function guardarSala(string $s, string $n): void {}
    public function estadoCama(string $s, string $c): ?string { return null; }
    public function guardarCama(string $s, string $c): void {}
    public function cambiarEstado(string $s, string $c, string $de, string $a, string $m): bool { return false; }
};
$r = (new ControladorCatalogo(new ServicioCatalogo($caida)))->atender('registrarSala', ['codigo' => 'B', 'nombre' => 'Cirugía']);
prueba('persistencia (doble): base caída = 503 E8 sin detalles técnicos', $error($r) === '503 E8' && !str_contains($r['mensaje'], 'SQLSTATE'));
$rota = baseDePrueba(); $rota->exec('DROP TABLE wards');
prueba('persistencia (PDO real): PDOException se traduce a 503 E8', $error(crearControlador($rota)->atender('registrarSala', ['codigo' => 'B', 'nombre' => 'Cirugía'])) === '503 E8');

// 4. Regla de dependencias: cada capa solo apunta hacia adentro.
foreach (['Dominio' => ['Aplicacion', 'Persistencia', 'Presentacion'], 'Aplicacion' => ['Persistencia', 'Presentacion'],
          'Persistencia' => ['Presentacion'], 'Presentacion' => ['Persistencia']] as $capa => $prohibidas) {
    $codigo = implode('', array_map('file_get_contents', glob(__DIR__ . "/../src/$capa/*.php")));
    prueba("dependencias: $capa no usa " . implode(', ', $prohibidas), array_filter($prohibidas, fn ($p) => str_contains($codigo, "MicroHis\\Catalogo\\$p")) === []);
}
prueba('PDO: ninguna consulta concatena variables en el SQL', !preg_match('/(prepare|query|exec)\(\s*"[^"]*\$/', file_get_contents(__DIR__ . '/../src/Persistencia/RepositorioPdo.php')));

echo $fallos === 0 ? "RESULTADO: todo correcto\n" : "RESULTADO: $fallos fallos\n";
exit($fallos === 0 ? 0 : 1);
