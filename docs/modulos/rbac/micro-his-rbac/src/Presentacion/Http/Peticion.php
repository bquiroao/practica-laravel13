<?php

declare(strict_types=1);

namespace Rbac\Presentacion\Http;

/** Lo que recibe un controlador: la solicitud, quién la hace y los parámetros de la ruta. */
final class Peticion
{
    /** @param array<string, string> $parametros */
    public function __construct(
        public readonly Solicitud $solicitud,
        public readonly Identidad $identidad,
        public readonly array $parametros,
    ) {
    }

    public function tenant(): string
    {
        return $this->solicitud->cabecera('X-Tenant-ID');
    }

    public function ip(): string
    {
        return $this->solicitud->ip;
    }

    public function parametro(string $nombre): string
    {
        return $this->parametros[$nombre] ?? '';
    }

    /** @return array<string, mixed> */
    public function cuerpo(): array
    {
        return $this->solicitud->cuerpo ?? [];
    }
}
