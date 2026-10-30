<?php
declare(strict_types=1);

namespace MicroHis\Expediente\Persistencia;

use MicroHis\Expediente\Aplicacion\{IndiceCentral, RepositorioExpedientes};
use MicroHis\Expediente\Dominio\Paciente;

/** Adaptador en memoria de la interfaz Repository de HOSPITAL: responde lo mismo que HospitalPdo, sin base. Para pruebas y demos.
 *  $pacientes: código => [id global, estado, vínculo MPI]; $asignaciones: [paciente, profesional] activas; $eventos: [profesional, operación, paciente, resultado]. */
final class HospitalEnMemoria implements RepositorioExpedientes
{
    private array $expedientes = [];   // código de paciente => número (medical_records)
    public array $eventos = [];        // audit_events

    public function __construct(private array $pacientes = [], private array $asignaciones = []) {}

    public function paciente(string $codigo): ?Paciente
    {
        if (!isset($this->pacientes[$codigo])) return null;
        [$idGlobal, $estado, $vinculo] = $this->pacientes[$codigo];
        return new Paciente($codigo, $idGlobal, $estado, $vinculo === 'linked', $this->expedientes[$codigo] ?? null);
    }
    public function asignacionActiva(string $p, string $m): bool { return in_array([$p, $m], $this->asignaciones, true); }
    public function abrirExpediente(string $paciente, string $numero): void { $this->expedientes[$paciente] = $numero; }
    public function registrar(string $m, string $op, string $p, string $res): void { $this->eventos[] = [$m, $op, $p, $res]; }
}

/** Adaptador en memoria del índice CENTRAL. $consentimientos: "idGlobal|hospital" => ['desde', 'hasta'] (solo activos); $indice: [id global, hospital, expediente]. */
final class CentralEnMemoria implements IndiceCentral
{
    public function __construct(private array $consentimientos = [], private array $indice = []) {}

    public function consentimiento(string $idGlobal, string $hospital): ?array { return $this->consentimientos["$idGlobal|$hospital"] ?? null; }
    public function expedientesExternos(string $idGlobal, string $hospital): array
    {
        $externos = array_filter($this->indice, fn (array $f) => $f[0] === $idGlobal && $f[1] !== $hospital);
        usort($externos, fn (array $a, array $b) => strcmp($a[1], $b[1]));   // el mismo orden que el ORDER BY de CentralPdo
        return array_map(fn (array $f) => ['hospital' => $f[1], 'expediente' => $f[2]], $externos);
    }
}
