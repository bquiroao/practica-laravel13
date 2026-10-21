<?php

declare(strict_types=1);

namespace Pacientes\Pruebas\Dobles;

use Pacientes\Dominio\Excepciones\ErrorPersistencia;
use Pacientes\Dominio\Modelo\EventoAuditoria;
use Pacientes\Dominio\Puertos\RegistroAuditoria;

final class RegistroAuditoriaEnMemoria implements RegistroAuditoria, Restaurable
{
    /** @var list<EventoAuditoria> */
    private array $eventos = [];
    public bool $fallar = false;

    public function registrar(EventoAuditoria $evento): void
    {
        if ($this->fallar) {
            throw new ErrorPersistencia('Simulado: bitácora no disponible', '08006');
        }
        $this->eventos[] = $evento;
    }

    /** @return list<EventoAuditoria> */
    public function eventos(?string $accion = null): array
    {
        return array_values(array_filter($this->eventos, static fn (EventoAuditoria $e): bool => $accion === null || $e->accion === $accion));
    }

    public function instantanea(): mixed
    {
        return $this->eventos;
    }

    public function restaurar(mixed $instantanea): void
    {
        $this->eventos = $instantanea;
    }
}
