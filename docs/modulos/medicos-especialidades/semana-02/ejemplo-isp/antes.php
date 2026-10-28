<?php
declare(strict_types=1);

// Semana 2 — ANTES: una sola interfaz «gorda» con todo lo del módulo. Cada cliente depende de los 7 métodos aunque use 1 o 2.
// La lógica de dominio es la misma que en despues.php (se reutiliza); lo que cambia es la forma de las interfaces.
namespace Medicos\Antes;

interface ServicioMedicos {
    public function registrarMedico(string $colegiado, string $documento, string $nombre): string;
    public function desactivarMedico(string $medicoId): void;
    public function listarMedicos(): array;
    public function asociarEspecialidad(string $medicoId, string $especialidad, string $desde, ?string $hasta, bool $principal): void;
    public function definirHorario(string $medicoId, int $diaSemana, string $inicio, string $fin): void;
    public function registrarAusencia(string $medicoId, string $fecha): void;
    public function consultarDisponibilidad(string $especialidad, string $desde, string $hasta): array;
}

final class ServicioCompleto extends \Medicos\Despues\ServicioMedicos implements ServicioMedicos {}

// Recepción solo consulta, pero recibe también registrar, desactivar, horarios y ausencias.
final class PantallaRecepcion {
    public function __construct(private ServicioMedicos $medicos) {}
    public function franjas(string $especialidad, string $desde, string $hasta): array { return $this->medicos->consultarDisponibilidad($especialidad, $desde, $hasta); }
}

final class IntegracionCitas {
    public function __construct(private ServicioMedicos $medicos) {}
    public function hayEspacio(string $medico, string $especialidad, string $inicio): bool {
        return in_array(['medico' => $medico, 'inicio' => $inicio], $this->medicos->consultarDisponibilidad($especialidad, substr($inicio, 0, 10), substr($inicio, 0, 10)), true);
    }
}

final class PortalMedico {
    public function __construct(private ServicioMedicos $medicos, private string $medicoId) {}
    public function avisarAusencia(string $fecha): void { $this->medicos->registrarAusencia($this->medicoId, $fecha); }
    public function misFranjas(string $especialidad, string $fecha): array {
        return array_values(array_filter($this->medicos->consultarDisponibilidad($especialidad, $fecha, $fecha), fn ($f) => $f['medico'] === $this->medicoId));
    }
}

// La réplica de solo lectura para Citas está obligada a «implementar» 6 métodos que no tiene: los rellena con excepciones.
final class DisponibilidadEnCache implements ServicioMedicos {
    public function __construct(private array $franjas) {}
    public function registrarMedico(string $colegiado, string $documento, string $nombre): string { throw new \LogicException('no_soportado'); }
    public function desactivarMedico(string $medicoId): void { throw new \LogicException('no_soportado'); }
    public function listarMedicos(): array { throw new \LogicException('no_soportado'); }
    public function asociarEspecialidad(string $medicoId, string $especialidad, string $desde, ?string $hasta, bool $principal): void { throw new \LogicException('no_soportado'); }
    public function definirHorario(string $medicoId, int $diaSemana, string $inicio, string $fin): void { throw new \LogicException('no_soportado'); }
    public function registrarAusencia(string $medicoId, string $fecha): void { throw new \LogicException('no_soportado'); }
    public function consultarDisponibilidad(string $especialidad, string $desde, string $hasta): array {
        return array_values(array_filter($this->franjas, fn ($f) => substr($f['inicio'], 0, 10) >= $desde && substr($f['inicio'], 0, 10) <= $hasta));
    }
}
