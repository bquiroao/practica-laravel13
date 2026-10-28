<?php
declare(strict_types=1);

// Front controller (MVC): ruta -> controlador -> modelo -> vista. Uso: php -S localhost:8000 -t public. Base: OCUPACION_DSN o un SQLite temporal.
require __DIR__ . '/../bootstrap.php';

use MicroHis\DashboardOcupacion\Aplicacion\Reloj;
use MicroHis\DashboardOcupacion\Persistencia\RepositorioPdo;
use MicroHis\DashboardOcupacion\Presentacion\{Rutas, VistaJson};
use function MicroHis\DashboardOcupacion\crearControlador;

[$accion, $parametros] = Rutas::resolver($_SERVER['REQUEST_METHOD'], (string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$filtro = isset($_GET['estado']) ? ['estado' => (string) $_GET['estado']] : [];   // GET /ocupacion?estado=available (CU-03)
$db = new \PDO(getenv('OCUPACION_DSN') ?: 'sqlite:' . sys_get_temp_dir() . '/micro-his-ocupacion.sqlite');
$db->exec(file_get_contents(__DIR__ . '/../database/schema.sql'));   // CREATE TABLE IF NOT EXISTS: seguro en cada arranque
$relojDelSistema = new class implements Reloj { public function ahora(): int { return time(); } };
VistaJson::emitir(crearControlador(new RepositorioPdo($db), $relojDelSistema)->atender($accion, $parametros + $filtro));
