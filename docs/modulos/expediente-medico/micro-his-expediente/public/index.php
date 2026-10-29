<?php
declare(strict_types=1);

// Front controller (MVC): ruta -> controlador -> modelo -> vista. Uso: php -S localhost:8000 -t public.
// Bases: EXPEDIENTE_DSN (HOSPITAL) y CENTRAL_DSN, o SQLite temporales. Profesional y hospital: cabeceras X-Profesional y X-Tenant-ID (hasta tener sesión).
require __DIR__ . '/../bootstrap.php';

use MicroHis\Expediente\Aplicacion\Reloj;
use MicroHis\Expediente\Persistencia\{CentralPdo, HospitalPdo};
use MicroHis\Expediente\Presentacion\{Rutas, VistaJson};
use function MicroHis\Expediente\crearControlador;

function base(string $variable, string $esquema, string $archivo): \PDO   // CREATE TABLE IF NOT EXISTS: seguro en cada arranque
{ $db = new \PDO(getenv($variable) ?: 'sqlite:' . sys_get_temp_dir() . "/$archivo"); $db->exec(file_get_contents(__DIR__ . "/../database/$esquema")); return $db; }

[$accion, $parametros] = Rutas::resolver($_SERVER['REQUEST_METHOD'], (string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$relojDelSistema = new class implements Reloj { public function ahora(): int { return time(); } };
$controlador = crearControlador(new HospitalPdo(base('EXPEDIENTE_DSN', 'schema.sql', 'micro-his-expediente.sqlite')),
    new CentralPdo(base('CENTRAL_DSN', 'schema-central.sql', 'micro-his-central.sqlite')), $relojDelSistema, $_SERVER['HTTP_X_TENANT_ID'] ?? 'h1');
VistaJson::emitir($controlador->atender($accion, $parametros + ['profesional' => $_SERVER['HTTP_X_PROFESIONAL'] ?? '']));
