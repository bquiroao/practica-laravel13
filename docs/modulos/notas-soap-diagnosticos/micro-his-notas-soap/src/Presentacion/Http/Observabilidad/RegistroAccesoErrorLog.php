<?php

declare(strict_types=1);

namespace NotasSoap\Presentacion\Http\Observabilidad;

/** Escribe cada línea como JSON en el log de PHP (con php -S aparece en la consola del servidor). */
final class RegistroAccesoErrorLog implements RegistroAcceso
{
    public function registrar(array $linea): void
    {
        error_log((string) json_encode(['tipo' => 'acceso'] + $linea, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
