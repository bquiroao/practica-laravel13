<?php

declare(strict_types=1);

namespace Auditoria\Dominio;

/**
 * Un movimiento de la bitácora. Inmutable: no tiene métodos que lo modifiquen (R-02);
 * sellado() devuelve OTRA instancia con su posición en la cadena.
 */
final class Movimiento
{
    /** @param array<string, mixed> $detalle */
    private function __construct(
        public readonly int $id,
        public readonly string $emisor,
        public readonly string $eventId,
        public readonly ?string $hospital,
        public readonly string $usuario,
        public readonly string $modulo,
        public readonly string $accion,
        public readonly \DateTimeImmutable $ocurridoEn,
        public readonly string $resultado,
        public readonly ?string $recurso,
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

        return new self(0, $s->emisor, $d['event_id'], $d['hospital'] ?? null, $d['usuario'], $d['modulo'], $d['accion'],
            $ocurrido->setTimezone(new \DateTimeZone('UTC')), $d['resultado'], $d['recurso'] ?? null, $d['detalle'] ?? []);
    }

    /** Movimiento que el propio módulo genera (p. ej. AUDITORIA_CONSULTADA). */
    public static function delSistema(string $eventId, ?string $hospital, string $usuario, string $accion,
        \DateTimeImmutable $ocurridoEn, array $detalle): self
    {
        return new self(0, 'auditoria', $eventId, $hospital, $usuario, 'auditoria', $accion,
            $ocurridoEn->setTimezone(new \DateTimeZone('UTC')), 'exito', null, $detalle);
    }

    public function sellado(int $id, int $secuencia, string $sello): self
    {
        return new self($id, $this->emisor, $this->eventId, $this->hospital, $this->usuario, $this->modulo, $this->accion,
            $this->ocurridoEn, $this->resultado, $this->recurso, $this->detalle, $secuencia, $sello);
    }

    /** Lo que se sella y lo que se compara en un reintento: todo menos la posición en la cadena. */
    public function contenido(): array
    {
        return [
            'emisor' => $this->emisor, 'event_id' => $this->eventId, 'hospital' => $this->hospital, 'usuario' => $this->usuario,
            'modulo' => $this->modulo, 'accion' => $this->accion, 'ocurrido_en' => $this->ocurridoEn->format('Y-m-d\TH:i:s\Z'),
            'resultado' => $this->resultado, 'recurso' => $this->recurso, 'detalle' => $this->detalle,
        ];
    }

    public function mismoContenidoQue(self $otro): bool
    {
        return json_encode($this->contenido()) === json_encode($otro->contenido());
    }
}
