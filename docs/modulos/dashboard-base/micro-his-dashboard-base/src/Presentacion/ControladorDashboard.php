<?php
declare(strict_types=1);

namespace MicroHis\DashboardBase\Presentacion;

use MicroHis\DashboardBase\Aplicacion\ServicioDashboard;
use MicroHis\DashboardBase\Dominio\{EstadoTarjeta, ReglaDeDominio};

/** Recibe la petición, llama al caso de uso y traduce el resultado a HTTP. Sin SQL ni reglas de negocio.
 *  'hospital' (X-Tenant-ID) y 'permisos' vendrán de la sesión y de RBAC; en este Micro-HIS llegan en la petición. */
final class ControladorDashboard
{
    private const HTTP = ['E1' => 401, 'E4' => 403];

    public function __construct(private ServicioDashboard $servicio) {}

    public function atender(string $accion, array $d): array
    {
        $hospital = (string) ($d['hospital'] ?? ''); $permisos = (array) ($d['permisos'] ?? []);
        try {
            switch ($accion) {
                case 'inicio': return ['status' => 200] + $this->servicio->inicio($hospital, $permisos);
                case 'tarjeta':
                    $t = $this->servicio->tarjeta((string) ($d['id'] ?? ''), $hospital, $permisos);
                    return ($t['estado'] === EstadoTarjeta::Error->value ? ['status' => 503, 'error' => 'E5'] : ['status' => 200]) + $t;
                default: return ['status' => 404];
            }
        } catch (ReglaDeDominio $e) { return ['status' => self::HTTP[$e->codigo], 'error' => $e->codigo, 'mensaje' => $e->getMessage()]; }
    }
}
