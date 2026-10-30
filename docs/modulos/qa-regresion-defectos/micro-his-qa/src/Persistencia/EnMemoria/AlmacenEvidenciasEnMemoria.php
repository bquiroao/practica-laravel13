<?php

declare(strict_types=1);

namespace Qa\Persistencia\EnMemoria;

use Qa\Aplicacion\Puertos\AlmacenEvidencias;
use Qa\Aplicacion\Puertos\AlmacenNoDisponible;
use Qa\Dominio\Evidencia\EvidenciaVerificable;

/**
 * Almacén de evidencias en memoria, direccionado por contenido como el de disco. No participa en la transacción
 * (un almacén externo tampoco lo hace): por eso RegistrarResultado lo llama ANTES de ella.
 */
final class AlmacenEvidenciasEnMemoria implements AlmacenEvidencias
{
    /** Simula el almacén caído (para demostrar el fail-closed sin desenchufar nada). */
    public bool $caido = false;
    /** @var array<string, string> ubicación => bytes */
    private array $archivos = [];

    public function guardar(string $tenant, EvidenciaVerificable $evidencia): string
    {
        if ($this->caido) {
            throw new AlmacenNoDisponible('almacén no disponible');
        }
        $ubicacion = $tenant . '/' . $evidencia->huella();
        $this->archivos[$ubicacion] = $evidencia->contenido();

        return $ubicacion;
    }

    public function leer(string $ubicacion): string
    {
        if ($this->caido || !isset($this->archivos[$ubicacion])) {
            throw new AlmacenNoDisponible('evidencia no disponible');
        }

        return $this->archivos[$ubicacion];
    }

    public function cantidad(): int
    {
        return count($this->archivos);
    }
}
