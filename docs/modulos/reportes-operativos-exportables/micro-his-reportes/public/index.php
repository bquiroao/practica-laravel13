<?php
declare(strict_types=1);

// Front controller (MVC):  php -S localhost:8080 -t public   (PDO con SQLite en var/reportes.db; RP_ALMACEN=memoria usa el adaptador en memoria)
use Reportes\Aplicacion\{ExportarReporte, Usuario};
use Reportes\Dominio\Csv;
use Reportes\Persistencia\{EnMemoria, ReportesPdo};
use Reportes\Presentacion\{Enrutador, Peticion, ReportesControlador, VistaHttp};
use function Reportes\Dominio\catalogo;

require __DIR__ . '/../bootstrap.php';

if (getenv('RP_ALMACEN') === 'memoria') {
    $repo = EnMemoria::demo();
} else {
    $archivo = getenv('RP_DB') ?: __DIR__ . '/../var/reportes.db';
    $nueva = !is_file($archivo);
    if (!is_dir(dirname($archivo))) { mkdir(dirname($archivo), 0775, true); }
    $pdo = new PDO('sqlite:' . $archivo);
    if ($nueva) { $pdo->exec((string) file_get_contents(__DIR__ . '/../sql/esquema.sql')); }
    $repo = new ReportesPdo($pdo);
}
$usuarios = ['jefa' => new Usuario('usr-jefa', 'jefatura', 'hosp-1', true), 'auditor' => new Usuario('usr-aud', 'auditor', 'hosp-1', false)];
$controlador = new ReportesControlador(new ExportarReporte($repo, $repo, catalogo(), ['csv' => new Csv()]), $usuarios);
VistaHttp::enviar((new Enrutador($controlador))->atender(Peticion::desdeGlobales()));
