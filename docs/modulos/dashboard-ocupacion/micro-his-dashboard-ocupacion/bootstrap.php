<?php
declare(strict_types=1);

namespace MicroHis\DashboardOcupacion;   // fábrica en el espacio de nombres del módulo: no choca con la de otros Micro-HIS

// Composición: único lugar que conoce las cuatro capas; aquí se elige el adaptador del Repository (PDO o InMemory).
foreach (['Dominio/Ocupacion', 'Aplicacion/ServiciosOcupacion', 'Persistencia/RepositorioPdo', 'Persistencia/RepositorioEnMemoria', 'Presentacion/VistaJson',
          'Presentacion/Rutas', 'Presentacion/ControladorOcupacion'] as $a) require_once __DIR__ . "/src/$a.php";

function crearControlador(Aplicacion\RepositorioOcupacion $repositorio, Aplicacion\Reloj $reloj): Presentacion\ControladorOcupacion
{   // un solo almacén; cada servicio lo ve solo a través de sus puertos (ISP de la semana 2)
    return new Presentacion\ControladorOcupacion(new Aplicacion\ConsolidadorOcupacion($repositorio, $repositorio, $reloj),
                                                 new Aplicacion\ConsultaOcupacion($repositorio, $reloj), new Presentacion\VistaJson());
}
