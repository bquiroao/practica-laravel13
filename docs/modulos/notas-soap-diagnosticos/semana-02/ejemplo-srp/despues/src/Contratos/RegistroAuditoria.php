<?php

declare(strict_types=1);

namespace NotasSoap\Contratos;

/** Bitácora de eventos. No recibe contenido clínico, solo identificadores. */
interface RegistroAuditoria
{
    /** @param array<string, string> $datos */
    public function registrar(string $evento, array $datos): void;
}
