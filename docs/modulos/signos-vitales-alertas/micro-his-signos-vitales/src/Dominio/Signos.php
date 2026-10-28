<?php
declare(strict_types=1);

// Dominio: reglas de signos vitales. No conoce HTTP, SQL ni casos de uso (diseño LSP de la semana 2).
namespace SignosVitales\Dominio;

enum TipoSigno: string { case PAS = 'pas'; case PAD = 'pad'; case FC = 'fc'; case FR = 'fr'; case TEMP = 'temp'; case SPO2 = 'spo2'; }
enum Clasificacion: string { case Normal = 'normal'; case Anormal = 'anormal'; case Critica = 'critica'; case SinRango = 'sin_rango'; }

final class ErrorDominio extends \DomainException { // $campos: signos con problema
    public function __construct(public readonly string $codigo, string $mensaje, public readonly array $campos = []) { parent::__construct($mensaje); }
}

final class Medicion {
    public function __construct(public readonly TipoSigno $tipo, public readonly float $valor) {}

    public static function desde(string $tipo, mixed $valor): self {
        $t = TipoSigno::tryFrom($tipo);
        if ($t === null || !is_int($valor) && !is_float($valor)) { throw new ErrorDominio('medicion_invalida', "Signo o valor no válido: $tipo", [$tipo]); }
        return new self($t, (float) $valor);
    }
}

/** Crítica si valor < criticoBajo o valor > criticoAlto. */
final class RangoSigno {
    public function __construct(public readonly float $normalMin, public readonly float $normalMax,
                                public readonly float $criticoBajo, public readonly float $criticoAlto) {}
}

/** Contrato: pre medición posible y rango opcional; post siempre una Clasificacion; no lanza ni tiene efectos. */
interface ReglaDeUmbral { public function clasificar(Medicion $m, ?RangoSigno $r): Clasificacion; }

final class ReglaPorIntervalo implements ReglaDeUmbral {
    public function clasificar(Medicion $m, ?RangoSigno $r): Clasificacion {
        if ($r === null) { return Clasificacion::SinRango; }
        if ($m->valor < $r->criticoBajo || $m->valor > $r->criticoAlto) { return Clasificacion::Critica; }
        return ($m->valor < $r->normalMin || $m->valor > $r->normalMax) ? Clasificacion::Anormal : Clasificacion::Normal;
    }
}

final class ReglaLimiteInferior implements ReglaDeUmbral { // SpO2: solo el valor bajo es peligroso
    public function clasificar(Medicion $m, ?RangoSigno $r): Clasificacion {
        if ($r === null) { return Clasificacion::SinRango; }
        if ($m->valor < $r->criticoBajo) { return Clasificacion::Critica; }
        return $m->valor < $r->normalMin ? Clasificacion::Anormal : Clasificacion::Normal;
    }
}

final class ValidadorLimitesFisicos {
    public const LIMITES = ['pas' => [40, 300], 'pad' => [20, 200], 'fc' => [20, 300], 'fr' => [1, 80], 'temp' => [25, 45], 'spo2' => [50, 100]];

    /** @param Medicion[] $mediciones */
    public function exigirPosibles(array $mediciones): void {
        $malas = array_values(array_map(fn (Medicion $m) => $m->tipo->value, array_filter($mediciones,
            fn (Medicion $m) => $m->valor < self::LIMITES[$m->tipo->value][0] || $m->valor > self::LIMITES[$m->tipo->value][1])));
        if ($malas !== []) { throw new ErrorDominio('medicion_invalida', 'Valor físicamente imposible', $malas); }
    }
}

final class EvaluadorUmbrales {
    public function __construct(private array $reglas = [], private ReglaDeUmbral $porDefecto = new ReglaPorIntervalo()) {}

    public static function estandar(): self { return new self(['spo2' => new ReglaLimiteInferior()]); }

    /** @return array<string, Clasificacion> clasificación por signo; sin rango → SinRango */
    public function evaluar(array $mediciones, array $rangos): array {
        $r = [];
        foreach ($mediciones as $m) { $r[$m->tipo->value] = ($this->reglas[$m->tipo->value] ?? $this->porDefecto)->clasificar($m, $rangos[$m->tipo->value] ?? null); }
        return $r;
    }
}
