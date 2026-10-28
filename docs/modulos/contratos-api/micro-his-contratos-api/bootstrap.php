<?php
declare(strict_types=1);

// Composición: el único lugar que conoce las cuatro capas y las conecta.
foreach (['Dominio/Documento', 'Aplicacion/ServiciosDocumentacion', 'Persistencia/RepositorioPdo', 'Presentacion/ControladorDocumentacion'] as $a) require_once __DIR__ . "/src/$a.php";

function crearControlador(PDO $db): MicroHis\ContratosApi\Presentacion\ControladorDocumentacion
{
    $repo = new MicroHis\ContratosApi\Persistencia\RepositorioPdo($db);   // un solo almacén; cada servicio lo ve solo por su puerto
    return new MicroHis\ContratosApi\Presentacion\ControladorDocumentacion(new MicroHis\ContratosApi\Aplicacion\ServicioPublicacion($repo),
        new MicroHis\ContratosApi\Aplicacion\ServicioRevision($repo), new MicroHis\ContratosApi\Aplicacion\ServicioConsulta($repo));
}
