<?php
declare(strict_types=1);

// Punto de entrada (Front Controller): traduce la petición y delega en Application.
// El rol llega por parámetro solo en este prototipo; en el SHI sale del token JWT.
require __DIR__ . '/../bootstrap.php';

use MicroHis\Application\ResultadoConsulta;
use MicroHis\Domain\Filtros;
use MicroHis\Presentation\VistaIndicador;

$consulta = array_filter([
    'reporte' => $_GET['reporte'] ?? 'ocupacion',
    'desde' => $_GET['desde'] ?? '2026-10-01',
    'hasta' => $_GET['hasta'] ?? '2026-10-31',
    'dimension' => $_GET['dimension'] ?? 'sala',
    'rol' => $_GET['rol'] ?? 'administrador',
]);

try {
    $resultado = crearCasoDeUso()->ejecutar(
        $consulta['rol'],
        $consulta['reporte'],
        new Filtros($consulta['desde'], $consulta['hasta']),
        $_GET['nivel'] ?? 'indicador',
        $consulta['dimension'],
        $_GET['parte'] ?? null,
    );
} catch (\InvalidArgumentException $e) {
    $resultado = ResultadoConsulta::error(422, $e->getMessage());
}

http_response_code($resultado->codigoHttp);
echo (new VistaIndicador())->html($resultado, $consulta + array_filter(['parte' => $_GET['parte'] ?? null]));
