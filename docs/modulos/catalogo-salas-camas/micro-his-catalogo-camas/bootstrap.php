<?php
declare(strict_types=1);

// Composición: el único lugar que conoce las cuatro capas y las conecta.
foreach (['Dominio/EstadoCama', 'Aplicacion/ServicioCatalogo', 'Persistencia/RepositorioPdo', 'Presentacion/ControladorCatalogo'] as $archivo) {
    require_once __DIR__ . "/src/$archivo.php";
}

function crearControlador(PDO $db): MicroHis\Catalogo\Presentacion\ControladorCatalogo
{
    return new MicroHis\Catalogo\Presentacion\ControladorCatalogo(
        new MicroHis\Catalogo\Aplicacion\ServicioCatalogo(new MicroHis\Catalogo\Persistencia\RepositorioPdo($db)));
}
