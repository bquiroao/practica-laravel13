<?php

declare(strict_types=1);

namespace E2e\Persistencia\EnMemoria;

use E2e\Aplicacion\Puertos\AlmacenEvidencias;
use E2e\Aplicacion\Puertos\AlmacenNoDisponible;
use E2e\Dominio\Modelo\Evidencia;

final class AlmacenEvidenciasEnMemoria implements AlmacenEvidencias
{
    /** @var array<string, string> sha256 => bytes */
    public array $contenido = [];
    public bool $caido = false;

    public function guardar(string $tipo, string $bytes): Evidencia
    {
        if ($this->caido) {
            throw new AlmacenNoDisponible('almacén de artefactos caído');
        }
        $sha = hash('sha256', $bytes);
        $this->contenido[$sha] = $bytes;

        return new Evidencia($tipo, $sha, strlen($bytes), "memoria://$sha");
    }

    public function confirmada(string $sha256): bool
    {
        return isset($this->contenido[$sha256]) && hash('sha256', $this->contenido[$sha256]) === $sha256;
    }
}
