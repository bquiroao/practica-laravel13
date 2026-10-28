<?php
declare(strict_types=1);

// Semana 2 — DESPUÉS: todo movimiento de cama cumple el mismo contrato y es intercambiable (LSP).
namespace Traslados\Despues;

enum TipoAlta: string { case Medica = 'medica'; case Voluntaria = 'voluntaria'; case Referencia = 'referencia'; case Defuncion = 'defuncion'; }
enum RequisitoCama: string { case Cualquiera = 'cualquiera'; case Uci = 'uci'; }

final class Resultado {
    /** $rechazo: código estable (sin_cama, cama_no_disponible, cama_no_cumple_requisito, alta_invalida) o null si se aplicó */
    public function __construct(public readonly array $camas, public readonly ?string $rechazo = null) {}
}

/**
 * Contrato (lo cumple toda implementación):
 * - Pre:  estado de camas consistente (una cama «occupied» tiene paciente y ningún paciente está en dos camas). Ninguna pide más.
 * - Post: devuelve siempre un Resultado. Si se rechaza, trae un código y las camas iguales. Si se aplica, el paciente
 *         deja su cama de origen y esta queda en 'maintenance' (limpieza); el estado sigue consistente.
 * - No lanza excepciones ni guarda nada: el caso de uso aplica el Resultado en una transacción.
 */
interface MovimientoDeCama { public function aplicar(array $camas): Resultado; }

/** Funciones comunes, sin estado: buscar la cama del paciente y liberarla para limpieza. */
final class Camas {
    public static function de(array $camas, string $paciente): ?string { foreach ($camas as $id => $c) { if ($c['paciente'] === $paciente) { return $id; } } return null; }

    public static function liberar(array $camas, string $id): array { $camas[$id] = ['estado' => 'maintenance', 'paciente' => null] + $camas[$id]; return $camas; }
}

final class Traslado implements MovimientoDeCama {
    public function __construct(private string $paciente, private string $destino, private RequisitoCama $requisito = RequisitoCama::Cualquiera) {}

    public function aplicar(array $camas): Resultado {
        $origen = Camas::de($camas, $this->paciente);
        $destino = $camas[$this->destino] ?? null;
        if ($origen === null) { return new Resultado($camas, 'sin_cama'); }
        if ($destino === null || $this->destino === $origen || $destino['estado'] !== 'available') { return new Resultado($camas, 'cama_no_disponible'); }
        if ($this->requisito === RequisitoCama::Uci && $destino['tipo'] !== 'uci') { return new Resultado($camas, 'cama_no_cumple_requisito'); }
        $nuevo = Camas::liberar($camas, $origen);
        $nuevo[$this->destino] = ['estado' => 'occupied', 'paciente' => $this->paciente] + $destino;
        return new Resultado($nuevo);
    }
}

final class Alta implements MovimientoDeCama {
    public function __construct(private string $paciente, private TipoAlta $tipo, private string $motivo) {}

    public function aplicar(array $camas): Resultado {
        $origen = Camas::de($camas, $this->paciente);
        if ($origen === null) { return new Resultado($camas, 'sin_cama'); }
        if (trim($this->motivo) === '') { return new Resultado($camas, 'alta_invalida'); }
        return new Resultado(Camas::liberar($camas, $origen)); // la voluntaria también libera; el formulario firmado es otro requisito
    }
}

/** Ir a quirófano o a un estudio y volver NO es un movimiento de cama: no implementa MovimientoDeCama y no toca las camas. */
final class SalidaTemporal {
    public function __construct(private string $paciente, private string $servicio) {}

    public function registrar(array $camas): array { return ['paciente' => $this->paciente, 'servicio' => $this->servicio, 'cama_reservada' => Camas::de($camas, $this->paciente)]; }
}

/** Cliente: solo conoce la interfaz. Sin instanceof ni try/catch. */
final class RegistrarMovimiento { public function ejecutar(MovimientoDeCama $m, array $camas): Resultado { return $m->aplicar($camas); } }

/** Invariante de ocupación: «occupied» ⇔ tiene paciente, y ningún paciente en dos camas. */
function consistente(array $camas): bool {
    $pacientes = array_filter(array_column($camas, 'paciente'));
    foreach ($camas as $c) { if (($c['estado'] === 'occupied') !== ($c['paciente'] !== null)) { return false; } }
    return count($pacientes) === count(array_unique($pacientes));
}

/** Estado de ejemplo: dos pacientes internados, una cama general libre, una de UCI libre y una en limpieza. */
function camasEjemplo(): array {
    return ['A1' => ['estado' => 'occupied', 'paciente' => 'p1', 'tipo' => 'general'], 'A2' => ['estado' => 'available', 'paciente' => null, 'tipo' => 'general'],
            'A3' => ['estado' => 'occupied', 'paciente' => 'p2', 'tipo' => 'general'], 'U1' => ['estado' => 'available', 'paciente' => null, 'tipo' => 'uci'],
            'M1' => ['estado' => 'maintenance', 'paciente' => null, 'tipo' => 'general']];
}
