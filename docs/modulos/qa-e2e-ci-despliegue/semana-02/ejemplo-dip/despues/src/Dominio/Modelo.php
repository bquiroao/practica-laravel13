<?php

declare(strict_types=1);

namespace E2e\Dominio;

/** Resultado de un escenario E2E en una ejecución (R-06: el que pasó al reintentar es INESTABLE, no PASA). */
enum Resultado: string
{
    case PASA = 'PASA';
    case FALLA = 'FALLA';
    case INESTABLE = 'INESTABLE';
    case OMITIDO = 'OMITIDO';

    public function exigeEvidencia(): bool
    {
        return $this === self::FALLA || $this === self::INESTABLE;
    }
}

final class ResultadoEscenario
{
    /** @param list<string> $evidencias SHA-256 de los artefactos (traza, captura, log) */
    public function __construct(
        public readonly string $escenario,
        public readonly bool $critico,
        public readonly Resultado $resultado,
        public readonly int $intentos,
        public readonly array $evidencias,
    ) {
    }
}

/** Una corrida del pipeline sobre UN digest (R-03). */
final class Ejecucion
{
    /** @param list<ResultadoEscenario> $resultados */
    public function __construct(
        public readonly string $id,
        public readonly string $digest,
        public readonly bool $completada,
        public readonly \DateTimeImmutable $terminadaEn,
        public readonly array $resultados,
    ) {
    }
}

/** R-09: los umbrales son datos de la política, no constantes enterradas en el código. */
final class PoliticaGate
{
    public function __construct(
        public readonly string $version,
        public readonly float $umbralNoCriticos,
        public readonly int $maxInestables,
        public readonly int $antiguedadMaximaHoras,
    ) {
    }
}

final class Decision
{
    public const APROBADO = 'APROBADO';
    public const RECHAZADO = 'RECHAZADO';

    /**
     * @param array<string, bool> $reglas regla → se cumple
     * @param list<string>        $causas
     */
    public function __construct(
        public readonly string $valor,
        public readonly array $reglas,
        public readonly array $causas,
        public readonly string $ejecucion,
        public readonly string $digest,
        public readonly string $politica,
    ) {
    }
}

final class ReglaRechazada extends \DomainException
{
    public function __construct(public readonly string $codigo)
    {
        parent::__construct($codigo);
    }
}
