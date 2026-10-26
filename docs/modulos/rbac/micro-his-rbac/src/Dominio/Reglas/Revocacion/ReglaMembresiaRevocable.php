<?php

declare(strict_types=1);

namespace Rbac\Dominio\Reglas\Revocacion;

use Rbac\Dominio\Modelo\ErrorRevocacion;
use Rbac\Dominio\Modelo\Membresia;
use Rbac\Dominio\Modelo\SolicitudRevocacion;
use Rbac\Dominio\Puertos\RepositorioMembresias;
use Rbac\Dominio\Reglas\ReglaRevocacion;

/** Solo se revoca lo que existe: una membresía activa del rol que todavía no venció. */
final class ReglaMembresiaRevocable implements ReglaRevocacion
{
    public function __construct(private readonly RepositorioMembresias $membresias)
    {
    }

    public function evaluar(SolicitudRevocacion $solicitud): ?ErrorRevocacion
    {
        foreach ($this->membresias->membresiasDe($solicitud->usuarioDestinoId, $solicitud->hospitalId) as $m) {
            if (self::esRevocable($m, $solicitud->rol, $solicitud->ahora)) {
                return null;
            }
        }

        return new ErrorRevocacion('MEMBRESIA_NO_ENCONTRADA', 404);
    }

    public static function esRevocable(Membresia $m, string $rol, \DateTimeImmutable $ahora): bool
    {
        return $m->rol === $rol && $m->estado === 'active' && ($m->validoHasta === null || $m->validoHasta > $ahora);
    }
}
