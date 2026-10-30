<?php
declare(strict_types=1);

// Front controller (MVC):  php -S localhost:8080 -t public   (PDO con SQLite en var/traslados.db; TRAS_ALMACEN=memoria usa el adaptador en memoria)
use Traslados\Aplicacion\{RegistrarMovimiento, Usuario};
use Traslados\Persistencia\{EnMemoria, MovimientosPdo};
use Traslados\Presentacion\{Enrutador, MovimientosControlador, Peticion, VistaJson};

require __DIR__ . '/../bootstrap.php';

if (getenv('TRAS_ALMACEN') === 'memoria') {
    $repo = EnMemoria::demo();
} else {
    $archivo = getenv('TRAS_DB') ?: __DIR__ . '/../var/traslados.db';
    $nueva = !is_file($archivo);
    if (!is_dir(dirname($archivo))) { mkdir(dirname($archivo), 0775, true); }
    $pdo = new PDO('sqlite:' . $archivo);
    if ($nueva) { $pdo->exec((string) file_get_contents(__DIR__ . '/../sql/esquema.sql')); }
    $repo = new MovimientosPdo($pdo);
}
$usuarios = ['enf-ana' => new Usuario('enf-ana', 'enfermeria'), 'adm-eva' => new Usuario('adm-eva', 'admision'), 'dr-luis' => new Usuario('dr-luis', 'medico')];
$controlador = new MovimientosControlador(new RegistrarMovimiento($repo, $repo), $usuarios);
$peticion = Peticion::desdeGlobales();
$inicio = hrtime(true);
$respuesta = (new Enrutador($controlador))->atender($peticion);
VistaJson::enviar($respuesta, $peticion->idSolicitud);
error_log(VistaJson::registro($peticion, $respuesta, (hrtime(true) - $inicio) / 1e6));
