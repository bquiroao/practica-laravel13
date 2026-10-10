<?php

declare(strict_types=1);

namespace Rbac\Pruebas\Dobles;

use Rbac\Dominio\Excepciones\ErrorPersistencia;
use Rbac\Dominio\Modelo\EventoAuditoria;
use Rbac\Dominio\Puertos\RegistroAuditoria;

final class RegistroAuditoriaEnMemoria implements RegistroAuditoria
{
    public bool $fallar = false;

    /** @var list<EventoAuditoria> */
    private array $eventos = [];

    public function registrar(EventoAuditoria $evento): void
    {
        if ($this->fallar) {
            throw new ErrorPersistencia('Bitácora simulada no disponible', '08006');
        }
        $this->eventos[] = $evento;
    }

    public function buscarPorCorrelacion(string $correlationId): ?EventoAuditoria
    {
        foreach ($this->eventos as $evento) {
            if ($evento->correlationId === $correlationId) {
                return $evento;
            }
        }

        return null;
    }

    /** @return list<EventoAuditoria> */
    public function eventos(): array
    {
        return $this->eventos;
    }

    /** @return list<EventoAuditoria> */
    public function eventosDeTipo(string $tipo): array
    {
        return array_values(array_filter($this->eventos, static fn (EventoAuditoria $e): bool => $e->tipo === $tipo));
    }
}
