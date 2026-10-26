<?php
declare(strict_types=1);

namespace MicroHis\DashboardOcupacion\Presentacion;

use MicroHis\DashboardOcupacion\Aplicacion\{ConsolidadorOcupacion, ConsultaOcupacion, ErrorPersistencia};
use MicroHis\DashboardOcupacion\Dominio\ReglaDeDominio;

/** Recibe la petición, llama al caso de uso y traduce el resultado a HTTP. Sin SQL ni reglas de negocio. */
final class ControladorOcupacion
{
    public function __construct(private ConsolidadorOcupacion $consolidador, private ConsultaOcupacion $consulta) {}

    public function atender(string $accion, array $d): array
    {
        try {
            switch ($accion) {
                case 'consolidar': $c = $this->consolidador->consolidar(); return ['status' => 201, 'hora' => $c->hora, 'salas' => count($c->salas)];
                case 'consultar': return ['status' => 200] + $this->consulta->consultar($d['estado'] ?? null);
                default: return ['status' => 404];
            }
        } catch (ReglaDeDominio $e) { return ['status' => 422, 'error' => $e->codigo, 'mensaje' => $e->getMessage()];
        } catch (ErrorPersistencia) { return ['status' => 503, 'error' => 'E8', 'mensaje' => 'No pudimos leer la ocupación. Se conserva el corte anterior.']; }
    }
}
