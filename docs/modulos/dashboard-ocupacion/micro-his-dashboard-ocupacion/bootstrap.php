<?php
declare(strict_types=1);

// Composición: el único lugar que conoce las cuatro capas y las conecta.
foreach (['Dominio/Ocupacion', 'Aplicacion/ServiciosOcupacion', 'Persistencia/RepositorioPdo', 'Presentacion/ControladorOcupacion'] as $a) require_once __DIR__ . "/src/$a.php";

function crearControlador(PDO $db, MicroHis\DashboardOcupacion\Aplicacion\Reloj $reloj): MicroHis\DashboardOcupacion\Presentacion\ControladorOcupacion
{
    $repo = new MicroHis\DashboardOcupacion\Persistencia\RepositorioPdo($db);   // un solo almacén; cada servicio lo ve solo por sus puertos
    return new MicroHis\DashboardOcupacion\Presentacion\ControladorOcupacion(
        new MicroHis\DashboardOcupacion\Aplicacion\ConsolidadorOcupacion($repo, $repo, $reloj), new MicroHis\DashboardOcupacion\Aplicacion\ConsultaOcupacion($repo, $reloj));
}
