<?php
declare(strict_types=1);

// El mismo guion (camino feliz y reglas) contra los adaptadores PDO e InMemory, error de persistencia, MVC y dependencias. Datos ficticios.
require __DIR__ . '/../bootstrap.php';

use MicroHis\DashboardBase\Aplicacion\{ErrorPersistencia, FuenteConteos, Reloj, ServicioDashboard};
use MicroHis\DashboardBase\Persistencia\{RepositorioEnMemoria, RepositorioPdo};
use MicroHis\DashboardBase\Presentacion\{ControladorDashboard, Rutas, VistaJson};
use function MicroHis\DashboardBase\crearControlador;

$fallos = 0;
function prueba(string $nombre, bool $ok): void { global $fallos; $fallos += $ok ? 0 : 1; echo ($ok ? 'OK    ' : 'FALLA ') . "$nombre\n"; }
function baseDePrueba(): PDO { $db = new PDO('sqlite::memory:'); $db->exec(file_get_contents(__DIR__ . '/../database/schema.sql'));
    $db->exec("INSERT INTO local_patients (status) VALUES ('active'), ('active'), ('inactive');
               INSERT INTO appointments (starts_at, status) VALUES ('2026-10-26 08:00', 'scheduled'), ('2026-10-26 09:00', 'confirmed'), ('2026-10-26 10:00', 'cancelled'), ('2026-10-27 08:00', 'scheduled');
               INSERT INTO beds (status) VALUES ('occupied'), ('maintenance');"); return $db; }
$memoria = new RepositorioEnMemoria(['active', 'active', 'inactive'],   // los mismos datos de la base de prueba
    [['2026-10-26 08:00', 'scheduled'], ['2026-10-26 09:00', 'confirmed'], ['2026-10-26 10:00', 'cancelled'], ['2026-10-27 08:00', 'scheduled']], ['occupied', 'maintenance']);
$reloj = new class implements Reloj { public function hoy(): string { return '2026-10-26'; } };   // «hoy» fijo para contar las citas del día
$todos = ['hospital' => 'h1', 'permisos' => ['pacientes.ver', 'citas.ver', 'camas.ver']];
$tarjeta = fn (string $id, array $u = []) => ['id' => $id] + $u + $todos;

// 1 y 2. Camino feliz y reglas de dominio: el mismo guion con cada adaptador (contrato del Repository).
$db = baseDePrueba();
foreach (['PDO' => new RepositorioPdo($db), 'InMemory' => $memoria] as $adaptador => $repo) {
    $app = crearControlador($repo, $reloj);
    $r = $app->atender('inicio', $todos);
    prueba("[$adaptador] feliz: inicio = 200 con el menú del rol y sus 3 tarjetas en «cargando» (CU-01, R-04)", $r['status'] === 200
           && array_column($r['menu'], 'ruta') === ['/pacientes', '/citas', '/camas'] && array_column($r['tarjetas'], 'estado') === ['cargando', 'cargando', 'cargando']);
    prueba("[$adaptador] feliz: pacientes = 200 «2 pacientes activos» (CU-02)", $app->atender('tarjeta', $tarjeta('pacientes')) === ['status' => 200, 'id' => 'pacientes', 'estado' => 'resumen', 'texto' => '2 pacientes activos']);
    prueba("[$adaptador] feliz: citas de hoy = «2 citas hoy» (sin canceladas ni de otro día)", $app->atender('tarjeta', $tarjeta('citas_hoy'))['texto'] === '2 citas hoy');
    $r = $app->atender('tarjeta', $tarjeta('camas_disponibles'));
    prueba("[$adaptador] regla: 0 camas = 200 «vacío» con sugerencia, no error (R-05)", $r['status'] === 200 && $r['estado'] === 'vacio' && $r['texto'] === 'No hay camas disponibles ahora.');
    $soloCitas = ['hospital' => 'h1', 'permisos' => ['citas.ver']];
    $r = $app->atender('inicio', $soloCitas);
    prueba("[$adaptador] regla: el menú y las tarjetas muestran solo lo que el rol permite (R-02)", array_column($r['menu'], 'ruta') === ['/citas'] && array_column($r['tarjetas'], 'id') === ['citas_hoy']);
    prueba("[$adaptador] regla: tarjeta sin permiso = 403 E4, se oculta (R-07)", $app->atender('tarjeta', $tarjeta('pacientes', $soloCitas))['status'] === 403);
    prueba("[$adaptador] regla: sin hospital seleccionado = 401 E1 (R-01)", $app->atender('inicio', ['hospital' => ''] + $todos)['error'] === 'E1' && $app->atender('tarjeta', $tarjeta('pacientes', ['hospital' => '']))['status'] === 401);
    prueba("[$adaptador] regla: la tarjeta solo devuelve id, estado y texto (R-06)", array_keys($app->atender('tarjeta', $tarjeta('pacientes'))) === ['status', 'id', 'estado', 'texto']);
    prueba("[$adaptador] contrato: los conteos de pacientes, citas de hoy y camas disponibles son 2, 2 y 0", [$repo->conteo('pacientes', '2026-10-26'), $repo->conteo('citas_hoy', '2026-10-26'), $repo->conteo('camas_disponibles', '2026-10-26')] === [2, 2, 0]);
}

prueba('[InMemory] regla: con un solo registro el texto va en singular («1 cita hoy») (R-09)', crearControlador(new RepositorioEnMemoria([], [['2026-10-26 08:00', 'scheduled']]), $reloj)->atender('tarjeta', $tarjeta('citas_hoy'))['texto'] === '1 cita hoy');

// 3. Error de persistencia: un doble que falla solo en camas y una PDOException real.
$camasCaidas = new class implements FuenteConteos {
    public function conteo(string $t, string $dia): int { return $t === 'camas_disponibles' ? throw new ErrorPersistencia('SQLSTATE[08006] conexión perdida') : 4; }
};
$doble = new ControladorDashboard(new ServicioDashboard($camasCaidas, $reloj), new VistaJson());
$r = $doble->atender('tarjeta', $tarjeta('camas_disponibles'));
prueba('persistencia (doble): camas = 503 E5 «error» con texto amable, sin detalles técnicos (R-09)',
       $r['status'] === 503 && $r['estado'] === 'error' && $r['texto'] === 'No pudimos cargar las camas disponibles. Intenta de nuevo.');
prueba('persistencia (doble): las demás tarjetas siguen con su resumen (R-03)', $doble->atender('tarjeta', $tarjeta('pacientes'))['texto'] === '4 pacientes activos');
$app = crearControlador(new RepositorioPdo($db), $reloj);
$db->exec('DROP TABLE beds');
prueba('persistencia (PDO real): PDOException en camas = 503 «error»; pacientes sigue en 200 (R-03)',
       $app->atender('tarjeta', $tarjeta('camas_disponibles'))['status'] === 503 && $app->atender('tarjeta', $tarjeta('pacientes'))['status'] === 200);
$db->exec("CREATE TABLE beds (id INTEGER PRIMARY KEY, status TEXT NOT NULL); INSERT INTO beds (status) VALUES ('available'), ('available')");
prueba('persistencia (PDO real): al reintentar con la base recuperada, la tarjeta vuelve a 200 (CU-04, R-08)', $app->atender('tarjeta', $tarjeta('camas_disponibles'))['texto'] === '2 camas disponibles');

// 4. Entrada MVC: rutas, controlador sin SQL ni reglas, y regla de dependencias.
prueba('MVC: las rutas llevan a su acción con el id de la tarjeta, y una ruta desconocida responde 404',
       [Rutas::resolver('GET', '/dashboard'), Rutas::resolver('GET', '/dashboard/tarjetas/citas_hoy'), Rutas::resolver('POST', '/dashboard')] === [['inicio', []], ['tarjeta', ['id' => 'citas_hoy']], ['', []]]
       && crearControlador($memoria, $reloj)->atender('', [])['status'] === 404);
prueba('MVC: el controlador no tiene SQL, PDO, estados de tarjeta, catálogo ni throw', !preg_match('/\b(SELECT|INSERT|UPDATE|DELETE)\b|PDO|EstadoTarjeta|Catalogo|throw /', file_get_contents(__DIR__ . '/../src/Presentacion/ControladorDashboard.php')));
$haciaAfuera = [];
foreach (['Dominio' => ['Aplicacion', 'Persistencia', 'Presentacion'], 'Aplicacion' => ['Persistencia', 'Presentacion'], 'Persistencia' => ['Presentacion'], 'Presentacion' => ['Persistencia']] as $capa => $prohibidas)
    foreach ($prohibidas as $p) if (str_contains(implode('', array_map('file_get_contents', glob(__DIR__ . "/../src/$capa/*.php"))), "MicroHis\\DashboardBase\\$p")) $haciaAfuera[] = "$capa -> $p";
prueba('dependencias: el Dominio no usa otra capa, Aplicación solo usa Dominio, Persistencia y Presentación no se usan entre sí', $haciaAfuera === []);
prueba('PDO: ninguna consulta concatena variables en el SQL', !preg_match('/(prepare|query|exec)\(\s*"[^"]*\$/', file_get_contents(__DIR__ . '/../src/Persistencia/RepositorioPdo.php')));

echo $fallos === 0 ? "RESULTADO: todo correcto\n" : "RESULTADO: $fallos fallos\n";
exit($fallos === 0 ? 0 : 1);
