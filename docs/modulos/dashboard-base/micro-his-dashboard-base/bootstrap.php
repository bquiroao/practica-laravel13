<?php
declare(strict_types=1);

namespace MicroHis\DashboardBase;   // fábrica en el espacio de nombres del módulo: no choca con la de otros Micro-HIS

// Composición: único lugar que conoce las cuatro capas; aquí se elige el adaptador del Repository (PDO sobre la base del tenant, o InMemory).
foreach (['Dominio/Dashboard', 'Aplicacion/ServicioDashboard', 'Persistencia/RepositorioPdo', 'Persistencia/RepositorioEnMemoria', 'Presentacion/VistaJson',
          'Presentacion/Rutas', 'Presentacion/ControladorDashboard'] as $a) require_once __DIR__ . "/src/$a.php";

function crearControlador(Aplicacion\FuenteConteos $repositorio, Aplicacion\Reloj $reloj): Presentacion\ControladorDashboard
{
    return new Presentacion\ControladorDashboard(new Aplicacion\ServicioDashboard($repositorio, $reloj), new Presentacion\VistaJson());
}
