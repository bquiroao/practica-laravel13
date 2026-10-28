<?php
declare(strict_types=1);

// Front controller (MVC): ruta -> controlador -> modelo -> vista. Uso: php -S localhost:8000 -t public. Base: CONTRATOS_DSN o un SQLite temporal.
require __DIR__ . '/../bootstrap.php';

use MicroHis\ContratosApi\Persistencia\RepositorioPdo;
use MicroHis\ContratosApi\Presentacion\{Rutas, VistaJson};
use function MicroHis\ContratosApi\crearControlador;

[$accion, $parametros] = Rutas::resolver($_SERVER['REQUEST_METHOD'], (string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$sesion = ['usuario' => (string) ($_SERVER['HTTP_X_USUARIO'] ?? ''), 'roles' => array_values(array_filter(explode(',', (string) ($_SERVER['HTTP_X_ROLES'] ?? ''))))];   // en el HIS: sesión y RBAC
$cuerpo = json_decode((string) file_get_contents('php://input'), true);
$db = new \PDO(getenv('CONTRATOS_DSN') ?: 'sqlite:' . sys_get_temp_dir() . '/micro-his-contratos.sqlite');
$db->exec(file_get_contents(__DIR__ . '/../database/schema.sql'));   // CREATE TABLE IF NOT EXISTS: seguro en cada arranque
VistaJson::emitir(crearControlador(new RepositorioPdo($db))->atender($accion, $parametros + $sesion + (is_array($cuerpo) ? $cuerpo : [])));
