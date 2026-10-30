<?php

declare(strict_types=1);

namespace Qa\Persistencia\Archivos;

use Qa\Aplicacion\Puertos\AlmacenEvidencias;
use Qa\Aplicacion\Puertos\AlmacenNoDisponible;
use Qa\Dominio\Evidencia\EvidenciaVerificable;

/**
 * Almacén de evidencias en disco, direccionado por contenido: <raíz>/<tenant>/<huella>. En el HIS sería un almacén de
 * objetos; el puerto es el mismo. Guardar el mismo contenido dos veces no duplica nada. Confirma releyendo lo escrito.
 */
final class AlmacenEvidenciasArchivos implements AlmacenEvidencias
{
    public function __construct(private readonly string $raiz)
    {
    }

    public function guardar(string $tenant, EvidenciaVerificable $evidencia): string
    {
        if (!preg_match('/^[a-z0-9-]{1,40}$/', $tenant)) {
            throw new AlmacenNoDisponible('tenant no válido para una ruta');
        }
        $ubicacion = $tenant . '/' . $evidencia->huella();
        $archivo = $this->raiz . '/' . $ubicacion;
        if (!is_dir(dirname($archivo)) && !@mkdir(dirname($archivo), 0770, true) && !is_dir(dirname($archivo))) {
            throw new AlmacenNoDisponible('no se pudo crear la carpeta del tenant');
        }
        if (!is_file($archivo) && @file_put_contents($archivo, $evidencia->contenido(), LOCK_EX) !== $evidencia->tamano()) {
            throw new AlmacenNoDisponible('no se pudo escribir la evidencia');
        }
        if (hash_file('sha256', $archivo) !== $evidencia->huella()) {
            throw new AlmacenNoDisponible('la evidencia guardada no coincide con su huella');
        }

        return $ubicacion;
    }

    public function leer(string $ubicacion): string
    {
        if (!preg_match('#^[a-z0-9-]{1,40}/[0-9a-f]{64}$#', $ubicacion)) {
            throw new AlmacenNoDisponible('ubicación no válida');
        }
        $contenido = @file_get_contents($this->raiz . '/' . $ubicacion);
        if ($contenido === false) {
            throw new AlmacenNoDisponible('evidencia no encontrada');
        }

        return $contenido;
    }
}
