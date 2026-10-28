<?php

declare(strict_types=1);

namespace Rbac\Aplicacion\Servicios;

use Rbac\Aplicacion\Puertos\Transaccion;
use Rbac\Dominio\Cambios\CambioDeMembresia;
use Rbac\Dominio\Excepciones\MembresiaDuplicada;
use Rbac\Dominio\Puertos\RegistroAuditoria;
use Rbac\Dominio\Puertos\RepositorioMembresias;

/**
 * Semana 7 — único lugar que aplica un cambio de membresía con su evidencia (R-07):
 * transacción + cambio + evento, y traducción de las fallas técnicas a un Desenlace.
 * Antes, este bloque estaba copiado en AsignarRol y en RevocarRol.
 */
final class RegistradorDeCambios
{
    public function __construct(
        private readonly Transaccion $transaccion,
        private readonly RepositorioMembresias $membresias,
        private readonly RegistroAuditoria $auditoria,
    ) {
    }

    public function aplicar(CambioDeMembresia $cambio): Desenlace
    {
        $afectadas = 0;

        try {
            $this->transaccion->ejecutar(function () use ($cambio, &$afectadas): void {
                $afectadas = $cambio->aplicarEn($this->membresias);
                if ($afectadas > 0) {
                    $this->auditoria->registrar($cambio->evento($afectadas));
                }
            });
        } catch (MembresiaDuplicada) {
            return Desenlace::DUPLICADO;
        } catch (\Throwable) {
            return Desenlace::FALLA;
        }

        return $afectadas > 0 ? Desenlace::APLICADO : Desenlace::SIN_EFECTO;
    }
}
