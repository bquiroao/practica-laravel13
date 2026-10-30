<?php

declare(strict_types=1);

namespace NotasSoap\Aplicacion\Servicios;

use NotasSoap\Aplicacion\Puertos\Transaccion;
use NotasSoap\Dominio\Cambios\CambioClinico;
use NotasSoap\Dominio\Puertos\RegistroAuditoria;
use NotasSoap\Dominio\Puertos\RepositorioDiagnosticos;
use NotasSoap\Dominio\Puertos\RepositorioNotas;

/**
 * Semana 7: el ÚNICO servicio que guarda un cambio clínico junto con su evento de bitácora,
 * en una sola transacción (R-06). Antes ese bloque estaba copiado en Registrar, Corregir y Firmar.
 * Si algo falla, se revierte todo y la excepción sigue hacia arriba (el Kernel la traduce a 500).
 */
final class RegistradorClinico
{
    public function __construct(
        private readonly RepositorioNotas $notas,
        private readonly RepositorioDiagnosticos $diagnosticos,
        private readonly RegistroAuditoria $auditoria,
        private readonly Transaccion $transaccion,
    ) {
    }

    /** @param (\Closure(): void)|null $tambien trabajo extra que debe quedar en la misma transacción (por ejemplo, la clave de idempotencia) */
    public function guardar(CambioClinico $cambio, ?\Closure $tambien = null): void
    {
        $this->transaccion->ejecutar(function () use ($cambio, $tambien): void {
            $cambio->aplicarEn($this->notas, $this->diagnosticos);
            $entrada = $cambio->entrada();
            $this->auditoria->registrar($entrada->evento, $entrada->datos);
            if ($tambien !== null) {
                $tambien();
            }
        });
    }
}
