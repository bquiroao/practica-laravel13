<?php
declare(strict_types=1);

// Front controller (entrada MVC): ruta -> controlador -> modelo -> vista. Uso: php -S localhost:8000 -t public
// La base se elige con CATALOGO_DSN; por omisión, un SQLite en la carpeta temporal del sistema (nunca dentro del repositorio).
require __DIR__ . '/../bootstrap.php';

use MicroHis\Catalogo\Persistencia\RepositorioPdo;
use MicroHis\Catalogo\Presentacion\{Rutas, VistaJson};
use function MicroHis\Catalogo\crearControlador;

[$accion, $parametros] = Rutas::resolver($_SERVER['REQUEST_METHOD'], (string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$cuerpo = json_decode((string) file_get_contents('php://input'), true);
$db = new PDO(getenv('CATALOGO_DSN') ?: 'sqlite:' . sys_get_temp_dir() . '/micro-his-catalogo.sqlite');
$db->exec(file_get_contents(__DIR__ . '/../database/schema.sql'));   // CREATE TABLE IF NOT EXISTS: seguro en cada arranque
VistaJson::emitir(crearControlador(new RepositorioPdo($db))->atender($accion, $parametros + (is_array($cuerpo) ? $cuerpo : [])));
