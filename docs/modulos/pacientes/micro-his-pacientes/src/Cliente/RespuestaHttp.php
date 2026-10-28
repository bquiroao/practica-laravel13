<?php

declare(strict_types=1);

namespace Pacientes\Cliente;

final class RespuestaHttp
{
    /** @var array<string, string> nombres en minúsculas */
    public readonly array $cabeceras;

    /**
     * @param array<string, string> $cabeceras
     * @param array<string, mixed> $cuerpo
     */
    public function __construct(public readonly int $estado, array $cabeceras = [], public readonly array $cuerpo = [])
    {
        $this->cabeceras = array_change_key_case($cabeceras, CASE_LOWER);
    }

    public function cabecera(string $nombre): string
    {
        return $this->cabeceras[strtolower($nombre)] ?? '';
    }
}
