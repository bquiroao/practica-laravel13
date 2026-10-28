<?php
declare(strict_types=1);

namespace MicroHis\ContratosApi;   // fábrica en el espacio de nombres del módulo: no choca con la de otros Micro-HIS

// Composición: único lugar que conoce las cuatro capas; aquí se elige el adaptador del Repository (PDO o InMemory).
foreach (['Dominio/Documento', 'Aplicacion/ServiciosDocumentacion', 'Persistencia/RepositorioPdo', 'Persistencia/RepositorioEnMemoria', 'Presentacion/VistaJson',
          'Presentacion/Rutas', 'Presentacion/ControladorDocumentacion'] as $a) require_once __DIR__ . "/src/$a.php";

function crearControlador(Aplicacion\RepositorioDocumentos $repositorio): Presentacion\ControladorDocumentacion
{   // un solo almacén; cada servicio lo ve solo a través de su puerto (ISP de la semana 2)
    return new Presentacion\ControladorDocumentacion(new Aplicacion\ServicioPublicacion($repositorio), new Aplicacion\ServicioRevision($repositorio),
                                                     new Aplicacion\ServicioConsulta($repositorio), new Presentacion\VistaJson());
}
