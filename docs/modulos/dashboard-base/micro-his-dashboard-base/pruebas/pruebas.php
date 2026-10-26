<?php
declare(strict_types=1);

// Camino feliz, reglas de dominio y error de persistencia (base SQLite de prueba y un doble), más la regla de dependencias. Datos ficticios.
require __DIR__ . '/../bootstrap.php';

use MicroHis\DashboardBase\Aplicacion\{ErrorPersistencia, FuenteConteos, Reloj, ServicioDashboard};
use MicroHis\DashboardBase\Presentacion\ControladorDashboard;

$fallos = 0;
function prueba(string $nombre, bool $ok): void { global $fallos; $fallos += $ok ? 0 : 1; echo ($ok ? 'OK    ' : 'FALLA ') . "$nombre\n"; }
function baseDePrueba(): PDO { $db = new PDO('sqlite::memory:'); $db->exec(file_get_contents(__DIR__ . '/../database/schema.sql'));
    $db->exec("INSERT INTO local_patients (status) VALUES ('active'), ('active'), ('inactive');
               INSERT INTO appointments (starts_at, status) VALUES ('2026-10-26 08:00', 'scheduled'), ('2026-10-26 09:00', 'confirmed'), ('2026-10-26 10:00', 'cancelled'), ('2026-10-27 08:00', 'scheduled');
               INSERT INTO beds (status) VALUES ('occupied'), ('maintenance');"); return $db; }
$reloj = new class implements Reloj { public function hoy(): string { return '2026-10-26'; } };   // «hoy» fijo para contar las citas del día
$todos = ['hospital' => 'h1', 'permisos' => ['pacientes.ver', 'citas.ver', 'camas.ver']];
$tarjeta = fn (string $id, array $u = []) => ['id' => $id] + $u + $todos;

// 1. Camino feliz, de la presentación a la base de prueba.
$app = crearControlador($db = baseDePrueba(), $reloj);
$r = $app->atender('inicio', $todos);
prueba('feliz: inicio = 200 con el menú del rol (3 opciones) (CU-01)', $r['status'] === 200 && array_column($r['menu'], 'ruta') === ['/pacientes', '/citas', '/camas']);
prueba('feliz: las 3 tarjetas empiezan en «cargando» (R-04)', array_column($r['tarjetas'], 'estado') === ['cargando', 'cargando', 'cargando']);
prueba('feliz: pacientes = 200 «2 pacientes activos» (CU-02)', $app->atender('tarjeta', $tarjeta('pacientes')) === ['status' => 200, 'id' => 'pacientes', 'estado' => 'resumen', 'texto' => '2 pacientes activos']);
prueba('feliz: citas de hoy = «2 citas hoy» (sin canceladas ni de otro día)', $app->atender('tarjeta', $tarjeta('citas_hoy'))['texto'] === '2 citas hoy');
// 2. Reglas de dominio.
$r = $app->atender('tarjeta', $tarjeta('camas_disponibles'));
prueba('regla: 0 camas = 200 «vacío» con sugerencia, no error (R-05)', $r['status'] === 200 && $r['estado'] === 'vacio' && $r['texto'] === 'No hay camas disponibles ahora.');
$soloCitas = ['hospital' => 'h1', 'permisos' => ['citas.ver']];
$r = $app->atender('inicio', $soloCitas);
prueba('regla: el menú y las tarjetas muestran solo lo que el rol permite (R-02)', array_column($r['menu'], 'ruta') === ['/citas'] && array_column($r['tarjetas'], 'id') === ['citas_hoy']);
prueba('regla: tarjeta sin permiso = 403 E4, se oculta (R-07)', $app->atender('tarjeta', $tarjeta('pacientes', $soloCitas))['status'] === 403);
prueba('regla: sin hospital seleccionado = 401 E1 (R-01)', $app->atender('inicio', ['hospital' => ''] + $todos)['error'] === 'E1' && $app->atender('tarjeta', $tarjeta('pacientes', ['hospital' => '']))['status'] === 401);
prueba('regla: la tarjeta solo devuelve id, estado y texto (R-06)', array_keys($app->atender('tarjeta', $tarjeta('pacientes'))) === ['status', 'id', 'estado', 'texto']);

// 3. Error de persistencia: un doble que falla solo en camas y una PDOException real.
$camasCaidas = new class implements FuenteConteos {
    public function conteo(string $t, string $dia): int { return $t === 'camas_disponibles' ? throw new ErrorPersistencia('SQLSTATE[08006] conexión perdida') : 4; }
};
$doble = new ControladorDashboard(new ServicioDashboard($camasCaidas, $reloj));
$r = $doble->atender('tarjeta', $tarjeta('camas_disponibles'));
prueba('persistencia (doble): camas = 503 E5 «error» con texto amable, sin detalles técnicos (R-09)',
       $r['status'] === 503 && $r['estado'] === 'error' && $r['texto'] === 'No pudimos cargar las camas disponibles. Intenta de nuevo.');
prueba('persistencia (doble): las demás tarjetas siguen con su resumen (R-03)', $doble->atender('tarjeta', $tarjeta('pacientes'))['texto'] === '4 pacientes activos');
$db->exec('DROP TABLE beds');
prueba('persistencia (PDO real): PDOException en camas = 503 «error»; pacientes sigue en 200 (R-03)',
       $app->atender('tarjeta', $tarjeta('camas_disponibles'))['status'] === 503 && $app->atender('tarjeta', $tarjeta('pacientes'))['status'] === 200);
$db->exec("CREATE TABLE beds (id INTEGER PRIMARY KEY, status TEXT NOT NULL); INSERT INTO beds (status) VALUES ('available'), ('available')");
prueba('persistencia (PDO real): al reintentar con la base recuperada, la tarjeta vuelve a 200 (CU-04, R-08)', $app->atender('tarjeta', $tarjeta('camas_disponibles'))['texto'] === '2 camas disponibles');
// 4. Regla de dependencias: cada capa solo apunta hacia adentro.
$haciaAfuera = [];
foreach (['Dominio' => ['Aplicacion', 'Persistencia', 'Presentacion'], 'Aplicacion' => ['Persistencia', 'Presentacion'], 'Persistencia' => ['Presentacion'], 'Presentacion' => ['Persistencia']] as $capa => $prohibidas)
    foreach ($prohibidas as $p) if (str_contains(implode('', array_map('file_get_contents', glob(__DIR__ . "/../src/$capa/*.php"))), "MicroHis\\DashboardBase\\$p")) $haciaAfuera[] = "$capa -> $p";
prueba('dependencias: el Dominio no usa otra capa, Aplicación solo usa Dominio, Persistencia y Presentación no se usan entre sí', $haciaAfuera === []);
prueba('PDO: ninguna consulta concatena variables en el SQL', !preg_match('/(prepare|query|exec)\(\s*"[^"]*\$/', file_get_contents(__DIR__ . '/../src/Persistencia/RepositorioPdo.php')));

echo $fallos === 0 ? "RESULTADO: todo correcto\n" : "RESULTADO: $fallos fallos\n";
exit($fallos === 0 ? 0 : 1);
