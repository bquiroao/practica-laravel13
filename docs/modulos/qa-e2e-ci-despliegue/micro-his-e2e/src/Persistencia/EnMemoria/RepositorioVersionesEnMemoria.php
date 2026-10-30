<?php

declare(strict_types=1);

namespace E2e\Persistencia\EnMemoria;

use E2e\Dominio\Modelo\Version;
use E2e\Dominio\Puertos\RepositorioVersiones;

/** Guarda copias serializadas: quien modifica la instancia que leyó no cambia lo guardado hasta llamar a actualizar(). */
final class RepositorioVersionesEnMemoria implements RepositorioVersiones
{
    /** @var array<string, string> */
    private array $filas = [];

    public function crear(Version $version): void
    {
        $this->filas[$version->tenant . '|' . $version->numero] = serialize($version);
    }

    public function actualizar(Version $version): void
    {
        $this->crear($version);
    }

    public function porNumero(string $tenant, string $numero): ?Version
    {
        return isset($this->filas["$tenant|$numero"]) ? unserialize($this->filas["$tenant|$numero"]) : null;
    }

    public function porDigest(string $tenant, string $digest): ?Version
    {
        foreach ($this->filas as $fila) {
            $v = unserialize($fila);
            if ($v->tenant === $tenant && $v->digest === $digest) {
                return $v;
            }
        }

        return null;
    }

    public function instantanea(): mixed
    {
        return $this->filas;
    }

    public function restaurar(mixed $estado): void
    {
        $this->filas = $estado;
    }
}
