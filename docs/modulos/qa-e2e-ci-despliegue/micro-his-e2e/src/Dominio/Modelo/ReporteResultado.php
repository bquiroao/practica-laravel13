<?php

declare(strict_types=1);

namespace E2e\Dominio\Modelo;

use E2e\Dominio\Excepciones\ReglaRechazada;

/**
 * Semana 7 (refactor): lo que el pipeline reporta, con las reglas que antes decidía el caso de uso.
 *   · la clave de idempotencia tiene el formato del contrato (R-12);
 *   · la huella del contenido (para distinguir un reintento de un conflicto) es la MISMA fórmula de la semana 3;
 *   · cuándo una repetición es «el mismo reporte» lo decide esta clase, no la aplicación.
 */
final readonly class ReporteResultado
{
    /** @param list<array{tipo: string, bytes: string}> $artefactos */
    private function __construct(
        public int $ejecucion,
        public string $clave,
        public string $huella,
        public string $escenario,
        public string $resultado,
        public int $intentos,
        public array $artefactos,
    ) {
    }

    /** @param array<string, mixed> $datos escenario, resultado, intentos, evidencias[{tipo, bytes}] */
    public static function desde(int $ejecucion, string $clave, array $datos): self
    {
        $artefactos = is_array($datos['evidencias'] ?? null) ? $datos['evidencias'] : [];
        $huella = hash('sha256', json_encode([$ejecucion, $datos['escenario'] ?? null, $datos['resultado'] ?? null, $datos['intentos'] ?? null,
            array_map(static fn ($a): array => [is_array($a) ? ($a['tipo'] ?? null) : null, is_array($a) ? hash('sha256', (string) ($a['bytes'] ?? '')) : null], $artefactos)],
            JSON_THROW_ON_ERROR));
        if (!preg_match('/^[A-Za-z0-9._-]{8,80}$/', $clave)) {
            throw new ReglaRechazada('DATOS_INVALIDOS', ['Idempotency-Key']);
        }

        return new self($ejecucion, $clave, $huella, (string) ($datos['escenario'] ?? ''), (string) ($datos['resultado'] ?? ''),
            is_int($datos['intentos'] ?? null) ? $datos['intentos'] : -1,
            array_map(static fn ($a): array => ['tipo' => is_array($a) ? (string) ($a['tipo'] ?? '') : '', 'bytes' => is_array($a) ? (string) ($a['bytes'] ?? '') : ''], $artefactos));
    }

    /** R-12: la misma clave es el MISMO reporte solo si es de la misma ejecución y con el mismo contenido; si no, es un conflicto. */
    public function repite(int $ejecucionPrevia, ResultadoEscenario $previo): bool
    {
        return $ejecucionPrevia === $this->ejecucion && $previo->huella === $this->huella;
    }
}
