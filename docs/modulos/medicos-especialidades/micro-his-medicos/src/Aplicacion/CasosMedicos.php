<?php
declare(strict_types=1);

// Aplicación: orquesta el flujo «registro de médico, asociación de especialidad y consulta de disponibilidad». Sin SQL ni HTTP.
// Semana 3: depende directo del adaptador PDO. En la semana 4 se invierte con una interfaz Repository.
namespace Medicos\Aplicacion;

use Medicos\Dominio\{Agenda, Asignacion, ErrorDominio, ReglasEspecialidad, Vigencia};
use Medicos\Persistencia\MedicosPdo;

final class Usuario { public function __construct(public readonly string $id, public readonly string $rol) {} }
final class ErrorPersistencia extends \RuntimeException {}
function exigirRol(Usuario $u, array $roles): void {
    if (!in_array($u->rol, $roles, true)) { throw new ErrorDominio('acceso_denegado', 'Su rol no puede hacer esta operación'); }
}

/** CU-01 y CU-03: crea el perfil del médico y, si se indica, su especialidad principal, todo en una transacción. $hoy y $nuevoId: fn () => string. */
final class RegistrarMedico {
    public function __construct(private MedicosPdo $bd, private \Closure $hoy, private \Closure $nuevoId) {}
    public function ejecutar(array $e, Usuario $u): array {
        exigirRol($u, ['admin']);
        [$usuarioId, $colegiado] = [(string) ($e['usuario_id'] ?? ''), trim((string) ($e['colegiado'] ?? ''))];
        if ($colegiado === '' || !$this->bd->usuarioActivo($usuarioId)) { throw new ErrorDominio('usuario_no_disponible', 'Falta el colegiado, o el usuario no existe o no está activo'); }
        if ($this->bd->colegiadoRegistrado($colegiado) || $this->bd->tienePerfilActivo($usuarioId)) { throw new ErrorDominio('medico_duplicado', 'Ese colegiado o ese usuario ya tienen perfil de médico'); }
        $vigencia = new Vigencia((string) ($e['desde'] ?? ($this->hoy)()));
        $principal = isset($e['especialidad_id']) ? new Asignacion(exigirEspecialidad($this->bd, (string) $e['especialidad_id']), $vigencia, true) : null;
        $id = ($this->nuevoId)();
        $this->bd->guardarMedico($id, $usuarioId, $colegiado, $vigencia, $principal, ($this->nuevoId)());
        return ['medico_id' => $id, 'especialidad_principal' => $principal?->especialidadId];
    }
}
function exigirEspecialidad(MedicosPdo $bd, string $id): string {
    if (!$bd->especialidadActiva($id)) { throw new ErrorDominio('especialidad_invalida', 'La especialidad no existe o no está activa'); }
    return $id;
}
/** CU-03: asocia otra especialidad a un médico existente. */
final class AsociarEspecialidad {
    public function __construct(private MedicosPdo $bd, private \Closure $nuevoId) {}
    public function ejecutar(string $medicoId, array $e, Usuario $u): array {
        exigirRol($u, ['admin']);
        if (!$this->bd->medicoActivo($medicoId)) { throw new ErrorDominio('medico_no_encontrado', 'El médico no existe o no está activo'); }
        $nueva = new Asignacion(exigirEspecialidad($this->bd, (string) ($e['especialidad_id'] ?? '')), new Vigencia((string) ($e['desde'] ?? ''), $e['hasta'] ?? null), (bool) ($e['principal'] ?? false));
        ReglasEspecialidad::exigirAsignable($this->bd->asignaciones($medicoId), $nueva);
        $this->bd->guardarAsignacion($id = ($this->nuevoId)(), $medicoId, $nueva);
        return ['asignacion_id' => $id];
    }
}
/** CU-06/CU-07: franjas libres de 30 min por especialidad = horario − citas activas; solo médicos activos con la especialidad vigente ese día. */
final class ConsultarDisponibilidad {
    public function __construct(private MedicosPdo $bd, private \Closure $hoy) {}
    public function ejecutar(string $codigo, string $desde, string $hasta, Usuario $u): array {
        exigirRol($u, ['admin', 'recepcion', 'medico']);
        Agenda::exigirRango($desde, $hasta, ($this->hoy)());
        $medicos = $this->bd->medicosConEspecialidad($codigo);   // id => [nombre, Vigencia[]]
        [$horarios, $citas] = [$this->bd->horarios(array_keys($medicos)), $this->bd->citasActivas(array_keys($medicos), $desde, $hasta)];
        $franjas = [];
        for ($d = new \DateTimeImmutable($desde); ($fecha = $d->format('Y-m-d')) <= $hasta; $d = $d->modify('+1 day')) {
            foreach ($medicos as $id => [$nombre, $vigencias]) {
                if (!array_filter($vigencias, fn (Vigencia $v) => $v->incluye($fecha))) { continue; }
                foreach (Agenda::franjas($fecha, $horarios[$id][(int) $d->format('N')] ?? [], $citas[$id] ?? []) as $inicio) {
                    $franjas[] = ['medico_id' => $id, 'medico' => $nombre, 'inicio' => $inicio];
                }
            }
        }
        return $franjas;
    }
}
