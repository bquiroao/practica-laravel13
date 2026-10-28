<?php
declare(strict_types=1);

// Semana 2 — ANTES: jerarquía de movimientos que rompe LSP.
// Contrato de la base: aplicar() devuelve el nuevo estado de camas (o el mismo si no se puede), nunca lanza,
// un paciente ocupa como máximo una cama y la cama de origen queda en 'maintenance' (limpieza).
namespace Traslados\Antes;

class Movimiento {
    public function __construct(protected string $paciente) {}

    public function aplicar(array $camas): array { return $camas; }

    protected function origen(array $camas): ?string { foreach ($camas as $id => $c) { if ($c['paciente'] === $this->paciente) { return $id; } } return null; }

    protected function liberar(array $camas, string $id): array { $camas[$id] = ['estado' => 'maintenance', 'paciente' => null] + $camas[$id]; return $camas; }

    public function paciente(): string { return $this->paciente; }
}

class Traslado extends Movimiento {
    public function __construct(string $paciente, protected string $destino) { parent::__construct($paciente); }

    public function aplicar(array $camas): array {
        $origen = $this->origen($camas);
        if ($origen === null || $origen === $this->destino || ($camas[$this->destino]['estado'] ?? '') !== 'available') { return $camas; }
        $camas = $this->liberar($camas, $origen);
        $camas[$this->destino] = ['estado' => 'occupied', 'paciente' => $this->paciente] + $camas[$this->destino];
        return $camas;
    }
}

class Alta extends Movimiento {
    public function aplicar(array $camas): array {
        $origen = $this->origen($camas);
        return $origen === null ? $camas : $this->liberar($camas, $origen);
    }
}

// V1: postcondición más débil. «Hasta que firme el formulario» no libera la cama: queda ocupada por alguien que ya se fue.
class AltaVoluntaria extends Alta { public function aplicar(array $camas): array { return $camas; } }

// V2: precondición más fuerte. Si la cama no es de UCI lanza, cuando la base solo devolvía el estado sin cambios.
class TrasladoUci extends Traslado {
    public function aplicar(array $camas): array {
        if (($camas[$this->destino]['tipo'] ?? '') !== 'uci') { throw new \DomainException('La cama destino no es de UCI'); }
        return parent::aplicar($camas);
    }
}

// V3: invariante roto. Ocupa la cama destino «mientras va a quirófano» sin soltar la de origen: un paciente en dos camas.
class TrasladoTemporal extends Traslado {
    public function aplicar(array $camas): array {
        $camas[$this->destino] = ['estado' => 'occupied', 'paciente' => $this->paciente] + $camas[$this->destino];
        return $camas;
    }
}

// El cliente paga el precio: instanceof y try/catch para corregir a cada subclase.
function registrarMovimiento(Movimiento $m, array $camas): array {
    try { $nuevo = $m->aplicar($camas); } catch (\DomainException) { return $camas; }
    if ($m instanceof AltaVoluntaria) { $nuevo = (new Alta($m->paciente()))->aplicar($nuevo); }
    return $nuevo;
}
