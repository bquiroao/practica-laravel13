<?php

declare(strict_types=1);

namespace Rbac\Extension;

use Rbac\Contratos\ReglaAutorizacion;
use Rbac\Dominio\ContextoAutorizacion;

/**
 * EXTENSIÓN que demuestra OCP: requerimiento nuevo, sin modificar el código existente.
 *
 * «Los permisos sensibles solo se ejercen desde la red interna del hospital.»
 * Se agrega como clase nueva que implementa ReglaAutorizacion y se registra en
 * composicion.php. El Autorizador y las demás reglas no cambian.
 */
final class ReglaRedHospitalaria implements ReglaAutorizacion
{
    /** @param list<string> $permisosSensibles */
    public function __construct(
        private readonly array $permisosSensibles,
        private readonly string $redPermitida,
    ) {
    }

    public function evaluar(ContextoAutorizacion $contexto): ?string
    {
        $solicitud = $contexto->solicitud;

        if (!in_array($solicitud->permiso, $this->permisosSensibles, true)) {
            return null;
        }

        return $this->ipEnRed($solicitud->ip) ? null : 'RED_NO_AUTORIZADA';
    }

    private function ipEnRed(string $ip): bool
    {
        [$red, $bits] = explode('/', $this->redPermitida);
        $ipLong = ip2long($ip);
        $redLong = ip2long($red);
        if ($ipLong === false || $redLong === false) {
            return false;
        }

        $mascara = -1 << (32 - (int) $bits);

        return ($ipLong & $mascara) === ($redLong & $mascara);
    }
}
