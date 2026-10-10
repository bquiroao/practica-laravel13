<?php

declare(strict_types=1);

namespace NotasSoap\EnMemoria;

use NotasSoap\Contratos\RegistroAuditoria;

final class RegistroAuditoriaEnMemoria implements RegistroAuditoria
{
    /** @var list<array{evento: string, datos: array<string, string>}> */
    public array $eventos = [];

    public function registrar(string $evento, array $datos): void
    {
        $this->eventos[] = ['evento' => $evento, 'datos' => $datos];
    }
}
