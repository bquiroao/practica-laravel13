<?php
declare(strict_types=1);

// Semana 2 — DESPUÉS: todas las reglas cumplen el mismo contrato y son intercambiables (LSP).
namespace SignosVitales\Despues;

enum TipoSigno: string { case PAS = 'pas'; case PAD = 'pad'; case FC = 'fc'; case FR = 'fr'; case TEMP = 'temp'; case SPO2 = 'spo2'; }
enum Clasificacion: string { case Normal = 'normal'; case Anormal = 'anormal'; case Critica = 'critica'; case SinRango = 'sin_rango'; }

final class Medicion {
    public function __construct(public readonly TipoSigno $tipo, public readonly float $valor) {}
}

/** Rango del catálogo para una edad. Crítica si valor < criticoBajo o valor > criticoAlto. */
final class RangoSigno {
    public function __construct(public readonly float $normalMin, public readonly float $normalMax,
                                public readonly float $criticoBajo, public readonly float $criticoAlto) {}
}

/**
 * Contrato (lo cumple toda implementación):
 * - Pre:  la medición ya pasó ValidadorLimitesFisicos; $rango puede ser null. Ninguna regla pide más.
 * - Post: devuelve siempre una Clasificacion; sin rango → SinRango; debajo de criticoBajo → Critica.
 * - No lanza excepciones ni tiene efectos (no guarda, no notifica).
 */
interface ReglaDeUmbral {
    public function clasificar(Medicion $m, ?RangoSigno $rango): Clasificacion;
}

final class ReglaPorIntervalo implements ReglaDeUmbral {
    public function clasificar(Medicion $m, ?RangoSigno $r): Clasificacion {
        if ($r === null) { return Clasificacion::SinRango; }
        if ($m->valor < $r->criticoBajo || $m->valor > $r->criticoAlto) { return Clasificacion::Critica; }
        return ($m->valor < $r->normalMin || $m->valor > $r->normalMax) ? Clasificacion::Anormal : Clasificacion::Normal;
    }
}

/** Saturación de oxígeno: solo el valor bajo es peligroso (100 % es el máximo posible). */
final class ReglaLimiteInferior implements ReglaDeUmbral {
    public function clasificar(Medicion $m, ?RangoSigno $r): Clasificacion {
        if ($r === null) { return Clasificacion::SinRango; }
        if ($m->valor < $r->criticoBajo) { return Clasificacion::Critica; }
        return $m->valor < $r->normalMin ? Clasificacion::Anormal : Clasificacion::Normal;
    }
}

/** CU-02: la precondición vive aquí, una sola vez, y no dentro de cada regla. */
final class ValidadorLimitesFisicos {
    public const LIMITES = ['pas' => [40, 300], 'pad' => [20, 200], 'fc' => [20, 300], 'fr' => [1, 80], 'temp' => [25, 45], 'spo2' => [50, 100]];

    /** @param Medicion[] $mediciones  @return string[] signos con valor imposible */
    public function invalidas(array $mediciones): array {
        $malas = [];
        foreach ($mediciones as $m) {
            [$min, $max] = self::LIMITES[$m->tipo->value];
            if ($m->valor < $min || $m->valor > $max) { $malas[] = $m->tipo->value; }
        }
        return $malas;
    }
}

/** CU-03: el cliente solo conoce la interfaz. Sin instanceof, sin try/catch. Sin rango cargado (pediatría hoy) → SinRango. */
final class EvaluadorUmbrales {
    /** @param array<string, ReglaDeUmbral> $reglas */
    public function __construct(private array $reglas, private ReglaDeUmbral $porDefecto = new ReglaPorIntervalo()) {}

    /** @param Medicion[] $mediciones  @param array<string, RangoSigno> $rangos  @return array<string, Clasificacion> */
    public function evaluar(array $mediciones, array $rangos): array {
        $resultado = [];
        foreach ($mediciones as $m) {
            $regla = $this->reglas[$m->tipo->value] ?? $this->porDefecto;
            $resultado[$m->tipo->value] = $regla->clasificar($m, $rangos[$m->tipo->value] ?? null);
        }
        return $resultado;
    }
}

/** CU-04: convierte clasificaciones en alertas; no sabe cómo se clasificó. */
final class GeneradorAlertas {
    /** @param array<string, Clasificacion> $clasificaciones */
    public function alertas(array $clasificaciones): array {
        $alertas = [];
        foreach ($clasificaciones as $signo => $c) {
            if ($c === Clasificacion::Anormal || $c === Clasificacion::Critica) { $alertas[] = ['signo' => $signo, 'nivel' => $c->value]; }
        }
        return $alertas;
    }
}

/** Rangos de adulto de ejemplo (tabla de la semana 1). */
function rangosAdulto(): array {
    return ['pas' => new RangoSigno(90, 139, 80, 179), 'pad' => new RangoSigno(60, 89, 40, 119),
            'fc' => new RangoSigno(60, 100, 40, 130), 'fr' => new RangoSigno(12, 20, 8, 30),
            'temp' => new RangoSigno(36.0, 37.5, 35.0, 39.9), 'spo2' => new RangoSigno(95, 100, 90, 100)];
}

function evaluadorEstandar(): EvaluadorUmbrales {
    $intervalo = new ReglaPorIntervalo();
    return new EvaluadorUmbrales(['pas' => $intervalo, 'pad' => $intervalo, 'fc' => $intervalo, 'fr' => $intervalo, 'temp' => $intervalo, 'spo2' => new ReglaLimiteInferior()]);
}
