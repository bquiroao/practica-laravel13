<?php
declare(strict_types=1);

// Composition Root: el único lugar donde se crean y conectan las piezas de las cuatro capas.
require __DIR__ . '/src/autoload.php';

use MicroHis\Application\CalcularIndicador;
use MicroHis\Application\PoliticaPermisos;
use MicroHis\Domain\OcupacionRepository;
use MicroHis\Domain\ReportRegistry;
use MicroHis\Domain\ReporteOcupacion;
use MicroHis\Persistence\ConexionPdo;
use MicroHis\Persistence\OcupacionPdoRepository;
use MicroHis\Presentation\Controller\ApiReporteController;
use MicroHis\Presentation\Controller\ReporteController;
use MicroHis\Presentation\Http\Enrutador;
use MicroHis\Presentation\View\JsonIndicador;
use MicroHis\Presentation\View\VistaIndicador;

/** @param string $base «principal» (micro_his) o «prueba» (micro_his_test); $repositorio permite usar InMemory o un doble. */
function crearCasoDeUso(string $base = 'principal', ?OcupacionRepository $repositorio = null): CalcularIndicador
{
    $repositorio ??= new OcupacionPdoRepository(new ConexionPdo(__DIR__ . '/config/app.ini', $base));
    $registro = new ReportRegistry();
    $registro->registrar(new ReporteOcupacion($repositorio));
    return new CalcularIndicador($registro, new PoliticaPermisos());
}

function crearEnrutador(CalcularIndicador $casoDeUso): Enrutador
{
    $controlador = new ReporteController($casoDeUso, new VistaIndicador());
    $enrutador = new Enrutador();
    $enrutador->agregar('GET', '/', [$controlador, 'mostrar']);

    // API REST v1 (semana 5): el mismo caso de uso, servido como JSON para cualquier cliente.
    $api = new ApiReporteController($casoDeUso, new JsonIndicador());
    $enrutador->agregar('GET', '/api/v1/reportes/(?<tipo>[a-z_]+)', [$api, 'indicador']);
    $enrutador->agregar('GET', '/api/v1/reportes/(?<tipo>[a-z_]+)/desglose', [$api, 'desglose']);
    $enrutador->agregar('GET', '/api/v1/reportes/(?<tipo>[a-z_]+)/registros', [$api, 'registros']);
    return $enrutador;
}
