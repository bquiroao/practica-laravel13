<?php
declare(strict_types=1);

namespace MicroHis\Citas;   // la fábrica vive en el espacio de nombres del módulo: no choca con la de otros Micro-HIS

// Composición: el único lugar que conoce las cuatro capas y las conecta. El adaptador del Repository se elige aquí (PDO o InMemory).
foreach (['Dominio/Cita', 'Aplicacion/ServicioCitas', 'Persistencia/RepositorioPdo', 'Persistencia/RepositorioEnMemoria',
          'Presentacion/VistaJson', 'Presentacion/Rutas', 'Presentacion/ControladorCitas'] as $archivo) {
    require_once __DIR__ . "/src/$archivo.php";
}

function crearControlador(Aplicacion\RepositorioCitas $repositorio, Aplicacion\Reloj $reloj): Presentacion\ControladorCitas
{
    return new Presentacion\ControladorCitas(new Aplicacion\ServicioCitas($repositorio, $reloj), new Presentacion\VistaJson());
}
