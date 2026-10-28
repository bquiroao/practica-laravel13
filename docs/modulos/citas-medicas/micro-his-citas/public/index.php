<?php
declare(strict_types=1);

// Front controller (MVC): ruta -> controlador -> modelo -> vista. Uso: php -S localhost:8000 -t public. Base: CITAS_DSN o un SQLite temporal.
require __DIR__ . '/../bootstrap.php';

use MicroHis\Citas\Aplicacion\Reloj;
use MicroHis\Citas\Persistencia\RepositorioPdo;
use MicroHis\Citas\Presentacion\{Rutas, VistaJson};
use function MicroHis\Citas\crearControlador;

[$accion, $parametros] = Rutas::resolver($_SERVER['REQUEST_METHOD'], (string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$cuerpo = json_decode((string) file_get_contents('php://input'), true);
$db = new PDO(getenv('CITAS_DSN') ?: 'sqlite:' . sys_get_temp_dir() . '/micro-his-citas.sqlite');
$db->exec(file_get_contents(__DIR__ . '/../database/schema.sql'));   // CREATE TABLE IF NOT EXISTS: seguro en cada arranque
$relojDelSistema = new class implements Reloj { public function ahora(): int { return time(); } };
VistaJson::emitir(crearControlador(new RepositorioPdo($db), $relojDelSistema)->atender($accion, $parametros + (is_array($cuerpo) ? $cuerpo : [])));
