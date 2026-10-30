<?php
declare(strict_types=1);

namespace MicroHis\DashboardBase\Presentacion;

use MicroHis\DashboardBase\Aplicacion\ServicioDashboard;
use MicroHis\DashboardBase\Dominio\ReglaDeDominio;

/** Controlador (MVC): elige el caso de uso del modelo según la acción y entrega el resultado a la vista. Sin SQL ni reglas de negocio.
 *  'hospital' (X-Tenant-ID) y 'permisos' vendrán de la sesión y de RBAC; en este Micro-HIS llegan en la petición. */
final class ControladorDashboard
{
    public function __construct(private ServicioDashboard $servicio, private VistaJson $vista) {}

    public function atender(string $accion, array $d): array
    {
        $hospital = (string) ($d['hospital'] ?? ''); $permisos = (array) ($d['permisos'] ?? []);
        try {
            switch ($accion) {
                case 'inicio': return $this->vista->inicio($this->servicio->inicio($hospital, $permisos));
                case 'tarjeta': return $this->vista->tarjeta($this->servicio->tarjeta((string) ($d['id'] ?? ''), $hospital, $permisos));
                case 'navegar': return $this->vista->navegacion($this->servicio->navegar((string) ($d['ruta'] ?? ''), $hospital, $permisos));
                default: return $this->vista->rutaDesconocida();
            }
        } catch (ReglaDeDominio $e) { return $this->vista->rechazo($e); }
    }
}
