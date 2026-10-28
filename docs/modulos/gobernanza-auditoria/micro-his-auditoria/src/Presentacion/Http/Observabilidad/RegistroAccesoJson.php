<?php

declare(strict_types=1);

namespace Auditoria\Presentacion\Http\Observabilidad;

/** Una línea JSON por petición en stderr (la recoge el agregador de logs del HIS). */
final class RegistroAccesoJson implements RegistroAcceso
{
    public function __construct(private readonly string $destino = 'php://stderr')
    {
    }

    public function registrar(array $linea): void
    {
        $flujo = fopen($this->destino, 'a');
        if ($flujo === false) {
            return;
        }
        fwrite($flujo, json_encode(['tipo' => 'acceso', 'servicio' => 'auditoria'] + $linea, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL);
        fclose($flujo);
    }
}
