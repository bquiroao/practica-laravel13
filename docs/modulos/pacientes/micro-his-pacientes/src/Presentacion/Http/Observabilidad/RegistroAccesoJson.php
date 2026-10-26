<?php

declare(strict_types=1);

namespace Pacientes\Presentacion\Http\Observabilidad;

/** JSON Lines hacia un flujo (por defecto stderr, que el servidor web envía a su log). */
final class RegistroAccesoJson implements RegistroAcceso
{
    public function __construct(private readonly string $destino = 'php://stderr')
    {
    }

    public function registrar(array $linea): void
    {
        $flujo = fopen($this->destino, 'ab');
        if ($flujo === false) {
            return;
        }
        fwrite($flujo, json_encode(['tipo' => 'acceso'] + $linea, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL);
        fclose($flujo);
    }
}
