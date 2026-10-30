<?php

declare(strict_types=1);

namespace Qa\Presentacion\Http\Observabilidad;

/** Escribe cada petición como una línea JSON (por defecto en stderr, que el servidor recoge). */
final class RegistroAccesoJson implements RegistroAcceso
{
    /** @param resource|null $destino */
    public function __construct(private $destino = null)
    {
    }

    public function registrar(array $linea): void
    {
        $destino = $this->destino ?? fopen('php://stderr', 'wb');
        fwrite($destino, json_encode(['ts' => gmdate('Y-m-d\TH:i:s\Z')] + $linea, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL);
    }
}
