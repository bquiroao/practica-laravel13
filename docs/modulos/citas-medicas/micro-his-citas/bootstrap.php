<?php
declare(strict_types=1);

// Composición: el único lugar que conoce las cuatro capas y las conecta.
foreach (['Dominio/Cita', 'Aplicacion/ServicioCitas', 'Persistencia/RepositorioPdo', 'Presentacion/ControladorCitas'] as $archivo) {
    require_once __DIR__ . "/src/$archivo.php";
}

function crearControlador(PDO $db, MicroHis\Citas\Aplicacion\Reloj $reloj): MicroHis\Citas\Presentacion\ControladorCitas
{
    return new MicroHis\Citas\Presentacion\ControladorCitas(
        new MicroHis\Citas\Aplicacion\ServicioCitas(new MicroHis\Citas\Persistencia\RepositorioPdo($db), $reloj));
}
