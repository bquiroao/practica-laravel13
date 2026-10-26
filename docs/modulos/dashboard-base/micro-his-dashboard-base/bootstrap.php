<?php
declare(strict_types=1);

// Composición: el único lugar que conoce las cuatro capas y las conecta. $db es la base del hospital elegido (tenant).
foreach (['Dominio/Dashboard', 'Aplicacion/ServicioDashboard', 'Persistencia/RepositorioPdo', 'Presentacion/ControladorDashboard'] as $a) require_once __DIR__ . "/src/$a.php";

function crearControlador(PDO $db, MicroHis\DashboardBase\Aplicacion\Reloj $reloj): MicroHis\DashboardBase\Presentacion\ControladorDashboard
{
    return new MicroHis\DashboardBase\Presentacion\ControladorDashboard(
        new MicroHis\DashboardBase\Aplicacion\ServicioDashboard(new MicroHis\DashboardBase\Persistencia\RepositorioPdo($db), $reloj));
}
