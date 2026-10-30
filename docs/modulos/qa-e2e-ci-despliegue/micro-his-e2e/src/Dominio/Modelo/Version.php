<?php

declare(strict_types=1);

namespace E2e\Dominio\Modelo;

use E2e\Dominio\Excepciones\ReglaRechazada;

/**
 * Una versión candidata: semver + commit + DIGEST del artefacto construido una sola vez (R-03).
 * Su plan de pruebas incluye todos los críticos (R-02) y se congela al iniciar la primera ejecución.
 */
final class Version
{
    public const CANDIDATA = 'CANDIDATA';
    public const APROBADA = 'APROBADA';
    public const RECHAZADA = 'RECHAZADA';

    /**
     * @param list<string>       $modulos módulos del HIS que el cambio afecta
     * @param array<string, bool>|null $plan escenario => crítico
     */
    public function __construct(
        public readonly string $tenant,
        public readonly string $numero,
        public readonly string $commit,
        public readonly string $digest,
        public readonly array $modulos,
        public readonly string $registradaPor,
        private string $estado = self::CANDIDATA,
        private ?array $plan = null,
        private bool $planCongelado = false,
    ) {
    }

    /** @param list<mixed> $modulos */
    public static function registrar(string $tenant, string $numero, string $commit, string $digest, array $modulos, string $usuario): self
    {
        $campos = [];
        if (!preg_match('/^\d+\.\d+\.\d+(-[0-9A-Za-z.]+)?$/', $numero)) {
            $campos[] = 'numero';
        }
        if (!preg_match('/^[0-9a-f]{7,40}$/', $commit)) {
            $campos[] = 'commit';
        }
        if (!preg_match('/^sha256:[0-9a-f]{64}$/', $digest)) {
            $campos[] = 'digest';
        }
        if ($modulos === [] || array_filter($modulos, static fn ($m): bool => !is_string($m) || !preg_match('/^[a-z][a-z0-9-]{1,40}$/', $m)) !== []) {
            $campos[] = 'modulos';
        }
        if ($campos !== []) {
            throw new ReglaRechazada('DATOS_INVALIDOS', $campos);
        }

        return new self($tenant, $numero, $commit, $digest, array_values(array_unique($modulos)), $usuario);
    }

    public function estado(): string
    {
        return $this->estado;
    }

    /** @return array<string, bool>|null */
    public function plan(): ?array
    {
        return $this->plan;
    }

    public function planCongelado(): bool
    {
        return $this->planCongelado;
    }

    /**
     * R-02: el plan es la propuesta MÁS todos los críticos (que no se pueden quitar) y los escenarios de los módulos afectados.
     * @param list<EscenarioE2E> $catalogo escenarios activos del tenant
     * @param list<mixed>        $propuesta ids que el Ingeniero QA quiere correr
     */
    public function planificar(array $catalogo, array $propuesta): void
    {
        if ($this->planCongelado) {
            throw new ReglaRechazada('PLAN_CONGELADO');
        }
        $porId = [];
        foreach ($catalogo as $e) {
            $porId[$e->id] = $e;
        }
        $inexistentes = array_values(array_filter($propuesta, static fn ($id): bool => !is_string($id) || !isset($porId[$id])));
        if ($inexistentes !== []) {
            throw new ReglaRechazada('ESCENARIO_INEXISTENTE', array_map('strval', $inexistentes));
        }
        $obligatorios = array_keys(array_filter($porId, fn (EscenarioE2E $e): bool => $e->critico || in_array($e->modulo, $this->modulos, true)));
        $faltan = array_values(array_diff($obligatorios, $propuesta));
        sort($faltan);
        if ($faltan !== []) {
            throw new ReglaRechazada('PLAN_INCOMPLETO', $faltan);
        }
        $plan = [];
        foreach (array_unique($propuesta) as $id) {
            $plan[$id] = $porId[$id]->critico;
        }
        ksort($plan);
        $this->plan = $plan;
    }

    /** Se llama al iniciar la primera ejecución: desde ahí nadie quita un escenario que falla (R-02). */
    public function congelarPlan(): void
    {
        if ($this->plan === null) {
            throw new ReglaRechazada('VERSION_SIN_PLAN');
        }
        $this->planCongelado = true;
    }

    public function aplicar(Decision $decision): void
    {
        $this->estado = $decision->valor === Decision::APROBADO ? self::APROBADA : self::RECHAZADA;
    }
}
