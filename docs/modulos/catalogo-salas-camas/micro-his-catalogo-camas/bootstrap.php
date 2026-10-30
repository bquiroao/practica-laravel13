<?php
declare(strict_types=1);

namespace MicroHis\Catalogo;   // la fábrica vive en el espacio de nombres del módulo: no choca con la de otros Micro-HIS

// Composición: el único lugar que conoce las cuatro capas y las conecta. El adaptador del Repository se elige aquí (PDO o InMemory).
foreach (['Dominio/EstadoCama', 'Dominio/CapacidadSala', 'Aplicacion/ServicioCatalogo', 'Persistencia/RepositorioPdo', 'Persistencia/RepositorioEnMemoria',
          'Presentacion/VistaJson', 'Presentacion/Rutas', 'Presentacion/ControladorCatalogo'] as $archivo) {
    require_once __DIR__ . "/src/$archivo.php";
}

function crearControlador(Aplicacion\RepositorioCatalogo $repositorio): Presentacion\ControladorCatalogo
{
    return new Presentacion\ControladorCatalogo(new Aplicacion\ServicioCatalogo($repositorio), new Presentacion\VistaJson());
}
