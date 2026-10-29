<?php

declare(strict_types=1);

namespace E2e\Dominio\Modelo;

use E2e\Dominio\Excepciones\ReglaRechazada;

/** Lo que pasó con un escenario en una ejecución. Inmutable: no se reemplaza (R-04). */
final readonly class ResultadoEscenario
{
    /** @param list<Evidencia> $evidencias */
    public function __construct(
        public ?int $id,
        public string $escenario,
        public bool $critico,
        public Resultado $resultado,
        public int $intentos,
        public array $evidencias,
        public string $clave,
        public string $huella,
        public \DateTimeImmutable $reportadoEn,
    ) {
    }

    /**
     * El pipeline reporta PASA, FALLA u OMITIDO con sus intentos; el dominio decide si fue INESTABLE (R-06):
     * un PASA que necesitó más de un intento NO es un PASA.
     * @param list<Evidencia> $evidencias
     */
    public static function desde(string $escenario, bool $critico, string $reportado, int $intentos, array $evidencias, string $clave,
        string $huella, \DateTimeImmutable $ahora): self
    {
        $resultado = Resultado::tryFrom($reportado);
        if ($resultado === null || $resultado === Resultado::INESTABLE) {
            throw new ReglaRechazada('DATOS_INVALIDOS', ['resultado']);
        }
        if ($intentos < ($resultado === Resultado::OMITIDO ? 0 : 1) || $intentos > 5) {
            throw new ReglaRechazada('DATOS_INVALIDOS', ['intentos']);
        }
        if ($resultado === Resultado::PASA && $intentos > 1) {
            $resultado = Resultado::INESTABLE;
        }

        return new self(null, $escenario, $critico, $resultado, $intentos, $evidencias, $clave, $huella, $ahora);
    }

    public function conId(int $id): self
    {
        return new self($id, $this->escenario, $this->critico, $this->resultado, $this->intentos, $this->evidencias, $this->clave, $this->huella,
            $this->reportadoEn);
    }
}
