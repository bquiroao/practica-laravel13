<?php
declare(strict_types=1);

// Semana 2 — ANTES: una jerarquía de evaluadores que rompe LSP.
// Contrato de la base: evaluar() devuelve 'normal' | 'anormal' | 'critica' | 'sin_rango' y nunca lanza.
namespace SignosVitales\Antes;

class EvaluadorSigno {
    /** @param float[] $rango [normalMin, normalMax, criticoBajo, criticoAlto] o [] si no hay rango */
    public function __construct(protected array $rango = []) {}

    public function evaluar(float $valor): string {
        if ($this->rango === []) { return 'sin_rango'; }
        [$nMin, $nMax, $cBajo, $cAlto] = $this->rango;
        if ($valor < $cBajo || $valor > $cAlto) { return 'critica'; }
        return ($valor < $nMin || $valor > $nMax) ? 'anormal' : 'normal';
    }
}

// V1: rechaza la operación heredada (la presión son dos números).
class EvaluadorPresion extends EvaluadorSigno {
    public function evaluar(float $valor): string { throw new \LogicException('Use evaluarPresion($sistolica, $diastolica)'); }
    public function evaluarPresion(float $s, float $d): string { return $s >= 180 || $d >= 120 ? 'critica' : 'normal'; }
}

// V2: precondición más fuerte (exige rango; la base acepta que no haya).
class EvaluadorSaturacion extends EvaluadorSigno {
    public function evaluar(float $valor): string {
        if ($this->rango === []) { throw new \RuntimeException('La saturación necesita rango'); }
        return parent::evaluar($valor);
    }
}

// V3: postcondición más débil (devuelve un valor fuera del contrato y esconde una crítica).
class EvaluadorTemperatura extends EvaluadorSigno {
    public function evaluar(float $valor): string { return $valor > 42 ? 'revisar_termometro' : parent::evaluar($valor); }
}

// El cliente paga el precio: instanceof, try/catch y un valor que no sabe mostrar.
function evaluarToma(array $evaluadores, array $valores): array {
    $resultado = [];
    foreach ($valores as $signo => $valor) {
        $ev = $evaluadores[$signo];
        try {
            $resultado[$signo] = $ev instanceof EvaluadorPresion ? $ev->evaluarPresion($valor[0], $valor[1]) : $ev->evaluar($valor);
        } catch (\RuntimeException) {
            $resultado[$signo] = 'sin_rango';
        }
    }
    return $resultado;
}
