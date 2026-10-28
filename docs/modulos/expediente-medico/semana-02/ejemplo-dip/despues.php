<?php
declare(strict_types=1);

// DESPUÉS (DIP): la política define sus abstracciones; HOSPITAL, CENTRAL, auditoría y reloj son detalles inyectados.

/** Base HOSPITAL: pacientes, asignaciones de atención y expedientes. */
interface ExpedientesHospital
{
    public function paciente(string $id): ?array;               // ['estado' => ..., 'vinculado' => bool]
    public function asignacionActiva(string $paciente, string $profesional): bool;
    public function tieneExpediente(string $paciente): bool;
    public function abrirExpediente(string $paciente): string;   // número de expediente
}
/** Base CENTRAL: consentimientos y accesos de emergencia. */
interface IndiceCentral
{
    public function consentimientoActivo(string $paciente, string $hospital, int $ahora): bool;
    public function crearAccesoEmergencia(string $paciente, string $profesional, string $justificacion, int $desde, int $hasta): void;
}
interface Auditoria { public function registrar(string $evento): void; }
interface Reloj { public function ahora(): int; }

/** Política de alto nivel (CU-01 a CU-04, R-01, R-03, R-05–R-08, R-10). No conoce ningún detalle. */
final class ServicioExpediente
{
    public function __construct(private ExpedientesHospital $hospital, private IndiceCentral $central,
                                private Auditoria $auditoria, private Reloj $reloj, private string $hospitalId) {}

    public function abrir(string $paciente): array
    {
        $p = $this->hospital->paciente($paciente);
        if ($p === null || $p['estado'] !== 'active') return $this->fin(422, "apertura rechazada $paciente");
        if ($this->hospital->tieneExpediente($paciente)) return $this->fin(409, "apertura repetida $paciente");
        return $this->fin(201, "abierto $paciente " . $this->hospital->abrirExpediente($paciente));
    }

    public function consultar(string $paciente, string $profesional): array
    {
        if (!$this->hospital->asignacionActiva($paciente, $profesional)) return $this->fin(403, "denegado $profesional->$paciente");
        return $this->fin(200, "local $profesional->$paciente");
    }

    public function historiaExterna(string $paciente, string $profesional): array
    {
        if (!$this->hospital->asignacionActiva($paciente, $profesional)) return $this->fin(403, "denegado $profesional->$paciente");
        if (!($this->hospital->paciente($paciente)['vinculado'] ?? false)) return $this->fin(200, "solo local $paciente") + ['solo_local' => true];
        if (!$this->central->consentimientoActivo($paciente, $this->hospitalId, $this->reloj->ahora())) return $this->fin(403, "sin consentimiento $paciente");
        return $this->fin(200, "externo $profesional->$paciente");
    }

    public function emergencia(string $paciente, string $profesional, string $justificacion): array
    {
        if (!$this->hospital->asignacionActiva($paciente, $profesional)) return $this->fin(403, "denegado $profesional->$paciente");
        if (trim($justificacion) === '') return $this->fin(422, "emergencia sin justificación $paciente");
        $desde = $this->reloj->ahora();
        $this->central->crearAccesoEmergencia($paciente, $profesional, $justificacion, $desde, $desde + 3600);
        return $this->fin(200, "emergencia $profesional->$paciente") + ['vence' => $desde + 3600];
    }

    private function fin(int $status, string $evento): array { $this->auditoria->registrar("$status $evento"); return ['status' => $status]; }
}

/** Detalle 1: base HOSPITAL con PDO y sentencias preparadas (SQLite en pruebas). */
final class ExpedientesPdo implements ExpedientesHospital
{
    public function __construct(private PDO $db)
    {
        $db->exec('CREATE TABLE IF NOT EXISTS pacientes (id TEXT PRIMARY KEY, estado TEXT, vinculado INT);
                   CREATE TABLE IF NOT EXISTS asignaciones (paciente TEXT, profesional TEXT, estado TEXT);
                   CREATE TABLE IF NOT EXISTS expedientes (numero INTEGER PRIMARY KEY, paciente TEXT UNIQUE)');
    }
    /** Ejecuta una sentencia preparada y devuelve la primera fila (o false). */
    private function fila(string $sql, array $params): array|false { $q = $this->db->prepare($sql); $q->execute($params); return $q->fetch(PDO::FETCH_ASSOC); }
    public function paciente(string $id): ?array
    {
        $r = $this->fila('SELECT estado, vinculado FROM pacientes WHERE id = ?', [$id]);
        return $r ? ['estado' => $r['estado'], 'vinculado' => (bool) $r['vinculado']] : null;
    }
    public function asignacionActiva(string $paciente, string $profesional): bool { return (bool) $this->fila("SELECT 1 FROM asignaciones WHERE paciente = ? AND profesional = ? AND estado = 'active'", [$paciente, $profesional]); }
    public function tieneExpediente(string $paciente): bool { return (bool) $this->fila('SELECT 1 FROM expedientes WHERE paciente = ?', [$paciente]); }
    public function abrirExpediente(string $paciente): string
    {
        $this->db->prepare('INSERT INTO expedientes (paciente) VALUES (?)')->execute([$paciente]);
        return 'EXP-' . $this->db->lastInsertId();
    }
}

/** Detalle 2: base HOSPITAL en memoria. */
final class ExpedientesEnMemoria implements ExpedientesHospital
{
    private array $expedientes = [];
    public function __construct(private array $pacientes, private array $asignaciones) {}
    public function paciente(string $id): ?array { return $this->pacientes[$id] ?? null; }
    public function asignacionActiva(string $paciente, string $profesional): bool { return in_array("$profesional->$paciente", $this->asignaciones, true); }
    public function tieneExpediente(string $paciente): bool { return in_array($paciente, $this->expedientes, true); }
    public function abrirExpediente(string $paciente): string { $this->expedientes[] = $paciente; return 'EXP-' . count($this->expedientes); }
}

final class IndiceCentralEnMemoria implements IndiceCentral
{
    public array $accesos = [];
    public function __construct(private array $consentimientos) {}   // ["paciente@hospital" => vence]
    public function consentimientoActivo(string $paciente, string $hospital, int $ahora): bool { return ($this->consentimientos["$paciente@$hospital"] ?? 0) > $ahora; }
    public function crearAccesoEmergencia(string $paciente, string $profesional, string $justificacion, int $desde, int $hasta): void { $this->accesos[] = compact('paciente', 'profesional', 'desde', 'hasta') + ['revision' => 'pending']; }
}
final class AuditoriaEnMemoria implements Auditoria { public array $eventos = []; public function registrar(string $e): void { $this->eventos[] = $e; } }
final class RelojFijo implements Reloj { public function __construct(public int $t) {} public function ahora(): int { return $this->t; } }
