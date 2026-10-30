<?php
declare(strict_types=1);

// Front controller (MVC): ruta -> controlador -> modelo -> vista. Uso: php -S localhost:8000 -t public. Base: DASHBOARD_DSN o un SQLite temporal.
require __DIR__ . '/../bootstrap.php';

use MicroHis\DashboardBase\Aplicacion\Reloj;
use MicroHis\DashboardBase\Persistencia\RepositorioPdo;
use MicroHis\DashboardBase\Presentacion\{Rutas, VistaJson};
use function MicroHis\DashboardBase\crearControlador;

[$accion, $parametros] = Rutas::resolver($_SERVER['REQUEST_METHOD'], (string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$sesion = ['hospital' => (string) ($_SERVER['HTTP_X_TENANT_ID'] ?? ''), 'permisos' => array_values(array_filter(explode(',', (string) ($_SERVER['HTTP_X_PERMISOS'] ?? ''))))];   // en el HIS: sesión y RBAC
$db = new \PDO(getenv('DASHBOARD_DSN') ?: 'sqlite:' . sys_get_temp_dir() . '/micro-his-dashboard.sqlite');
$db->exec(file_get_contents(__DIR__ . '/../database/schema.sql'));   // CREATE TABLE IF NOT EXISTS: seguro en cada arranque
$relojDelSistema = new class implements Reloj { public function hoy(): string { return date('Y-m-d'); } };
VistaJson::emitir(crearControlador(new RepositorioPdo($db), $relojDelSistema)->atender($accion, $parametros + $sesion));
