<?php
declare(strict_types=1);

// Dominio: el contrato LSP de la semana 2 (MovimientoDeCama). Ahora recibe solo las dos camas que necesita leer
// (CamaOrigen, CamaDestino) en vez de todo el mapa de camas. No conoce HTTP, SQL ni otras capas (lo comprueba ARQ-01).
namespace Traslados\Dominio;

enum TipoAlta: string { case Medica = 'medica'; case Voluntaria = 'voluntaria'; case Referencia = 'referencia'; case Defuncion = 'defuncion'; }
enum RequisitoCama: string { case Cualquiera = 'cualquiera'; case Uci = 'uci'; }

final class ErrorDominio extends \DomainException {
    public function __construct(public readonly string $codigo, string $mensaje, public readonly array $campos = []) { parent::__construct($mensaje); }
}

/** Cama que el paciente ocupa hoy: su estancia abierta (bed_stays sin ended_at). */
final class CamaOrigen { public function __construct(public readonly string $camaId, public readonly string $estanciaId) {} }

/** Estado y tipo actuales de la cama destino (beds.status, beds.type). */
final class CamaDestino { public function __construct(public readonly string $camaId, public readonly string $estado, public readonly string $tipo) {} }

/** $rechazo: sin_cama, cama_no_disponible, cama_no_cumple_requisito, alta_invalida, o null si se aplicó. */
final class Resultado {
    private function __construct(public readonly ?string $rechazo, public readonly ?string $origenId = null, public readonly ?string $destinoId = null) {}
    public static function rechazado(string $codigo): self { return new self($codigo); }
    public static function aplicado(string $origenId, ?string $destinoId): self { return new self(null, $origenId, $destinoId); }
    public function fueAplicado(): bool { return $this->rechazo === null; }
}

/**
 * Contrato (semana 2): no exige más de lo que recibe ($origen puede ser null); devuelve siempre un Resultado;
 * si aplica, el origen queda libre para limpieza y el destino (si hay) ocupado; no lanza ni guarda nada.
 */
interface MovimientoDeCama { public function aplicar(?CamaOrigen $origen, ?CamaDestino $destino): Resultado; }

final class Traslado implements MovimientoDeCama {
    public function __construct(private RequisitoCama $requisito = RequisitoCama::Cualquiera) {}
    public function aplicar(?CamaOrigen $origen, ?CamaDestino $destino): Resultado {
        if ($origen === null) { return Resultado::rechazado('sin_cama'); }
        if ($destino === null || $destino->camaId === $origen->camaId || $destino->estado !== 'available') { return Resultado::rechazado('cama_no_disponible'); }
        if ($this->requisito === RequisitoCama::Uci && $destino->tipo !== 'uci') { return Resultado::rechazado('cama_no_cumple_requisito'); }
        return Resultado::aplicado($origen->camaId, $destino->camaId);
    }
}

final class Alta implements MovimientoDeCama {
    public function __construct(private TipoAlta $tipo, private string $motivo) {}
    public function aplicar(?CamaOrigen $origen, ?CamaDestino $destino): Resultado {
        if ($origen === null) { return Resultado::rechazado('sin_cama'); }
        if (trim($this->motivo) === '') { return Resultado::rechazado('alta_invalida'); }
        return Resultado::aplicado($origen->camaId, null); // toda alta libera, también la voluntaria (semana 2)
    }
}
