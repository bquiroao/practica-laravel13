<?php

declare(strict_types=1);

namespace Auditoria\Dominio\Modelo;

/**
 * Un movimiento de la bitácora (fila de audit_logs). Inmutable: ningún método lo modifica (R-02);
 * sellado(), conId() y corrigiendo() devuelven OTRA instancia.
 * Semana 6: una CORRECCIÓN es otro movimiento que referencia al original (corrigeA) con su motivo;
 * el original nunca cambia.
 */
final class Movimiento
{
    public const RESULTADOS = ['exito', 'denegado', 'error'];

    /** @param array<string, mixed> $detalle */
    private function __construct(
        public readonly ?int $id,
        public readonly string $emisor,
        public readonly string $eventId,
        public readonly ?string $hospital,
        public readonly string $usuario,
        public readonly string $modulo,
        public readonly string $accion,
        public readonly \DateTimeImmutable $ocurridoEn,
        public readonly string $resultado,
        public readonly ?string $recurso,
        public readonly ?string $correlationId,
        public readonly array $detalle,
        public readonly int $secuencia = 0,
        public readonly string $sello = '',
        public readonly ?int $corrigeA = null,
        public readonly ?string $motivoCorreccion = null,
    ) {
    }

    /** A partir de una solicitud que YA pasó las reglas de registro (la referencia la resuelve el caso de uso). */
    public static function desdeSolicitud(SolicitudRegistro $s): self
    {
        $d = $s->datos;
        $ocurrido = \DateTimeImmutable::createFromFormat(\DATE_ATOM, $d['ocurrido_en']);
        $motivo = isset($d['motivo_correccion']) ? trim((string) $d['motivo_correccion']) : null;

        return new self(null, $s->emisor, $d['event_id'], $d['hospital'] ?? null, $d['usuario'], $d['modulo'], $d['accion'],
            self::utc($ocurrido), $d['resultado'], $d['recurso'] ?? null, $d['correlation_id'] ?? null, $d['detalle'] ?? [],
            motivoCorreccion: $motivo);
    }

    /** Movimiento que genera el propio módulo (p. ej. AUDITORIA_CONSULTADA, R-08). */
    public static function delSistema(string $eventId, ?string $hospital, string $usuario, string $accion,
        \DateTimeImmutable $ocurridoEn, array $detalle): self
    {
        return new self(null, 'auditoria', $eventId, $hospital, $usuario, 'auditoria', $accion, self::utc($ocurridoEn),
            'exito', null, null, $detalle);
    }

    /** Lo usa Persistencia al leer una fila. */
    public static function reconstituir(int $id, string $emisor, string $eventId, ?string $hospital, string $usuario, string $modulo,
        string $accion, \DateTimeImmutable $ocurridoEn, string $resultado, ?string $recurso, ?string $correlationId, array $detalle,
        int $secuencia, string $sello, ?int $corrigeA = null, ?string $motivoCorreccion = null): self
    {
        return new self($id, $emisor, $eventId, $hospital, $usuario, $modulo, $accion, self::utc($ocurridoEn), $resultado, $recurso,
            $correlationId, $detalle, $secuencia, $sello, $corrigeA, $motivoCorreccion);
    }

    public function sellado(int $secuencia, string $sello): self
    {
        return $this->con(secuencia: $secuencia, sello: $sello);
    }

    /** La base asigna el id al insertar. */
    public function conId(int $id): self
    {
        return $this->con(id: $id);
    }

    /** Semana 6: este movimiento corrige al movimiento $idOriginal (ya verificado: mismo emisor y hospital). */
    public function corrigiendo(int $idOriginal): self
    {
        return $this->con(corrigeA: $idOriginal);
    }

    public function esCorreccion(): bool
    {
        return $this->motivoCorreccion !== null;
    }

    /**
     * Lo que se sella y lo que se compara en un reintento: todo menos id, secuencia y sello.
     * La referencia y el motivo de una corrección SÍ se sellan: nadie puede desligarla del original.
     * El detalle va con las claves ORDENADAS: jsonb de PostgreSQL reordena las claves al guardar.
     */
    public function contenido(): array
    {
        return [
            'emisor' => $this->emisor, 'event_id' => $this->eventId, 'hospital' => $this->hospital, 'usuario' => $this->usuario,
            'modulo' => $this->modulo, 'accion' => $this->accion, 'ocurrido_en' => $this->ocurridoEn->format('Y-m-d\TH:i:s\Z'),
            'resultado' => $this->resultado, 'recurso' => $this->recurso, 'correlation_id' => $this->correlationId,
            'detalle' => self::canonico($this->detalle), 'corrige_a' => $this->corrigeA, 'motivo_correccion' => $this->motivoCorreccion,
        ];
    }

    /** Para la idempotencia: la referencia se compara por el motivo, porque el id del original aún no se resolvió. */
    public function mismoContenidoQue(self $otro): bool
    {
        $a = $this->contenido();
        $b = $otro->contenido();
        unset($a['corrige_a'], $b['corrige_a']);

        return json_encode($a) === json_encode($b);
    }

    /** Datos mínimos para una lista de resultados: sin el detalle. */
    public function resumen(): array
    {
        return ['id' => $this->id, 'ocurrido_en' => $this->ocurridoEn->format('Y-m-d\TH:i:s\Z'), 'hospital' => $this->hospital,
            'usuario' => $this->usuario, 'modulo' => $this->modulo, 'accion' => $this->accion, 'resultado' => $this->resultado,
            'recurso' => $this->recurso, 'corrige_a' => $this->corrigeA];
    }

    public function completo(): array
    {
        return ['id' => $this->id] + $this->contenido() + ['secuencia' => $this->secuencia, 'sello' => $this->sello];
    }

    private function con(?int $id = null, ?int $secuencia = null, ?string $sello = null, ?int $corrigeA = null): self
    {
        return new self($id ?? $this->id, $this->emisor, $this->eventId, $this->hospital, $this->usuario, $this->modulo, $this->accion,
            $this->ocurridoEn, $this->resultado, $this->recurso, $this->correlationId, $this->detalle, $secuencia ?? $this->secuencia,
            $sello ?? $this->sello, $corrigeA ?? $this->corrigeA, $this->motivoCorreccion);
    }

    private static function utc(\DateTimeImmutable $fecha): \DateTimeImmutable
    {
        return $fecha->setTimezone(new \DateTimeZone('UTC'));
    }

    private static function canonico(mixed $valor): mixed
    {
        if (!is_array($valor)) {
            return $valor;
        }
        if (!array_is_list($valor)) {
            ksort($valor, SORT_STRING);
        }

        return array_map(self::canonico(...), $valor);
    }
}
