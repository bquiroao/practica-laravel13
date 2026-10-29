<?php

declare(strict_types=1);

namespace E2e\Adaptadores;

use E2e\Dominio\AlmacenEvidencias;
use E2e\Dominio\Decision;
use E2e\Dominio\Ejecucion;
use E2e\Dominio\FuenteResultados;
use E2e\Dominio\ReglaRechazada;
use E2e\Dominio\Reloj;
use E2e\Dominio\RepositorioEvaluaciones;

/** Almacén en disco direccionado por contenido: el archivo se llama como su SHA-256 y se verifica al confirmar. */
final class AlmacenEnDisco implements AlmacenEvidencias
{
    public function __construct(private readonly string $directorio)
    {
    }

    public function confirmada(string $sha256): bool
    {
        $archivo = $this->directorio . '/' . basename($sha256);

        return preg_match('/^[0-9a-f]{64}$/', $sha256) === 1 && is_file($archivo) && hash_file('sha256', $archivo) === $sha256;
    }
}

final class RelojSistema implements Reloj
{
    public function ahora(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }
}

final class RelojFijo implements Reloj
{
    public function __construct(private readonly \DateTimeImmutable $hora)
    {
    }

    public function ahora(): \DateTimeImmutable
    {
        return $this->hora;
    }
}

final class FuenteEnMemoria implements FuenteResultados
{
    /** @param array<string, Ejecucion> $ejecuciones */
    public function __construct(private array $ejecuciones = [])
    {
    }

    public function ejecucion(string $id): Ejecucion
    {
        return $this->ejecuciones[$id] ?? throw new ReglaRechazada('EJECUCION_INEXISTENTE');
    }
}

final class AlmacenEnMemoria implements AlmacenEvidencias
{
    /** @param list<string> $huellas */
    public function __construct(private readonly array $huellas = [])
    {
    }

    public function confirmada(string $sha256): bool
    {
        return in_array($sha256, $this->huellas, true);
    }
}

final class EvaluacionesEnMemoria implements RepositorioEvaluaciones
{
    /** @var list<Decision> */
    private array $todas = [];

    public function guardar(Decision $decision): void
    {
        $this->todas[] = $decision;
    }

    public function deEjecucion(string $ejecucion): array
    {
        return array_values(array_filter($this->todas, static fn (Decision $d): bool => $d->ejecucion === $ejecucion));
    }
}
