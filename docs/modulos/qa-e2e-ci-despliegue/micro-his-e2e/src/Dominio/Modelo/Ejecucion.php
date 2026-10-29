<?php

declare(strict_types=1);

namespace E2e\Dominio\Modelo;

use E2e\Dominio\Excepciones\ReglaRechazada;

/**
 * Una corrida del pipeline sobre UN digest (R-03). Copia el plan congelado de la versión; solo EN_CURSO admite
 * resultados, uno por escenario y solo del plan (R-04).
 */
final class Ejecucion
{
    public const EN_CURSO = 'EN_CURSO';
    public const COMPLETADA = 'COMPLETADA';
    public const ABORTADA = 'ABORTADA';

    /**
     * @param array<string, bool>      $plan escenario => crítico
     * @param list<ResultadoEscenario> $resultados
     */
    public function __construct(
        public readonly ?int $id,
        public readonly string $tenant,
        public readonly string $version,
        public readonly string $digest,
        public readonly string $entorno,
        public readonly array $plan,
        public readonly \DateTimeImmutable $iniciadaEn,
        private string $estado = self::EN_CURSO,
        private ?\DateTimeImmutable $terminadaEn = null,
        private ?string $motivo = null,
        private array $resultados = [],
    ) {
    }

    /** R-03: se prueba el artefacto de la versión, no otro. */
    public static function iniciar(Version $version, string $digest, string $entorno, \DateTimeImmutable $ahora): self
    {
        if (!preg_match('/^[a-z][a-z0-9-]{1,30}$/', $entorno)) {
            throw new ReglaRechazada('DATOS_INVALIDOS', ['entorno']);
        }
        if ($digest !== $version->digest) {
            throw new ReglaRechazada('DIGEST_DISTINTO');
        }
        $version->congelarPlan();

        return new self(null, $version->tenant, $version->numero, $digest, $entorno, (array) $version->plan(), $ahora);
    }

    public function conId(int $id): self
    {
        return new self($id, $this->tenant, $this->version, $this->digest, $this->entorno, $this->plan, $this->iniciadaEn, $this->estado,
            $this->terminadaEn, $this->motivo, $this->resultados);
    }

    public function estado(): string
    {
        return $this->estado;
    }

    public function terminadaEn(): ?\DateTimeImmutable
    {
        return $this->terminadaEn;
    }

    public function motivo(): ?string
    {
        return $this->motivo;
    }

    /** @return list<ResultadoEscenario> */
    public function resultados(): array
    {
        return $this->resultados;
    }

    public function critico(string $escenario): bool
    {
        if (!array_key_exists($escenario, $this->plan)) {
            throw new ReglaRechazada('ESCENARIO_FUERA_DEL_PLAN', [$escenario]);
        }

        return $this->plan[$escenario];
    }

    public function agregar(ResultadoEscenario $r): void
    {
        if ($this->estado !== self::EN_CURSO) {
            throw new ReglaRechazada('EJECUCION_NO_ADMITE_RESULTADOS');
        }
        $this->critico($r->escenario);
        foreach ($this->resultados as $previo) {
            if ($previo->escenario === $r->escenario) {
                throw new ReglaRechazada('RESULTADO_YA_REPORTADO', [$r->escenario]);   // los reintentos van en «intentos»
            }
        }
        $this->resultados[] = $r;
    }

    public function completar(\DateTimeImmutable $ahora): void
    {
        $this->exigirEnCurso();
        $faltan = array_values(array_diff(array_keys($this->plan), array_map(static fn (ResultadoEscenario $r): string => $r->escenario, $this->resultados)));
        if ($faltan !== []) {
            throw new ReglaRechazada('ESCENARIOS_SIN_RESULTADO', $faltan);
        }
        $this->estado = self::COMPLETADA;
        $this->terminadaEn = $ahora;
    }

    /** El runner se cayó o venció el tiempo: la ejecución no sirve para el gate (E3). */
    public function abortar(string $motivo, \DateTimeImmutable $ahora): void
    {
        $this->exigirEnCurso();
        if (trim($motivo) === '') {
            throw new ReglaRechazada('MOTIVO_REQUERIDO');
        }
        $this->estado = self::ABORTADA;
        $this->terminadaEn = $ahora;
        $this->motivo = trim($motivo);
    }

    private function exigirEnCurso(): void
    {
        if ($this->estado !== self::EN_CURSO) {
            throw new ReglaRechazada('ESTADO_NO_PERMITE_CERRAR');
        }
    }
}
