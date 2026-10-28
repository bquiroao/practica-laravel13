<?php

declare(strict_types=1);

namespace Auditoria\Cliente;

/**
 * Bandeja local en un archivo JSON del emisor, con bloqueo exclusivo: sobrevive a que el proceso
 * del emisor se reinicie. Prototipo: en el HIS el equivalente es la tabla de salida del nodo del hospital.
 */
final class BandejaArchivo implements BandejaLocal
{
    public function __construct(private readonly string $ruta)
    {
    }

    public function guardar(string $eventId, array $movimiento): void
    {
        $this->modificar(static function (array $pendientes) use ($eventId, $movimiento): array {
            $pendientes[$eventId] = $movimiento;

            return $pendientes;
        });
    }

    public function pendientes(): array
    {
        $contenido = is_file($this->ruta) ? (string) file_get_contents($this->ruta) : '';
        $datos = $contenido === '' ? [] : json_decode($contenido, true);

        return is_array($datos) ? $datos : [];
    }

    public function quitar(string $eventId): void
    {
        $this->modificar(static function (array $pendientes) use ($eventId): array {
            unset($pendientes[$eventId]);

            return $pendientes;
        });
    }

    /** @param \Closure(array): array $cambio */
    private function modificar(\Closure $cambio): void
    {
        $archivo = fopen($this->ruta, 'c+');
        if ($archivo === false) {
            throw new \RuntimeException('No se pudo abrir la bandeja local.');
        }
        try {
            flock($archivo, LOCK_EX);
            $contenido = stream_get_contents($archivo);
            $pendientes = $contenido === '' || $contenido === false ? [] : (json_decode($contenido, true) ?: []);
            $nuevo = (string) json_encode($cambio($pendientes), JSON_UNESCAPED_UNICODE);
            ftruncate($archivo, 0);
            rewind($archivo);
            fwrite($archivo, $nuevo);
            fflush($archivo);
        } finally {
            flock($archivo, LOCK_UN);
            fclose($archivo);
        }
    }
}
