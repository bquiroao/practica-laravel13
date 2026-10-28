<?php
declare(strict_types=1);

namespace SignosVitales\Dominio;

/** Toma de signos vitales: solo se crea completa y válida; no se edita (RF-10). */
final class Toma {
    private function __construct(public readonly string $id, public readonly string $pacienteId, public readonly string $episodioId,
                                 public readonly string $autorId, public readonly \DateTimeImmutable $tomadaEn,
                                 public readonly array $mediciones, public readonly array $clasificaciones) {}

    public static function registrar(string $id, string $pacienteId, string $episodioId, string $autorId, \DateTimeImmutable $en,
                                     array $mediciones, array $rangos, EvaluadorUmbrales $evaluador): self {
        if ($mediciones === []) { throw new ErrorDominio('toma_vacia', 'La toma necesita al menos una medición'); }
        $tipos = array_map(fn (Medicion $m) => $m->tipo->value, $mediciones);
        if (count(array_unique($tipos)) !== count($tipos)) { throw new ErrorDominio('signo_repetido', 'Cada signo va una sola vez por toma'); }
        (new ValidadorLimitesFisicos())->exigirPosibles($mediciones);
        return new self($id, $pacienteId, $episodioId, $autorId, $en, $mediciones, $evaluador->evaluar($mediciones, $rangos));
    }

    /** @return array<int, array{signo: string, nivel: string, valor: float}> una alerta por medición anormal o crítica */
    public function alertas(): array {
        $alertas = [];
        foreach ($this->mediciones as $m) {
            $c = $this->clasificaciones[$m->tipo->value];
            if ($c === Clasificacion::Anormal || $c === Clasificacion::Critica) { $alertas[] = ['signo' => $m->tipo->value, 'nivel' => $c->value, 'valor' => $m->valor]; }
        }
        return $alertas;
    }
}
