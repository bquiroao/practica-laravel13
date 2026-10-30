<?php
declare(strict_types=1);

// ANTES: una sola interfaz "gorda" que todos los clientes reciben.
// Mezcla lectura, escritura, control de uso y hasta datos de otros módulos (precio, existencias).

namespace IspAntes;

interface RepositorioCatalogo
{
    public function buscarVigentes(string $hospitalId, string $texto, string $fecha): array;
    public function vigentePorCodigo(string $hospitalId, string $codigo, string $fecha): ?array;
    public function historial(string $hospitalId, string $codigo): array;
    public function equivalenteVigente(string $hospitalId, string $clave, string $desde): ?string;
    public function siguienteCodigo(string $hospitalId): string;
    public function agregar(array $medicamento): void;
    public function reemplazarVigente(array $anteriorCerrada, array $nueva): void;
    public function cerrar(array $cerrada): void;
    public function eliminar(string $hospitalId, string $codigo): void;
    public function fueUsadoEnPrescripciones(string $hospitalId, string $codigo): bool;
    public function actualizarPrecio(string $hospitalId, string $codigo, float $precio): void;
    public function existencias(string $hospitalId, string $codigo): int;
}

final class BuscarMedicamentosVigentes
{
    public function __construct(private RepositorioCatalogo $repo) {}

    public function ejecutar(string $hospitalId, string $texto, string $fecha): array
    {
        return $this->repo->buscarVigentes($hospitalId, $texto, $fecha);
    }
}

final class ConsultarHistorial
{
    public function __construct(private RepositorioCatalogo $repo) {}

    public function ejecutar(string $hospitalId, string $codigo): array
    {
        return $this->repo->historial($hospitalId, $codigo);
    }
}

final class RegistrarMedicamento
{
    public function __construct(private RepositorioCatalogo $repo) {}

    public function ejecutar(string $hospitalId, array $datos): string
    {
        $existente = $this->repo->equivalenteVigente($hospitalId, $datos['clave'], $datos['desde']);
        if ($existente !== null) {
            throw new \DomainException("DUPLICADO: $existente");
        }
        $codigo = $this->repo->siguienteCodigo($hospitalId);
        $this->repo->agregar($datos + ['codigo' => $codigo, 'version' => 1]);
        return $codigo;
    }
}

final class EliminarRegistroSinUso
{
    public function __construct(private RepositorioCatalogo $repo) {}

    public function ejecutar(string $hospitalId, string $codigo): void
    {
        if ($this->repo->fueUsadoEnPrescripciones($hospitalId, $codigo)) {
            throw new \DomainException('EN_USO');
        }
        $this->repo->eliminar($hospitalId, $codigo);
    }
}
