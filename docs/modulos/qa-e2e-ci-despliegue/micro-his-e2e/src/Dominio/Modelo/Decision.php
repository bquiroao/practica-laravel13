<?php

declare(strict_types=1);

namespace E2e\Dominio\Modelo;

/** Una evaluación del quality gate. Se agrega; nunca se edita. */
final readonly class Decision
{
    public const APROBADO = 'APROBADO';
    public const RECHAZADO = 'RECHAZADO';

    /**
     * @param array<string, bool> $reglas regla => se cumple
     * @param list<string>        $causas
     */
    public function __construct(
        public string $valor,
        public array $reglas,
        public array $causas,
        public string $tenant,
        public string $version,
        public int $ejecucion,
        public string $digest,
        public string $politica,
        public \DateTimeImmutable $evaluadaEn,
        public string $evaluadaPor,
    ) {
    }
}
