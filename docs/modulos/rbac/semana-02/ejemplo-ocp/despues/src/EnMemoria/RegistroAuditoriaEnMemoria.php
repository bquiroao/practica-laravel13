<?php

declare(strict_types=1);

namespace Rbac\EnMemoria;

use Rbac\Contratos\RegistroAuditoria;
use Rbac\Dominio\EventoAuditoria;

/** Bitácora append-only en memoria: no expone métodos para editar ni borrar. */
final class RegistroAuditoriaEnMemoria implements RegistroAuditoria
{
    /** @var list<EventoAuditoria> */
    private array $eventos = [];

    public function registrar(EventoAuditoria $evento): void
    {
        $this->eventos[] = $evento;
    }

    /** @return list<EventoAuditoria> */
    public function eventos(): array
    {
        return $this->eventos;
    }

    /** CU-06: buscar un evento por el correlation_id que vio el usuario. */
    public function buscarPorCorrelacion(string $correlationId): ?EventoAuditoria
    {
        foreach ($this->eventos as $evento) {
            if ($evento->correlationId === $correlationId) {
                return $evento;
            }
        }

        return null;
    }
}
