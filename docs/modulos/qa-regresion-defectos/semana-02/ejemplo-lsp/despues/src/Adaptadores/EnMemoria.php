<?php

declare(strict_types=1);

namespace Qa\Adaptadores;

use Qa\Aplicacion\AlmacenEvidencias;
use Qa\Aplicacion\AlmacenNoDisponible;
use Qa\Aplicacion\DescargadorRemoto;
use Qa\Aplicacion\PoliticaAcceso;
use Qa\Dominio\Evidencia\EvidenciaVerificable;

final class AlmacenEnMemoria implements AlmacenEvidencias
{
    /** @var array<string, string> ubicación => bytes */
    public array $archivos = [];
    public bool $caido = false;

    public function guardar(EvidenciaVerificable $evidencia): string
    {
        if ($this->caido) {
            throw new AlmacenNoDisponible('El almacén de evidencias no respondió.');
        }
        $ubicacion = 'mem://' . $evidencia->huella();
        $this->archivos[$ubicacion] = $evidencia->contenido();

        return $ubicacion;
    }

    public function leer(string $ubicacion): string
    {
        if ($this->caido || !isset($this->archivos[$ubicacion])) {
            throw new AlmacenNoDisponible('El almacén de evidencias no respondió.');
        }

        return $this->archivos[$ubicacion];
    }
}

final class PoliticaEnMemoria implements PoliticaAcceso
{
    /** @param array<string, list<string>> $permisos «usuario|tenant» => permisos */
    public function __construct(private readonly array $permisos)
    {
    }

    public function permite(string $usuario, string $permiso, string $tenant): bool
    {
        return in_array($permiso, $this->permisos["$usuario|$tenant"] ?? [], true);
    }
}

/** Un «pipeline» cuyo log se puede rotar (cambiar) durante la prueba. */
final class PipelineSimulado implements DescargadorRemoto
{
    /** @param array<string, string> $logs url => contenido actual */
    public function __construct(public array $logs)
    {
    }

    public function descargar(string $url): string
    {
        return $this->logs[$url] ?? throw new \RuntimeException("404 $url");
    }
}
