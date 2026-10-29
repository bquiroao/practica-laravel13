<?php
declare(strict_types=1);

use Alergias\Dominio\Alergia;
use Alergias\Dominio\DirectorioPacientes;
use Alergias\Dominio\ErrorPersistencia;
use Alergias\Dominio\PacienteRef;
use Alergias\Dominio\Reloj;
use Alergias\Dominio\RepositorioAlergias;
use Alergias\Dominio\TipoAlergia;

/** Dobles de prueba de la semana 3 (sin base). */
final class PacientesFijos implements DirectorioPacientes
{
    public function porCodigo(string $codigo): ?PacienteRef
    {
        return [
            'PAC-00451' => new PacienteRef('p-451', 'PAC-00451', 'active'),
            'PAC-00452' => new PacienteRef('p-452', 'PAC-00452', 'active'),
            'PAC-00453' => new PacienteRef('p-453', 'PAC-00453', 'deceased'),
        ][$codigo] ?? null;
    }
}

final class AlergiasEnLista implements RepositorioAlergias
{
    /** @var list<Alergia> */
    public array $guardadas = [];

    public function existeActivaEquivalente(string $pacienteId, TipoAlergia $tipo, string $claveAlergeno): bool
    {
        foreach ($this->activasDePaciente($pacienteId) as $a) {
            if ($a->tipo === $tipo && $a->alergeno->clave() === $claveAlergeno) {
                return true;
            }
        }
        return false;
    }

    public function guardar(Alergia $alergia): void
    {
        $this->guardadas[] = $alergia;
    }

    public function activasDePaciente(string $pacienteId): array
    {
        return array_values(array_filter($this->guardadas, fn ($a) => $a->pacienteId === $pacienteId && $a->estado === 'active'));
    }
}

/** Simula la base caída al escribir. */
final class AlergiasQueFallan implements RepositorioAlergias
{
    public function existeActivaEquivalente(string $pacienteId, TipoAlergia $tipo, string $claveAlergeno): bool
    {
        return false;
    }

    public function guardar(Alergia $alergia): void
    {
        throw new ErrorPersistencia('conexión rechazada (doble)');
    }

    public function activasDePaciente(string $pacienteId): array
    {
        throw new ErrorPersistencia('conexión rechazada (doble)');
    }
}

final class RelojFijo implements Reloj
{
    public function hoy(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-10-28');
    }
}
