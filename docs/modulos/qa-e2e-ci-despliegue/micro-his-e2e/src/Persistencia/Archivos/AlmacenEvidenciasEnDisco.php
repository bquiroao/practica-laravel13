<?php

declare(strict_types=1);

namespace E2e\Persistencia\Archivos;

use E2e\Aplicacion\Puertos\AlmacenEvidencias;
use E2e\Aplicacion\Puertos\AlmacenNoDisponible;
use E2e\Dominio\Modelo\Evidencia;

/** Almacén en disco direccionado por contenido: <dir>/<sha[0..1]>/<sha>. Escribe, relee y verifica antes de confirmar. */
final class AlmacenEvidenciasEnDisco implements AlmacenEvidencias
{
    public function __construct(private readonly string $directorio)
    {
    }

    public function guardar(string $tipo, string $bytes): Evidencia
    {
        $sha = hash('sha256', $bytes);
        $archivo = $this->ruta($sha);
        if (!is_dir(dirname($archivo)) && !@mkdir(dirname($archivo), 0770, true) && !is_dir(dirname($archivo))) {
            throw new AlmacenNoDisponible('no se pudo crear el directorio del almacén');
        }
        if (!is_file($archivo) && @file_put_contents($archivo, $bytes, LOCK_EX) !== strlen($bytes)) {
            throw new AlmacenNoDisponible('no se pudo escribir el artefacto');
        }
        if (!$this->confirmada($sha)) {
            throw new AlmacenNoDisponible('el artefacto guardado no coincide con su huella');
        }

        return new Evidencia($tipo, $sha, strlen($bytes), 'disco://' . substr($sha, 0, 2) . "/$sha");
    }

    public function confirmada(string $sha256): bool
    {
        return preg_match('/^[0-9a-f]{64}$/', $sha256) === 1 && is_file($this->ruta($sha256)) && hash_file('sha256', $this->ruta($sha256)) === $sha256;
    }

    private function ruta(string $sha): string
    {
        return $this->directorio . '/' . substr($sha, 0, 2) . '/' . $sha;
    }
}
