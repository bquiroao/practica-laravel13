<?php
declare(strict_types=1);

// Composición: el único lugar que conoce las cuatro capas y las conecta. Recibe las dos bases: HOSPITAL (tenant) y CENTRAL.
foreach (['Dominio/Expediente', 'Aplicacion/ServicioExpediente', 'Persistencia/RepositoriosPdo', 'Presentacion/ControladorExpediente'] as $a) require_once __DIR__ . "/src/$a.php";

function crearControlador(PDO $hospital, PDO $central, MicroHis\Expediente\Aplicacion\Reloj $reloj, string $hospitalId): MicroHis\Expediente\Presentacion\ControladorExpediente
{
    $local = new MicroHis\Expediente\Persistencia\HospitalPdo($hospital);
    return new MicroHis\Expediente\Presentacion\ControladorExpediente(new MicroHis\Expediente\Aplicacion\ServicioExpediente(
        $local, new MicroHis\Expediente\Persistencia\CentralPdo($central), $local, $reloj, $hospitalId));
}
