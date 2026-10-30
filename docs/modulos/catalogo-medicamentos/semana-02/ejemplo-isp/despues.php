<?php
declare(strict_types=1);

// DESPUÉS: interfaces de rol pequeñas. Cada cliente depende solo de lo que usa.
// Precio y existencias salen del catálogo: pertenecen a farmacia/inventario.

namespace IspDespues;

interface BusquedaCatalogo
{
    public function buscarVigentes(string $hospitalId, string $texto, string $fecha): array;
    public function vigentePorCodigo(string $hospitalId, string $codigo, string $fecha): ?array;
}

interface HistorialCatalogo
{
    public function historial(string $hospitalId, string $codigo): array;
}

interface ControlEquivalencia
{
    public function equivalenteVigente(string $hospitalId, string $clave, string $desde): ?string;
}

interface EscrituraCatalogo
{
    public function siguienteCodigo(string $hospitalId): string;
    public function agregar(array $medicamento): void;
    public function reemplazarVigente(array $anteriorCerrada, array $nueva): void;
    public function cerrar(array $cerrada): void;
    public function eliminar(string $hospitalId, string $codigo): void;
}

// Puerto hacia prescripciones-alergias: lo implementa un adaptador, no el repositorio del catálogo.
interface ConsultaUsoPrescripciones
{
    public function fueUsado(string $hospitalId, string $codigo): bool;
}

// El repositorio concreto (PDO o InMemory) implementa las cuatro del catálogo.
interface RepositorioMedicamentos extends BusquedaCatalogo, HistorialCatalogo, ControlEquivalencia, EscrituraCatalogo
{
}

final class BuscarMedicamentosVigentes
{
    public function __construct(private BusquedaCatalogo $busqueda) {}

    public function ejecutar(string $hospitalId, string $texto, string $fecha): array
    {
        return $this->busqueda->buscarVigentes($hospitalId, $texto, $fecha);
    }
}

final class ConsultarHistorial
{
    public function __construct(private HistorialCatalogo $historial) {}

    public function ejecutar(string $hospitalId, string $codigo): array
    {
        return $this->historial->historial($hospitalId, $codigo);
    }
}

final class RegistrarMedicamento
{
    public function __construct(private ControlEquivalencia $equivalencia, private EscrituraCatalogo $escritura) {}

    public function ejecutar(string $hospitalId, array $datos): string
    {
        $existente = $this->equivalencia->equivalenteVigente($hospitalId, $datos['clave'], $datos['desde']);
        if ($existente !== null) {
            throw new \DomainException("DUPLICADO: $existente");
        }
        $codigo = $this->escritura->siguienteCodigo($hospitalId);
        $this->escritura->agregar($datos + ['codigo' => $codigo, 'version' => 1]);
        return $codigo;
    }
}

final class EliminarRegistroSinUso
{
    public function __construct(private ConsultaUsoPrescripciones $uso, private EscrituraCatalogo $escritura) {}

    public function ejecutar(string $hospitalId, string $codigo): void
    {
        if ($this->uso->fueUsado($hospitalId, $codigo)) {
            throw new \DomainException('EN_USO');
        }
        $this->escritura->eliminar($hospitalId, $codigo);
    }
}
