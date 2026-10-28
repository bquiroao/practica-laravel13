<?php
declare(strict_types=1);

// Front controller (MVC):  php -S localhost:8080 -t public   (PDO con SQLite en var/signos.db; SV_ALMACEN=memoria usa el adaptador en memoria)
use SignosVitales\Aplicacion\{ConsultarTomas, RegistrarToma, Usuario};
use SignosVitales\Persistencia\{EnMemoria, TomasPdo};
use SignosVitales\Presentacion\{Enrutador, Peticion, TomasControlador, VistaJson};

require __DIR__ . '/../bootstrap.php';

if (getenv('SV_ALMACEN') === 'memoria') {
    $repo = new EnMemoria(); // sin episodios: útil solo para ver respuestas de error
} else {
    $archivo = getenv('SV_DB') ?: __DIR__ . '/../var/signos.db';
    $nueva = !is_file($archivo);
    if (!is_dir(dirname($archivo))) { mkdir(dirname($archivo), 0775, true); }
    $pdo = new PDO('sqlite:' . $archivo);
    if ($nueva) { $pdo->exec((string) file_get_contents(__DIR__ . '/../sql/esquema.sql')); }
    $repo = new TomasPdo($pdo);
}
$usuarios = ['enf-ana' => new Usuario('enf-ana', 'enfermeria', 'hosp-1'), 'dr-luis' => new Usuario('dr-luis', 'medico', 'hosp-1')];
$controlador = new TomasControlador(new RegistrarToma($repo, $repo), new ConsultarTomas($repo, $repo), $usuarios);
VistaJson::enviar((new Enrutador($controlador))->atender(Peticion::desdeGlobales()));
