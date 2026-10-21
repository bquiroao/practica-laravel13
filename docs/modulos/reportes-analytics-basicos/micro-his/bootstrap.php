<?php
declare(strict_types=1);

// Composition Root: el único lugar donde se crean y conectan las piezas de las cuatro capas.
require __DIR__ . '/src/autoload.php';

use MicroHis\Application\CalcularIndicador;
use MicroHis\Application\PoliticaPermisos;
use MicroHis\Domain\LectorOcupacion;
use MicroHis\Domain\ReportRegistry;
use MicroHis\Domain\ReporteOcupacion;
use MicroHis\Persistence\ConexionPdo;
use MicroHis\Persistence\OcupacionPdo;

/** @param string $base «principal» (micro_his) o «prueba» (micro_his_test); $lector permite inyectar un doble. */
function crearCasoDeUso(string $base = 'principal', ?LectorOcupacion $lector = null): CalcularIndicador
{
    $lector ??= new OcupacionPdo(new ConexionPdo(__DIR__ . '/config/app.ini', $base));
    $registro = new ReportRegistry();
    $registro->registrar(new ReporteOcupacion($lector));
    return new CalcularIndicador($registro, new PoliticaPermisos());
}
