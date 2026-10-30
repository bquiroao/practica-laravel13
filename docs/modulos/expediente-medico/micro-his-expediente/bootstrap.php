<?php
declare(strict_types=1);

namespace MicroHis\Expediente;   // fábrica en el espacio de nombres del módulo: no choca con la de otros Micro-HIS

// Composición: único lugar que conoce las cuatro capas; aquí se eligen los adaptadores de HOSPITAL (tenant) y CENTRAL (PDO o InMemory).
foreach (['Dominio/Expediente', 'Aplicacion/ServicioExpediente', 'Persistencia/RepositoriosPdo', 'Persistencia/RepositoriosEnMemoria', 'Presentacion/VistaJson',
          'Presentacion/Rutas', 'Presentacion/ControladorExpediente'] as $a) require_once __DIR__ . "/src/$a.php";

function crearControlador(Aplicacion\RepositorioExpedientes $hospital, Aplicacion\IndiceCentral $central, Aplicacion\Reloj $reloj, string $hospitalId): Presentacion\ControladorExpediente
{   // el mismo almacén de HOSPITAL sirve de expedientes y de auditoría
    return new Presentacion\ControladorExpediente(new Aplicacion\ServicioExpediente($hospital, $central, $hospital, $reloj, $hospitalId), new Presentacion\VistaJson());
}
