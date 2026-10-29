<?php

declare(strict_types=1);

namespace Qa\Dominio\Modelo;

use Qa\Dominio\Excepciones\ReglaRechazada;

/**
 * Defecto con su ciclo de vida (R-07, R-08). No hay ningún método que lo cierre sin un resultado de re-ejecución:
 * el desarrollador solo puede marcarlo CORREGIDO. Cada transición agrega una línea al historial (que no se edita).
 */
final class Defecto
{
    /** @var list<string> líneas nuevas del historial, que el repositorio agrega al guardar */
    private array $historialNuevo = [];

    /** @param list<string> $pasos */
    public function __construct(
        public readonly ?int $id,
        public readonly string $tenant,
        public readonly int $campanaId,
        public readonly string $casoId,
        public readonly string $titulo,
        public readonly array $pasos,
        public readonly Severidad $propuesta,
        public readonly bool $conEvidencia,
        public readonly string $reportadoPor,
        private EstadoDefecto $estado = EstadoDefecto::NUEVO,
        private ?Severidad $severidad = null,
        private ?string $prioridad = null,
    ) {
    }

    /** @param list<mixed> $pasos */
    public static function reportar(string $tenant, int $campanaId, string $casoId, string $titulo, array $pasos, Severidad $propuesta,
        bool $conEvidencia, string $tester): self
    {
        $campos = [];
        if (trim($titulo) === '') {
            $campos[] = 'defecto.titulo';
        }
        if (array_filter($pasos, static fn ($p): bool => !is_string($p) || trim($p) === '') !== []) {
            $campos[] = 'defecto.pasos';
        }
        if ($campos !== []) {
            throw new ReglaRechazada('DATOS_INVALIDOS', $campos);
        }

        return new self(null, $tenant, $campanaId, $casoId, trim($titulo), array_values($pasos), $propuesta, $conEvidencia, $tester);
    }

    public function estado(): EstadoDefecto
    {
        return $this->estado;
    }

    public function severidad(): ?Severidad
    {
        return $this->severidad;
    }

    public function prioridad(): ?string
    {
        return $this->prioridad;
    }

    /** @return list<string> */
    public function historialNuevo(): array
    {
        return $this->historialNuevo;
    }

    public function conId(int $id): self
    {
        $d = new self($id, $this->tenant, $this->campanaId, $this->casoId, $this->titulo, $this->pasos, $this->propuesta, $this->conEvidencia,
            $this->reportadoPor, $this->estado, $this->severidad, $this->prioridad);
        $d->historialNuevo = $this->historialNuevo;

        return $d;
    }

    /** R-07: el Líder QA confirma o cambia la severidad propuesta (con motivo); CRÍTICA exige pasos y evidencia. */
    public function clasificar(Severidad $severidad, string $prioridad, string $lider, ?string $motivo): void
    {
        if ($this->estado !== EstadoDefecto::NUEVO && $this->estado !== EstadoDefecto::ABIERTO && $this->estado !== EstadoDefecto::REABIERTO) {
            throw new ReglaRechazada('ESTADO_NO_PERMITE_CLASIFICAR');
        }
        if (!in_array($prioridad, CasoPrueba::PRIORIDADES, true)) {
            throw new ReglaRechazada('DATOS_INVALIDOS', ['prioridad']);
        }
        if ($severidad === Severidad::CRITICA && ($this->pasos === [] || !$this->conEvidencia)) {
            throw new ReglaRechazada('DEFECTO_INCOMPLETO');
        }
        if ($severidad !== $this->propuesta && trim((string) $motivo) === '') {
            throw new ReglaRechazada('MOTIVO_REQUERIDO');
        }
        $this->severidad = $severidad;
        $this->prioridad = $prioridad;
        if ($this->estado === EstadoDefecto::NUEVO) {
            $this->estado = EstadoDefecto::ABIERTO;
        }
        $this->historialNuevo[] = "CLASIFICADO {$severidad->value} {$prioridad} por {$lider}" . (trim((string) $motivo) === '' ? '' : ': ' . trim((string) $motivo));
    }

    /** R-08: el desarrollador marca CORREGIDO un defecto ABIERTO o REABIERTO; nunca lo cierra. */
    public function marcarCorregido(string $build, string $desarrollador): void
    {
        if ($this->estado !== EstadoDefecto::ABIERTO && $this->estado !== EstadoDefecto::REABIERTO) {
            throw new ReglaRechazada('ESTADO_NO_PERMITE_CORREGIR');
        }
        if (!preg_match(Campana::FORMATO_BUILD, $build)) {
            throw new ReglaRechazada('DATOS_INVALIDOS', ['build']);
        }
        $this->estado = EstadoDefecto::CORREGIDO;
        $this->historialNuevo[] = "CORREGIDO en {$build} por {$desarrollador}";
    }

    /** R-08: solo una re-ejecución lo cierra (PASA) o lo reabre (FALLA). */
    public function verificar(Resultado $resultado, string $tester): void
    {
        if ($this->estado !== EstadoDefecto::CORREGIDO) {
            throw new ReglaRechazada('ESTADO_NO_PERMITE_VERIFICAR');
        }
        $this->estado = match ($resultado) {
            Resultado::PASA => EstadoDefecto::CERRADO,
            Resultado::FALLA => EstadoDefecto::REABIERTO,
            default => throw new ReglaRechazada('RESULTADO_NO_VERIFICA'),
        };
        $this->historialNuevo[] = "{$this->estado->value} tras re-ejecución de {$tester}";
    }

    /**
     * RT-01 (semana 13): mientras el Líder QA no lo clasifica, el defecto cuenta con la severidad que PROPUSO el tester.
     * Antes contaba solo la clasificada, y un CRÍTICO propuesto y sin clasificar no bloqueaba el GO.
     */
    public function severidadEfectiva(): Severidad
    {
        return $this->severidad ?? $this->propuesta;
    }

    public function criticoAbierto(): bool
    {
        return $this->severidadEfectiva() === Severidad::CRITICA && $this->estado !== EstadoDefecto::CERRADO;
    }

    /**
     * R-09 y R-15 en UN solo lugar: lo usan la trazabilidad (bloquea_go) y los repositorios al contar para el cierre.
     * @param list<string> $criticosDeMatriz casos críticos en la matriz para las funcionalidades de la campaña
     */
    public function bloqueaGo(array $criticosDeMatriz): bool
    {
        return $this->criticoAbierto() || ($this->severidadEfectiva() === Severidad::MAYOR && $this->estado !== EstadoDefecto::CERRADO
            && in_array($this->casoId, $criticosDeMatriz, true));
    }
}
