<?php
declare(strict_types=1);

// DESPUÉS (DIP): la política de agendamiento define las abstracciones que necesita;
// los detalles (base de datos, reloj, auditoría) las implementan y se inyectan.

/** Abstracciones del dominio: las define y las usa la política, no la base de datos. */
interface AgendaCitas
{
    /** Devuelve 'medico', 'paciente' o null si nadie tiene cita activa cruzada en [inicio, fin). */
    public function cruce(string $medico, string $paciente, int $inicio, int $fin): ?string;
    public function guardar(string $medico, string $paciente, int $inicio, int $fin): int;
    public function estado(int $id): ?string;
    public function cancelar(int $id): void;
}
interface Reloj { public function ahora(): int; }
interface Auditoria { public function registrar(string $evento): void; }

/** Política de alto nivel (CU-01, CU-03, R-01–R-03, R-07, R-08, R-10). No conoce ningún detalle. */
final class ServicioCitas
{
    public function __construct(private AgendaCitas $agenda, private Reloj $reloj, private Auditoria $auditoria) {}

    public function solicitar(string $medico, string $paciente, int $inicio, int $fin, string $motivo): ?string
    {
        if ($inicio <= $this->reloj->ahora() || $fin <= $inicio || trim($motivo) === '') return 'E2';
        $cruce = $this->agenda->cruce($medico, $paciente, $inicio, $fin);
        if ($cruce !== null) return $cruce === 'medico' ? 'E4' : 'E5';
        $this->agenda->guardar($medico, $paciente, $inicio, $fin);
        return null;
    }

    public function cancelar(int $id, string $motivo): ?string
    {
        $estado = $this->agenda->estado($id);
        if ($estado === null) return 'NF';
        if (trim($motivo) === '') return 'E2';
        if (!in_array($estado, ['scheduled', 'confirmed'], true)) return 'E7';
        $this->agenda->cancelar($id);
        $this->auditoria->registrar("cancelada $id: $motivo");
        return null;
    }
}

/** Detalle 1: agenda con PDO (sirve para SQLite en pruebas o PostgreSQL en producción). */
final class AgendaPdo implements AgendaCitas
{
    public function __construct(private PDO $db)
    {
        $db->exec('CREATE TABLE IF NOT EXISTS citas (id INTEGER PRIMARY KEY, medico TEXT, paciente TEXT, inicio INT, fin INT, estado TEXT)');
    }
    public function cruce(string $medico, string $paciente, int $inicio, int $fin): ?string
    {
        $q = $this->db->prepare("SELECT medico = ? AS es_medico FROM citas WHERE estado IN ('scheduled','confirmed') AND inicio < ? AND fin > ? AND (medico = ? OR paciente = ?) ORDER BY es_medico DESC");
        $q->execute([$medico, $fin, $inicio, $medico, $paciente]);
        $r = $q->fetchColumn();
        return $r === false ? null : ($r ? 'medico' : 'paciente');
    }
    public function guardar(string $medico, string $paciente, int $inicio, int $fin): int
    {
        $this->db->prepare("INSERT INTO citas (medico, paciente, inicio, fin, estado) VALUES (?, ?, ?, ?, 'scheduled')")->execute([$medico, $paciente, $inicio, $fin]);
        return (int) $this->db->lastInsertId();
    }
    public function estado(int $id): ?string
    {
        $q = $this->db->prepare('SELECT estado FROM citas WHERE id = ?');
        $q->execute([$id]);
        return $q->fetchColumn() ?: null;
    }
    public function cancelar(int $id): void { $this->db->prepare("UPDATE citas SET estado = 'cancelled' WHERE id = ?")->execute([$id]); }
}

/** Detalle 2: agenda en memoria, sin base de datos. */
final class AgendaEnMemoria implements AgendaCitas
{
    private array $citas = [];
    public function cruce(string $medico, string $paciente, int $inicio, int $fin): ?string
    {
        $cruce = null;
        foreach ($this->citas as $c) {
            if ($c['estado'] === 'cancelled' || $c['inicio'] >= $fin || $c['fin'] <= $inicio) continue;
            if ($c['medico'] === $medico) return 'medico';
            if ($c['paciente'] === $paciente) $cruce = 'paciente';
        }
        return $cruce;
    }
    public function guardar(string $medico, string $paciente, int $inicio, int $fin): int
    {
        $this->citas[] = compact('medico', 'paciente', 'inicio', 'fin') + ['estado' => 'scheduled'];
        return count($this->citas);
    }
    public function estado(int $id): ?string { return $this->citas[$id - 1]['estado'] ?? null; }
    public function cancelar(int $id): void { $this->citas[$id - 1]['estado'] = 'cancelled'; }
}

final class RelojFijo implements Reloj { public function __construct(private int $t) {} public function ahora(): int { return $this->t; } }
final class AuditoriaEnMemoria implements Auditoria { public array $eventos = []; public function registrar(string $e): void { $this->eventos[] = $e; } }
