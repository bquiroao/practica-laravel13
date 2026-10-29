<?php
declare(strict_types=1);

// Límite de la Aplicación (semana 4): lo que los casos de uso necesitan, sin saber si detrás hay PDO o memoria.
namespace Medicos\Aplicacion;

use Medicos\Dominio\{Asignacion, Vigencia};

/** Médico nuevo ya validado, listo para guardarse todo o nada (perfil + especialidad principal). Lo usan los dos adaptadores. */
final class MedicoNuevo {
    public function __construct(public readonly string $id, public readonly string $usuarioId, public readonly string $colegiado,
                                public readonly Vigencia $vigencia, public readonly ?Asignacion $principal, public readonly string $asignacionId) {}
}

/** Repository de lo que este módulo escribe: doctor_profiles, doctor_specialties y horarios_medico. Si falla lanza ErrorPersistencia. */
interface RepositorioMedicos {
    public function colegiadoRegistrado(string $colegiado): bool;
    public function tienePerfilActivo(string $usuarioId): bool;
    public function medicoActivo(string $id): bool;
    /** @return Asignacion[] especialidades activas del médico */
    public function asignaciones(string $medicoId): array;
    /** @return array<string, array{0: string, 1: Vigencia[]}> médico => [nombre, vigencias]: activos con esa especialidad activa */
    public function medicosConEspecialidad(string $codigo): array;
    /** @return array<string, array<int, list<array{0: string, 1: string}>>> médico => día de la semana => [[inicio, fin]] */
    public function horarios(array $ids): array;
    public function guardarMedico(MedicoNuevo $m): void;
    public function guardarAsignacion(string $id, string $medicoId, Asignacion $a): void;
}

/** Lo que el módulo solo lee de otros: el usuario (Identidad), el catálogo de especialidades y las citas (Citas, M05). */
interface FuenteHospital {
    public function usuarioActivo(string $id): bool;
    public function especialidadActiva(string $id): bool;
    /** @return array<string, list<array{0: string, 1: string}>> médico => [[inicio, fin]] 'Y-m-d H:i' de citas 'scheduled' o 'confirmed' */
    public function citasActivas(array $ids, string $desde, string $hasta): array;
}
