<?php

declare(strict_types=1);

namespace Qa\Dominio\Modelo;

use Qa\Dominio\Evidencia\Clasificacion;
use Qa\Dominio\Evidencia\EvidenciaInvalida;
use Qa\Dominio\Evidencia\EvidenciaVerificable;
use Qa\Dominio\Evidencia\FabricaEvidencias;
use Qa\Dominio\Evidencia\ReferenciaExterna;
use Qa\Dominio\Excepciones\ReglaRechazada;

/**
 * Semana 7 (refactor): lo que el tester pide registrar, YA validado contra las reglas del resultado (R-05, R-06, R-10).
 * Antes estas decisiones vivían en el caso de uso RegistrarResultado (Dq = 8 fuera del Dominio); aquí se toman en el
 * mismo orden, así que la salida observable no cambia (CAR-01). Inmutable y sin dependencias de infraestructura.
 */
final readonly class SolicitudResultado
{
    public const TAMANO_MAXIMO = 5 * 1024 * 1024;

    /**
     * @param list<EvidenciaVerificable> $evidencias
     * @param list<ReferenciaExterna>    $referencias
     * @param array<string, mixed>|null  $defecto     lo que el tester propone, si es una FALLA nueva
     */
    private function __construct(
        public Resultado $resultado,
        public array $evidencias,
        public array $referencias,
        public ?array $defecto,
        public ?string $motivo,
    ) {
    }

    /**
     * @param array<string, mixed> $datos
     * @throws ReglaRechazada (incluye EVIDENCIA_DEMASIADO_GRANDE y los códigos de EvidenciaInvalida)
     */
    public static function desde(array $datos): self
    {
        $resultado = Resultado::tryFrom((string) ($datos['resultado'] ?? '')) ?? throw new ReglaRechazada('DATOS_INVALIDOS', ['resultado']);
        $motivo = trim((string) ($datos['motivo'] ?? '')) === '' ? null : trim((string) $datos['motivo']);
        if ($resultado === Resultado::NO_APLICA && $motivo === null) {
            throw new ReglaRechazada('MOTIVO_REQUERIDO');   // R-10
        }
        try {
            $evidencias = self::evidencias($datos['evidencias'] ?? []);
            if ($resultado->exigeEvidencia() && $evidencias === []) {
                throw new ReglaRechazada('EVIDENCIA_REQUERIDA');   // R-05: una referencia externa no cuenta
            }
            $referencias = array_map(static fn (array $r): ReferenciaExterna => new ReferenciaExterna((string) ($r['url'] ?? ''), (string) ($r['descripcion'] ?? '')),
                array_values(array_filter(is_array($datos['referencias'] ?? null) ? $datos['referencias'] : [], 'is_array')));
        } catch (EvidenciaInvalida $e) {
            throw new ReglaRechazada($e->codigo);
        }

        return new self($resultado, $evidencias, $referencias, is_array($datos['defecto'] ?? null) ? $datos['defecto'] : null, $motivo);
    }

    /**
     * R-06: una FALLA que no verifica un defecto existente reporta uno nuevo. R-08: si verifica uno, la re-ejecución lo
     * cierra (PASA) o lo reabre (FALLA). Devuelve el defecto NUEVO, o null.
     */
    public function resolverDefecto(?Defecto $verificado, string $tenant, int $campanaId, string $casoId, string $tester): ?Defecto
    {
        $verificado?->verificar($this->resultado, $tester);
        if ($this->resultado !== Resultado::FALLA || $verificado !== null) {
            return null;
        }
        $d = $this->defecto ?? throw new ReglaRechazada('DEFECTO_REQUERIDO');
        $propuesta = Severidad::tryFrom((string) ($d['severidad'] ?? '')) ?? throw new ReglaRechazada('DATOS_INVALIDOS', ['defecto.severidad']);

        return Defecto::reportar($tenant, $campanaId, $casoId, (string) ($d['titulo'] ?? ''), is_array($d['pasos'] ?? null) ? $d['pasos'] : [],
            $propuesta, $this->evidencias !== [], $tester);
    }

    /** @return list<EvidenciaVerificable> en el orden recibido; el tamaño se revisa ANTES de construir cada una */
    private static function evidencias(mixed $entrada): array
    {
        $evidencias = [];
        foreach (is_array($entrada) ? $entrada : [] as $e) {
            $bytes = is_array($e) && is_string($e['bytes'] ?? null) ? $e['bytes'] : '';
            if (strlen($bytes) > self::TAMANO_MAXIMO) {
                throw new ReglaRechazada('EVIDENCIA_DEMASIADO_GRANDE');
            }
            $clasificacion = Clasificacion::tryFrom((string) ($e['clasificacion'] ?? 'GENERAL')) ?? throw new ReglaRechazada('DATOS_INVALIDOS', ['evidencias.clasificacion']);
            $evidencias[] = FabricaEvidencias::crear((string) ($e['tipo'] ?? ''), $bytes, $clasificacion);
        }

        return $evidencias;
    }
}
