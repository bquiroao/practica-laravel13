<?php

declare(strict_types=1);

namespace NotasSoap\Persistencia\EnMemoria;

use NotasSoap\Dominio\Puertos\RegistroAuditoria;

final class RegistroAuditoriaEnMemoria implements RegistroAuditoria, Restaurable
{
    /** @var list<array{evento: string, datos: array<string, string>}> */
    private array $eventos = [];

    public function registrar(string $evento, array $datos): void
    {
        $this->eventos[] = ['evento' => $evento, 'datos' => $datos];
    }

    /** @return list<array{evento: string, datos: array<string, string>}> */
    public function eventos(): array
    {
        return $this->eventos;
    }

    public function instantanea(): array
    {
        return $this->eventos;
    }

    public function restaurar(array $estado): void
    {
        $this->eventos = $estado;
    }
}
