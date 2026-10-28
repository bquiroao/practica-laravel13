<?php
declare(strict_types=1);

namespace MicroHis\Expediente\Aplicacion;

use MicroHis\Expediente\Dominio\{Consentimiento, Paciente, ReglaDeDominio};

// Puertos (DIP de la semana 2): la política los define; HOSPITAL, CENTRAL, auditoría y reloj son detalles inyectados.
interface ExpedientesHospital
{
    public function paciente(string $codigo): ?Paciente;
    public function asignacionActiva(string $paciente, string $profesional): bool;
    public function abrirExpediente(string $paciente, string $numero): void;
}
interface IndiceCentral
{
    public function consentimiento(string $idGlobal, string $hospital): ?array;   // ['desde' => int, 'hasta' => ?int] o null
    public function expedientesExternos(string $idGlobal, string $hospital): array;   // [['hospital', 'expediente'], ...] de otros hospitales
}
interface Auditoria { public function registrar(string $profesional, string $operacion, string $paciente, string $resultado): void; }
interface Reloj { public function ahora(): int; }

final class ErrorPersistencia extends \RuntimeException {}   // fallo técnico de una base: no es una regla de negocio

/** CU-01 a CU-03 de la semana 1. Toda operación, aceptada o rechazada, queda en la auditoría (R-08). */
final class ServicioExpediente
{
    public function __construct(private ExpedientesHospital $hospital, private IndiceCentral $central,
                                private Auditoria $auditoria, private Reloj $reloj, private string $hospitalId) {}
    public function abrir(string $paciente, string $profesional): string
    {
        return $this->auditado($profesional, 'abrir', $paciente, function () use ($paciente): string {
            $numero = $this->buscar($paciente)->numeroParaAbrir();
            $this->hospital->abrirExpediente($paciente, $numero);
            return $numero;
        });
    }

    /** CU-02 y CU-03: con asignación activa (R-05); la historia de otros hospitales exige vínculo MPI y consentimiento vigente (R-06, R-10). */
    public function consultar(string $paciente, string $profesional, bool $longitudinal): array
    {
        return $this->auditado($profesional, $longitudinal ? 'consultar_longitudinal' : 'consultar', $paciente, function () use ($paciente, $profesional, $longitudinal): array {
            if (!$this->hospital->asignacionActiva($paciente, $profesional)) throw new ReglaDeDominio('E4', 'No tienes una asignación de atención activa con este paciente.');
            $p = $this->buscar($paciente);
            $r = ['expediente' => $p->expediente ?? throw new ReglaDeDominio('NF', 'El paciente aún no tiene expediente.')];
            if (!$longitudinal) return $r;
            if (!$p->vinculadoMpi) return $r + ['solo_local' => true];
            $consentimiento = $this->central->consentimiento($p->idGlobal, $this->hospitalId);
            if (!Consentimiento::vigente($consentimiento, $this->reloj->ahora())) throw new ReglaDeDominio('E5', 'No hay consentimiento vigente para ver otros hospitales.');
            return $r + ['externos' => $this->central->expedientesExternos($p->idGlobal, $this->hospitalId)];
        });
    }

    private function buscar(string $paciente): Paciente { return $this->hospital->paciente($paciente) ?? throw new ReglaDeDominio('E2', 'El paciente no existe.'); }
    private function auditado(string $profesional, string $operacion, string $paciente, callable $caso): mixed
    {
        try { $r = $caso(); $this->auditoria->registrar($profesional, $operacion, $paciente, 'ok'); return $r; }
        catch (ReglaDeDominio $e) { $this->auditoria->registrar($profesional, $operacion, $paciente, $e->codigo); throw $e; }
    }
}
