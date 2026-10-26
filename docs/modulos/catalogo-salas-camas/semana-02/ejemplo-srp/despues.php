<?php
declare(strict_types=1);

// DESPUÉS: cada clase tiene una sola razón para cambiar (SRP).

/** Reglas de estado de la cama (R-05, R-06). Cambia solo si cambian las reglas operativas. */
final class PoliticaEstadosCama
{
    private const PERMITIDAS = ['available' => ['maintenance', 'inactive'], 'maintenance' => ['available', 'inactive'], 'inactive' => ['available']];
    public function rechazo(string $actual, string $nuevo): ?string
    {
        if ($actual === 'occupied') return 'E5';
        return in_array($nuevo, self::PERMITIDAS[$actual], true) ? null : 'E6';
    }
}

/** Almacenamiento del catálogo. Cambia solo si cambia la base de datos. */
interface RepositorioCatalogo
{
    public function existeSala(string $sala): bool;
    public function guardarSala(string $sala): void;
    public function estadoCama(string $sala, string $cama): ?string;
    public function guardarCama(string $sala, string $cama, string $estado): void;
}
final class RepositorioEnMemoria implements RepositorioCatalogo
{
    private array $salas = [];
    private array $camas = [];
    public function existeSala(string $sala): bool { return isset($this->salas[$sala]); }
    public function guardarSala(string $sala): void { $this->salas[$sala] = true; }
    public function estadoCama(string $sala, string $cama): ?string { return $this->camas["$sala/$cama"] ?? null; }
    public function guardarCama(string $sala, string $cama, string $estado): void { $this->camas["$sala/$cama"] = $estado; }
}

/** Evidencia de cambios (R-09). Cambia solo si cambia el formato de auditoría. */
interface Auditoria { public function registrar(string $evento): void; }
final class AuditoriaEnMemoria implements Auditoria
{
    public array $eventos = [];
    public function registrar(string $evento): void { $this->eventos[] = $evento; }
}

/** Traducción de resultados a HTTP. Cambia solo si cambia el contrato de la API. */
final class RespuestaHttp
{
    private const CODIGOS = ['E2' => 422, 'E3' => 409, 'E4' => 422, 'E5' => 409, 'E6' => 422, 'NF' => 404];
    public function de(?string $error, int $exito): array
    {
        return ['status' => $error === null ? $exito : self::CODIGOS[$error], 'error' => $error];
    }
}

/** Flujo de CU-01 a CU-03. Cambia solo si cambia el caso de uso. Devuelve null o un código E. */
final class ServicioCatalogo
{
    public function __construct(private RepositorioCatalogo $repo, private PoliticaEstadosCama $politica, private Auditoria $auditoria) {}

    public function registrarSala(string $sala): ?string
    {
        if (trim($sala) === '') return 'E2';
        if ($this->repo->existeSala($sala)) return 'E3';
        $this->repo->guardarSala($sala);
        $this->auditoria->registrar("alta sala $sala");
        return null;
    }

    public function registrarCama(string $sala, string $cama): ?string
    {
        if (!$this->repo->existeSala($sala)) return 'E4';
        if (trim($cama) === '') return 'E2';
        if ($this->repo->estadoCama($sala, $cama) !== null) return 'E3';
        $this->repo->guardarCama($sala, $cama, 'available');
        $this->auditoria->registrar("alta cama $sala/$cama");
        return null;
    }

    public function cambiarEstado(string $sala, string $cama, string $nuevo, string $motivo): ?string
    {
        $actual = $this->repo->estadoCama($sala, $cama);
        if ($actual === null) return 'NF';
        if (trim($motivo) === '') return 'E2';
        $rechazo = $this->politica->rechazo($actual, $nuevo);
        if ($rechazo !== null) return $rechazo;
        $this->repo->guardarCama($sala, $cama, $nuevo);
        $this->auditoria->registrar("estado $sala/$cama $actual->$nuevo: $motivo");
        return null;
    }
}
