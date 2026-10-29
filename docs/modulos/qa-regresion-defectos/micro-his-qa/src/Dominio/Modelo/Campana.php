<?php

declare(strict_types=1);

namespace Qa\Dominio\Modelo;

use Qa\Dominio\Excepciones\ReglaRechazada;

/**
 * Campaña de regresión (R-03, R-04, R-09). El build, el alcance y la versión de cada caso se congelan al iniciar.
 * Los intentos solo se agregan; el último de cada caso es el que cuenta para la decisión.
 */
final class Campana
{
    /** @var list<Intento> */
    private array $intentos = [];

    /**
     * @param list<string>       $funcionalidades las que el build afecta (para tomar los críticos de la matriz)
     * @param array<string, int> $alcance         caso => versión
     * @param list<Intento>      $intentos
     */
    public function __construct(
        public readonly ?int $id,
        public readonly string $tenant,
        public readonly string $nombre,
        public readonly array $funcionalidades,
        public readonly array $alcance,
        public readonly float $umbral,
        public readonly string $creadaPor,
        private EstadoCampana $estado = EstadoCampana::PLANIFICADA,
        private ?string $build = null,
        private ?Decision $decision = null,
        array $intentos = [],
    ) {
        $this->intentos = $intentos;
    }

    /**
     * @param list<mixed>        $funcionalidades
     * @param array<string, int> $alcance
     */
    public static function planificar(string $tenant, string $nombre, array $funcionalidades, array $alcance, float $umbral, string $lider): self
    {
        $campos = [];
        if (trim($nombre) === '') {
            $campos[] = 'nombre';
        }
        if ($funcionalidades === [] || array_filter($funcionalidades, static fn ($f): bool => !is_string($f) || !preg_match('/^[a-z][a-z0-9_-]{1,40}$/', $f)) !== []) {
            $campos[] = 'funcionalidades';
        }
        if ($alcance === []) {
            $campos[] = 'alcance';
        }
        if ($umbral <= 0.0 || $umbral > 1.0) {
            $campos[] = 'umbral';
        }
        if ($campos !== []) {
            throw new ReglaRechazada('DATOS_INVALIDOS', $campos);
        }

        return new self(null, $tenant, trim($nombre), array_values(array_unique($funcionalidades)), $alcance, $umbral, $lider);
    }

    public function conId(int $id): self
    {
        return new self($id, $this->tenant, $this->nombre, $this->funcionalidades, $this->alcance, $this->umbral, $this->creadaPor,
            $this->estado, $this->build, $this->decision, $this->intentos);
    }

    public function estado(): EstadoCampana
    {
        return $this->estado;
    }

    public function build(): ?string
    {
        return $this->build;
    }

    public function decision(): ?Decision
    {
        return $this->decision;
    }

    /**
     * R-03: build informado, todos los casos críticos que la matriz marca para las funcionalidades afectadas, y casos aprobados.
     * @param list<string>          $criticosAfectados
     * @param array<string, string> $estadoDeCadaVersion caso => estado de la versión del alcance
     */
    public function iniciar(string $build, array $criticosAfectados, array $estadoDeCadaVersion): void
    {
        if ($this->estado !== EstadoCampana::PLANIFICADA) {
            throw new ReglaRechazada('ESTADO_NO_PERMITE_INICIAR');
        }
        if (!preg_match('/^\d+\.\d+\.\d+(\+[0-9a-f]{7,40})?$/', $build)) {
            throw new ReglaRechazada('ALCANCE_INCOMPLETO', ['build']);
        }
        $faltan = array_values(array_diff($criticosAfectados, array_keys($this->alcance)));
        sort($faltan);
        if ($faltan !== []) {
            throw new ReglaRechazada('ALCANCE_INCOMPLETO', $faltan);
        }
        $sinAprobar = array_values(array_filter(array_keys($this->alcance), static fn (string $c): bool => ($estadoDeCadaVersion[$c] ?? null) !== CasoPrueba::APROBADO));
        if ($sinAprobar !== []) {
            throw new ReglaRechazada('CASO_NO_APROBADO', $sinAprobar);
        }
        $this->build = $build;
        $this->estado = EstadoCampana::EN_CURSO;
    }

    /** R-04: solo una campaña EN_CURSO admite resultados, y solo de casos de su alcance. */
    public function admite(string $casoId): bool
    {
        return $this->estado === EstadoCampana::EN_CURSO && isset($this->alcance[$casoId]);
    }

    public function siguienteNumero(string $casoId): int
    {
        return 1 + count(array_filter($this->intentos, static fn (Intento $i): bool => $i->casoId === $casoId));
    }

    public function agregar(Intento $intento): void
    {
        if (!$this->admite($intento->casoId)) {
            throw new ReglaRechazada('CAMPANA_NO_ADMITE_RESULTADOS');
        }
        $this->intentos[] = $intento;
    }

    /** @return list<Intento> */
    public function intentos(): array
    {
        return $this->intentos;
    }

    public function ultimo(string $casoId): ?Intento
    {
        $ultimo = null;
        foreach ($this->intentos as $i) {
            if ($i->casoId === $casoId) {
                $ultimo = $i;
            }
        }

        return $ultimo;
    }

    /**
     * R-09: sin pendientes; GO con 0 críticos abiertos y la tasa del último intento de cada caso ≥ el umbral.
     * R-15 (semana 6): tampoco hay GO con un defecto MAYOR abierto en un caso crítico de la matriz.
     */
    public function cerrar(int $criticosAbiertos, int $mayoresEnCriticos = 0): Decision
    {
        if ($this->estado !== EstadoCampana::EN_CURSO) {
            throw new ReglaRechazada('ESTADO_NO_PERMITE_CERRAR');
        }
        $pendientes = array_values(array_filter(array_keys($this->alcance), fn (string $c): bool => $this->ultimo($c) === null));
        if ($pendientes !== []) {
            throw new ReglaRechazada('CASOS_PENDIENTES', $pendientes);
        }
        $ultimos = array_map(fn (string $c): Intento => $this->ultimo($c), array_keys($this->alcance));
        $aplicables = array_values(array_filter($ultimos, static fn (Intento $i): bool => $i->resultado !== Resultado::NO_APLICA));
        $pasan = count(array_filter($aplicables, static fn (Intento $i): bool => $i->resultado === Resultado::PASA));
        $tasa = $aplicables === [] ? 1.0 : round($pasan / count($aplicables), 4);
        $causas = [];
        if ($criticosAbiertos > 0) {
            $causas[] = $criticosAbiertos === 1 ? '1 defecto crítico abierto' : "$criticosAbiertos defectos críticos abiertos";
        }
        if ($mayoresEnCriticos > 0) {
            $causas[] = $mayoresEnCriticos === 1 ? '1 defecto MAYOR abierto en un caso crítico de la matriz'
                : "$mayoresEnCriticos defectos MAYOR abiertos en casos críticos de la matriz";
        }
        if ($tasa < $this->umbral) {
            $causas[] = sprintf('tasa de aprobados %.1f %% menor que el umbral %.1f %%', 100 * $tasa, 100 * $this->umbral);
        }
        $this->estado = EstadoCampana::CERRADA;

        return $this->decision = new Decision($causas === [] ? Decision::GO : Decision::NO_GO, $tasa, $causas);
    }
}
