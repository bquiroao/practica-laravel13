<?php
declare(strict_types=1);

namespace Lab\Persistence;

use DateTimeImmutable;
use Lab\Domain\LabTest;
use Lab\Domain\LabTestRepository;

/** Adaptador en memoria: mismo contrato que el PDO, para pruebas y demo sin base. */
final class InMemoryLabTestRepository implements LabTestRepository
{
    /** @var array<string,LabTest> */
    private array $filas = [];

    public function ultimaVersion(string $tenantId, string $codigo): ?LabTest
    {
        $ultima = null;
        foreach ($this->filas as $p) {
            if ($p->tenantId === $tenantId && $p->codigo === $codigo && ($ultima === null || $p->version > $ultima->version)) {
                $ultima = $p;
            }
        }
        return $ultima;
    }

    public function guardarNuevaVersion(LabTest $nueva, ?LabTest $anteriorCerrada): void
    {
        if ($anteriorCerrada !== null) {
            $this->filas[$anteriorCerrada->id] = $anteriorCerrada;
        }
        $this->filas[$nueva->id] = $nueva;
    }

    public function buscarVigentes(string $tenantId, DateTimeImmutable $fecha, string $texto = '', ?string $seccion = null, ?string $tipoMuestra = null): array
    {
        $r = array_filter($this->filas, fn (LabTest $p) => $p->tenantId === $tenantId
            && $p->vigencia->incluye($fecha)
            && ($texto === '' || stripos($p->nombre, $texto) !== false || stripos($p->codigo, $texto) !== false)
            && ($seccion === null || $p->seccion === $seccion)
            && ($tipoMuestra === null || $p->requisito->tipoMuestra === $tipoMuestra));
        usort($r, fn (LabTest $a, LabTest $b) => strcmp($a->codigo, $b->codigo));
        return $r;
    }

    public function buscarVigentePorCodigo(string $tenantId, string $codigo, DateTimeImmutable $fecha): ?LabTest
    {
        foreach ($this->buscarVigentes($tenantId, $fecha) as $p) {
            if ($p->codigo === $codigo) {
                return $p;
            }
        }
        return null;
    }
}
