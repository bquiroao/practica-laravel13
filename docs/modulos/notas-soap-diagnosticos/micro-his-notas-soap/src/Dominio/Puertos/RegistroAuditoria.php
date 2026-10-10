<?php

declare(strict_types=1);

namespace NotasSoap\Dominio\Puertos;

/** Bitácora del módulo: solo identificadores, nunca contenido clínico. */
interface RegistroAuditoria
{
    /** @param array<string, string> $datos */
    public function registrar(string $evento, array $datos): void;
}
