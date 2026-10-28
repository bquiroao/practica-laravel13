<?php
declare(strict_types=1);

namespace MicroHis\DashboardOcupacion\Presentacion;

use MicroHis\DashboardOcupacion\Aplicacion\{ConsolidadorOcupacion, ConsultaOcupacion, ErrorPersistencia};
use MicroHis\DashboardOcupacion\Dominio\ReglaDeDominio;

/** Controlador (MVC): elige el caso de uso del modelo según la acción y entrega el resultado a la vista. Sin SQL ni reglas de negocio. */
final class ControladorOcupacion
{
    public function __construct(private ConsolidadorOcupacion $consolidador, private ConsultaOcupacion $consulta, private VistaJson $vista) {}

    public function atender(string $accion, array $d): array
    {
        try {
            switch ($accion) {
                case 'consolidar': return $this->vista->corteCreado($this->consolidador->consolidar());
                case 'consultar': return $this->vista->consulta($this->consulta->consultar($d['estado'] ?? null));
                default: return $this->vista->rutaDesconocida();
            }
        } catch (ReglaDeDominio $e) { return $this->vista->rechazo($e);
        } catch (ErrorPersistencia) { return $this->vista->noDisponible(); }
    }
}
