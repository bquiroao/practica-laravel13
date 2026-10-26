<?php

declare(strict_types=1);

namespace Auditoria\Dominio\Modelo;

/**
 * Un movimiento de la bitácora (fila de audit_logs). Inmutable: ningún método lo modifica (R-02);
 * sellado() y conId() devuelven OTRA instancia.
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
    ) {
    }

    /** A partir de una solicitud que YA pasó las reglas de registro. */
    public static function desdeSolicitud(SolicitudRegistro $s): self
    {
        $d = $s->datos;
        $ocurrido = \DateTimeImmutable::createFromFormat(\DATE_ATOM, $d['ocurrido_en']);

        return new self(null, $s->emisor, $d['event_id'], $d['hospital'] ?? null, $d['usuario'], $d['modulo'], $d['accion'],
            self::utc($ocurrido), $d['resultado'], $d['recurso'] ?? null, $d['correlation_id'] ?? null, $d['detalle'] ?? []);
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
        int $secuencia, string $sello): self
    {
        return new self($id, $emisor, $eventId, $hospital, $usuario, $modulo, $accion, self::utc($ocurridoEn), $resultado, $recurso,
            $correlationId, $detalle, $secuencia, $sello);
    }

    public function sellado(int $secuencia, string $sello): self
    {
        return new self($this->id, $this->emisor, $this->eventId, $this->hospital, $this->usuario, $this->modulo, $this->accion,
            $this->ocurridoEn, $this->resultado, $this->recurso, $this->correlationId, $this->detalle, $secuencia, $sello);
    }

    /** La base asigna el id al insertar. */
    public function conId(int $id): self
    {
        return new self($id, $this->emisor, $this->eventId, $this->hospital, $this->usuario, $this->modulo, $this->accion,
            $this->ocurridoEn, $this->resultado, $this->recurso, $this->correlationId, $this->detalle, $this->secuencia, $this->sello);
    }

    /**
     * Lo que se sella y lo que se compara en un reintento: todo menos id, secuencia y sello.
     * El detalle va con las claves ORDENADAS: jsonb de PostgreSQL reordena las claves al guardar,
     * y sin esto el sello recalculado no coincidiría con el original.
     */
    public function contenido(): array
    {
        return [
            'emisor' => $this->emisor, 'event_id' => $this->eventId, 'hospital' => $this->hospital, 'usuario' => $this->usuario,
            'modulo' => $this->modulo, 'accion' => $this->accion, 'ocurrido_en' => $this->ocurridoEn->format('Y-m-d\TH:i:s\Z'),
            'resultado' => $this->resultado, 'recurso' => $this->recurso, 'correlation_id' => $this->correlationId,
            'detalle' => self::canonico($this->detalle),
        ];
    }

    public function mismoContenidoQue(self $otro): bool
    {
        return json_encode($this->contenido()) === json_encode($otro->contenido());
    }

    /** Datos mínimos para una lista de resultados: sin el detalle. */
    public function resumen(): array
    {
        return ['id' => $this->id, 'ocurrido_en' => $this->ocurridoEn->format('Y-m-d\TH:i:s\Z'), 'hospital' => $this->hospital,
            'usuario' => $this->usuario, 'modulo' => $this->modulo, 'accion' => $this->accion, 'resultado' => $this->resultado,
            'recurso' => $this->recurso];
    }

    public function completo(): array
    {
        return ['id' => $this->id] + $this->contenido() + ['secuencia' => $this->secuencia, 'sello' => $this->sello];
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
