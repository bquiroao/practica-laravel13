<?php
declare(strict_types=1);

// Adaptador en memoria: mismo contrato que MedicosPdo, sin base. Sirve para pruebas rápidas y como doble que falla.
namespace Medicos\Persistencia;

use Medicos\Aplicacion\{ErrorPersistencia, FuenteHospital, MedicoNuevo, RepositorioMedicos};
use Medicos\Dominio\{Asignacion, Vigencia};

final class EnMemoria implements RepositorioMedicos, FuenteHospital {
    public bool $fallarAlGuardar = false;
    /** Filas con las mismas columnas que las tablas de sql/esquema.sql. */
    public function __construct(private array $usuarios = [], private array $especialidades = [], private array $perfiles = [],
                                private array $asignadas = [], private array $horariosMed = [], private array $citas = []) {}

    /** Copia los datos de una base para que memoria y PDO arranquen iguales. */
    public static function desdePdo(\PDO $pdo): self {
        $t = fn (string $tabla) => $pdo->query("SELECT * FROM $tabla")->fetchAll(\PDO::FETCH_ASSOC);
        return new self($t('local_user_refs'), $t('specialties'), $t('doctor_profiles'), $t('doctor_specialties'), $t('horarios_medico'), $t('appointments'));
    }

    private function donde(array $filas, callable $si): array { return array_values(array_filter($filas, $si)); }
    public function usuarioActivo(string $id): bool { return (bool) $this->donde($this->usuarios, fn ($u) => [$u['local_user_ref_id'], $u['status']] === [$id, 'active']); }
    public function especialidadActiva(string $id): bool { return (bool) $this->donde($this->especialidades, fn ($s) => [$s['specialty_id'], $s['status']] === [$id, 'active']); }
    public function colegiadoRegistrado(string $c): bool { return (bool) $this->donde($this->perfiles, fn ($p) => $p['license_number'] === $c); }
    public function tienePerfilActivo(string $u): bool { return (bool) $this->donde($this->perfiles, fn ($p) => [$p['local_user_ref_id'], $p['status'], $p['valid_to']] === [$u, 'active', null]); }
    public function medicoActivo(string $id): bool { return (bool) $this->donde($this->perfiles, fn ($p) => [$p['doctor_profile_id'], $p['status']] === [$id, 'active']); }

    public function asignaciones(string $medicoId): array {
        return array_map(fn ($a) => new Asignacion($a['specialty_id'], new Vigencia($a['valid_from'], $a['valid_to']), (bool) $a['is_primary']),
                         $this->donde($this->asignadas, fn ($a) => [$a['doctor_profile_id'], $a['status']] === [$medicoId, 'active']));
    }

    public function medicosConEspecialidad(string $codigo): array {
        $esp = array_column($this->donde($this->especialidades, fn ($s) => [$s['code'], $s['status']] === [$codigo, 'active']), 'specialty_id');
        $nombres = array_column($this->usuarios, 'display_name', 'local_user_ref_id');
        $m = [];
        foreach ($this->donde($this->perfiles, fn ($p) => $p['status'] === 'active') as $p) {
            foreach ($this->donde($this->asignadas, fn ($a) => $a['doctor_profile_id'] === $p['doctor_profile_id'] && $a['status'] === 'active' && in_array($a['specialty_id'], $esp, true)) as $a) {
                $m[$p['doctor_profile_id']] ??= [$nombres[$p['local_user_ref_id']], []];
                $m[$p['doctor_profile_id']][1][] = new Vigencia($a['valid_from'], $a['valid_to']);
            }
        }
        uasort($m, fn ($x, $y) => $x[0] <=> $y[0]);
        return $m;
    }

    public function horarios(array $ids): array {
        $h = [];
        foreach ($this->donde($this->horariosMed, fn ($f) => in_array($f['doctor_profile_id'], $ids, true)) as $f) { $h[$f['doctor_profile_id']][(int) $f['dia_semana']][] = [$f['inicio'], $f['fin']]; }
        foreach ($h as &$dias) { foreach ($dias as &$f) { sort($f); } }
        return $h;
    }

    public function citasActivas(array $ids, string $desde, string $hasta): array {
        $c = [];
        foreach ($this->donde($this->citas, fn ($a) => in_array($a['doctor_profile_id'], $ids, true) && in_array($a['status'], ['scheduled', 'confirmed'], true)
                && $a['starts_at'] < "$hasta 24:00" && $a['ends_at'] > $desde) as $a) { $c[$a['doctor_profile_id']][] = [substr($a['starts_at'], 0, 16), substr($a['ends_at'], 0, 16)]; }
        return $c;
    }

    public function guardarMedico(MedicoNuevo $m): void {
        if ($this->fallarAlGuardar) { throw new ErrorPersistencia('Falla simulada al guardar'); } // antes de tocar nada: todo o nada
        $this->perfiles[] = ['doctor_profile_id' => $m->id, 'local_user_ref_id' => $m->usuarioId, 'license_number' => $m->colegiado, 'status' => 'active', 'valid_from' => $m->vigencia->desde, 'valid_to' => null];
        if ($m->principal !== null) { $this->guardarAsignacion($m->asignacionId, $m->id, $m->principal); }
    }

    public function guardarAsignacion(string $id, string $medicoId, Asignacion $a): void {
        if ($this->fallarAlGuardar) { throw new ErrorPersistencia('Falla simulada al guardar'); }
        $this->asignadas[] = ['doctor_specialty_id' => $id, 'doctor_profile_id' => $medicoId, 'specialty_id' => $a->especialidadId, 'is_primary' => (int) $a->principal,
                              'valid_from' => $a->vigencia->desde, 'valid_to' => $a->vigencia->hasta, 'status' => 'active'];
    }
}
