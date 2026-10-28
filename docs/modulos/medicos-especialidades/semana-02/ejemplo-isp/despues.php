<?php
declare(strict_types=1);

// Semana 2 — DESPUÉS: interfaces pequeñas, una por tipo de cliente (ISP). La lógica de dominio no cambia.
namespace Medicos\Despues;

final class ErrorMedico extends \DomainException {}

interface RegistroDeMedicos {      // administrador
    public function registrarMedico(string $colegiado, string $documento, string $nombre): string;
    public function desactivarMedico(string $medicoId): void;
    public function listarMedicos(): array;
}
interface GestionEspecialidades {  // administrador
    public function asociarEspecialidad(string $medicoId, string $especialidad, string $desde, ?string $hasta, bool $principal): void;
}
interface HorarioDeAtencion {      // administrador
    public function definirHorario(string $medicoId, int $diaSemana, string $inicio, string $fin): void;
}
interface AusenciasDelMedico {     // administrador y el propio médico
    public function registrarAusencia(string $medicoId, string $fecha): void;
}
interface ConsultaDisponibilidad { // recepción, Citas (M05) y médico: solo lectura
    /** @return array<int, array{medico: string, inicio: string}> franjas libres de 30 minutos */
    public function consultarDisponibilidad(string $especialidad, string $desde, string $hasta): array;
}

/** Implementación completa en memoria. Cada cliente la ve solo a través de la interfaz que necesita. */
class ServicioMedicos implements RegistroDeMedicos, GestionEspecialidades, HorarioDeAtencion, AusenciasDelMedico, ConsultaDisponibilidad {
    private array $medicos = [];
    private array $especialidades = [];
    private array $horarios = [];
    private array $ausencias = [];

    /** @param array<string, bool> $catalogo especialidad => activa  @param \Closure $citasActivas fn(): array<int, array{medico: string, inicio: string}> (M05) */
    public function __construct(private array $catalogo, private \Closure $citasActivas, private string $hoy) {}

    public function registrarMedico(string $colegiado, string $documento, string $nombre): string {
        foreach ($this->medicos as $m) {
            if ($m['colegiado'] === $colegiado || $m['documento'] === $documento) { throw new ErrorMedico('medico_duplicado'); }
        }
        $id = 'med-' . (count($this->medicos) + 1);
        $this->medicos[$id] = ['colegiado' => $colegiado, 'documento' => $documento, 'nombre' => $nombre, 'activo' => true];
        return $id;
    }

    public function desactivarMedico(string $medicoId): void { $this->medicos[$medicoId]['activo'] = false; }

    public function listarMedicos(): array { return $this->medicos; }

    public function asociarEspecialidad(string $medicoId, string $especialidad, string $desde, ?string $hasta, bool $principal): void {
        if (!($this->catalogo[$especialidad] ?? false)) { throw new ErrorMedico('especialidad_invalida'); }
        foreach ($this->especialidades[$medicoId] ?? [] as $e) {
            $cruza = $desde <= ($e['hasta'] ?? '9999-12-31') && $e['desde'] <= ($hasta ?? '9999-12-31');
            if ($cruza && ($e['especialidad'] === $especialidad || ($principal && $e['principal']))) { throw new ErrorMedico('especialidad_duplicada'); }
        }
        $this->especialidades[$medicoId][] = compact('especialidad', 'desde', 'hasta', 'principal');
    }

    public function definirHorario(string $medicoId, int $diaSemana, string $inicio, string $fin): void { $this->horarios[$medicoId][$diaSemana][] = [$inicio, $fin]; }

    public function registrarAusencia(string $medicoId, string $fecha): void { $this->ausencias[$medicoId][$fecha] = true; }

    public function consultarDisponibilidad(string $especialidad, string $desde, string $hasta): array {
        $d = new \DateTimeImmutable($desde);
        if ($desde < $this->hoy || $hasta < $desde || $d->diff(new \DateTimeImmutable($hasta))->days > 14) { throw new ErrorMedico('rango_invalido'); }
        $ocupadas = array_flip(array_map(fn ($c) => $c['medico'] . '|' . $c['inicio'], ($this->citasActivas)()));
        $franjas = [];
        for ($dia = $d; $dia->format('Y-m-d') <= $hasta; $dia = $dia->modify('+1 day')) {
            $fecha = $dia->format('Y-m-d');
            foreach ($this->medicos as $id => $m) {
                if (!$m['activo'] || isset($this->ausencias[$id][$fecha]) || !$this->vigente($id, $especialidad, $fecha)) { continue; }
                foreach ($this->horarios[$id][(int) $dia->format('N')] ?? [] as [$ini, $fin]) {
                    for ($t = new \DateTimeImmutable("$fecha $ini"); $t->format('H:i') < $fin; $t = $t->modify('+30 minutes')) {
                        $inicio = $t->format('Y-m-d H:i');
                        if (!isset($ocupadas["$id|$inicio"])) { $franjas[] = ['medico' => $id, 'inicio' => $inicio]; }
                    }
                }
            }
        }
        return $franjas;
    }

    private function vigente(string $medicoId, string $especialidad, string $fecha): bool {
        foreach ($this->especialidades[$medicoId] ?? [] as $e) {
            if ($e['especialidad'] === $especialidad && $e['desde'] <= $fecha && ($e['hasta'] === null || $fecha <= $e['hasta'])) { return true; }
        }
        return false;
    }
}

// Clientes: cada uno depende solo de lo que usa.
final class PantallaRecepcion {
    public function __construct(private ConsultaDisponibilidad $disponibilidad) {}
    public function franjas(string $especialidad, string $desde, string $hasta): array { return $this->disponibilidad->consultarDisponibilidad($especialidad, $desde, $hasta); }
}
final class IntegracionCitas {
    public function __construct(private ConsultaDisponibilidad $disponibilidad) {}
    public function hayEspacio(string $medico, string $especialidad, string $inicio): bool {
        return in_array(['medico' => $medico, 'inicio' => $inicio], $this->disponibilidad->consultarDisponibilidad($especialidad, substr($inicio, 0, 10), substr($inicio, 0, 10)), true);
    }
}
final class PortalMedico {
    public function __construct(private AusenciasDelMedico&ConsultaDisponibilidad $servicio, private string $medicoId) {}
    public function avisarAusencia(string $fecha): void { $this->servicio->registrarAusencia($this->medicoId, $fecha); }
    public function misFranjas(string $especialidad, string $fecha): array {
        return array_values(array_filter($this->servicio->consultarDisponibilidad($especialidad, $fecha, $fecha), fn ($f) => $f['medico'] === $this->medicoId));
    }
}

/** Réplica de solo lectura para Citas: implementa únicamente lo que ofrece. Sin métodos de relleno. */
final class DisponibilidadEnCache implements ConsultaDisponibilidad {
    public function __construct(private array $franjas) {}
    public function consultarDisponibilidad(string $especialidad, string $desde, string $hasta): array {
        return array_values(array_filter($this->franjas, fn ($f) => substr($f['inicio'], 0, 10) >= $desde && substr($f['inicio'], 0, 10) <= $hasta));
    }
}
