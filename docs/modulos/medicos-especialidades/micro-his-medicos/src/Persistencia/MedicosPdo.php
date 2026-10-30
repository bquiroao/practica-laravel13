<?php
declare(strict_types=1);

// Persistencia: SQL con sentencias preparadas. El perfil del médico y su especialidad principal se guardan en UNA transacción.
// Semana 4: implementa los dos puertos; en PostgreSQL los ids deben ser uuid (los genera public/index.php).
namespace Medicos\Persistencia;

use Medicos\Aplicacion\{ErrorPersistencia, FuenteHospital, MedicoNuevo, RepositorioMedicos};
use Medicos\Dominio\{Asignacion, Vigencia};

final class MedicosPdo implements RepositorioMedicos, FuenteHospital {
    public function __construct(private \PDO $pdo) { $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION); }

    public function usuarioActivo(string $id): bool { return $this->hay('SELECT 1 FROM local_user_refs WHERE local_user_ref_id = ? AND status = ?', [$id, 'active']); }
    public function colegiadoRegistrado(string $colegiado): bool { return $this->hay('SELECT 1 FROM doctor_profiles WHERE license_number = ?', [$colegiado]); }
    public function tienePerfilActivo(string $usuarioId): bool { return $this->hay('SELECT 1 FROM doctor_profiles WHERE local_user_ref_id = ? AND status = ? AND valid_to IS NULL', [$usuarioId, 'active']); }
    public function medicoActivo(string $id): bool { return $this->hay('SELECT 1 FROM doctor_profiles WHERE doctor_profile_id = ? AND status = ?', [$id, 'active']); }
    public function especialidadActiva(string $id): bool { return $this->hay('SELECT 1 FROM specialties WHERE specialty_id = ? AND status = ?', [$id, 'active']); }
    public function asignaciones(string $medicoId): array {
        return array_map(fn ($f) => new Asignacion($f['specialty_id'], new Vigencia($f['valid_from'], $f['valid_to']), (bool) $f['is_primary']),
            $this->filas('SELECT specialty_id, valid_from, valid_to, is_primary FROM doctor_specialties WHERE doctor_profile_id = ? AND status = ?', [$medicoId, 'active']));
    }
    // médico => [nombre (de local_user_refs), Vigencia[]]: médicos activos con esa especialidad activa
    public function medicosConEspecialidad(string $codigo): array {
        $m = [];
        foreach ($this->filas('SELECT p.doctor_profile_id AS id, u.display_name, ds.valid_from, ds.valid_to FROM doctor_profiles p
                JOIN local_user_refs u USING (local_user_ref_id) JOIN doctor_specialties ds ON ds.doctor_profile_id = p.doctor_profile_id AND ds.status = ?
                JOIN specialties s ON s.specialty_id = ds.specialty_id AND s.status = ? WHERE s.code = ? AND p.status = ? ORDER BY u.display_name', ['active', 'active', $codigo, 'active']) as $f) {
            $m[$f['id']] ??= [$f['display_name'], []];
            $m[$f['id']][1][] = new Vigencia($f['valid_from'], $f['valid_to']);
        }
        return $m;
    }
    public function horarios(array $ids): array {
        $h = [];
        foreach ($this->enLista('SELECT doctor_profile_id AS id, dia_semana, inicio, fin FROM horarios_medico WHERE doctor_profile_id IN (%s) ORDER BY inicio', $ids) as $f) { $h[$f['id']][(int) $f['dia_semana']][] = [$f['inicio'], $f['fin']]; }
        return $h;
    }
    // citas 'scheduled' o 'confirmed' del rango: las mismas que bloquea la exclusión GiST de appointments
    public function citasActivas(array $ids, string $desde, string $hasta): array {
        $c = [];
        foreach ($this->enLista("SELECT doctor_profile_id AS id, starts_at, ends_at FROM appointments WHERE doctor_profile_id IN (%s) AND status IN ('scheduled', 'confirmed')
                                 AND starts_at < ? AND ends_at > ?", $ids, [$hasta . ' 24:00', $desde]) as $f) { $c[$f['id']][] = [substr($f['starts_at'], 0, 16), substr($f['ends_at'], 0, 16)]; }
        return $c;
    }
    public function guardarMedico(MedicoNuevo $m): void {
        $this->enTransaccion(function () use ($m) {
            $this->pdo->prepare('INSERT INTO doctor_profiles (doctor_profile_id, local_user_ref_id, license_number, status, valid_from) VALUES (?, ?, ?, ?, ?)')
                ->execute([$m->id, $m->usuarioId, $m->colegiado, 'active', $m->vigencia->desde]);
            if ($m->principal !== null) { $this->insertarAsignacion($m->asignacionId, $m->id, $m->principal); }
        });
    }
    public function guardarAsignacion(string $id, string $medicoId, Asignacion $a): void { $this->enTransaccion(fn () => $this->insertarAsignacion($id, $medicoId, $a)); }
    private function insertarAsignacion(string $id, string $medicoId, Asignacion $a): void {
        $this->pdo->prepare('INSERT INTO doctor_specialties (doctor_specialty_id, doctor_profile_id, specialty_id, is_primary, valid_from, valid_to, status) VALUES (?, ?, ?, ?, ?, ?, ?)')
            ->execute([$id, $medicoId, $a->especialidadId, (int) $a->principal, $a->vigencia->desde, $a->vigencia->hasta, 'active']);
    }
    private function enTransaccion(callable $f): void {
        try { $this->pdo->beginTransaction(); $f(); $this->pdo->commit(); }
        catch (\PDOException $e) { if ($this->pdo->inTransaction()) { $this->pdo->rollBack(); } throw new ErrorPersistencia('No se pudo guardar', 0, $e); }
    }
    private function enLista(string $sql, array $ids, array $mas = []): array { return $ids === [] ? [] : $this->filas(sprintf($sql, implode(', ', array_fill(0, count($ids), '?'))), [...array_values($ids), ...$mas]); }
    private function hay(string $sql, array $p): bool { return $this->filas($sql, $p) !== []; }
    private function filas(string $sql, array $p): array {
        try { $c = $this->pdo->prepare($sql); $c->execute($p); return $c->fetchAll(\PDO::FETCH_ASSOC); }
        catch (\PDOException $e) { throw new ErrorPersistencia('No se pudo leer la base', 0, $e); }
    }
}
