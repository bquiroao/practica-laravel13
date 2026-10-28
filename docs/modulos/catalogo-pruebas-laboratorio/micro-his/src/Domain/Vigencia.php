<?php
declare(strict_types=1);

namespace Lab\Domain;

use DateTimeImmutable;

/** RN-04 y RN-05: período en que la prueba se puede ordenar. */
final class Vigencia
{
    private function __construct(
        public readonly DateTimeImmutable $desde,
        public readonly ?DateTimeImmutable $hasta,
    ) {
    }

    /** @return array<string,string> */
    public static function errores(array $d, DateTimeImmutable $hoy): array
    {
        $e = [];
        $desde = self::fecha($d['vigente_desde'] ?? null);
        $hasta = self::fecha($d['vigente_hasta'] ?? null);
        if ($desde === null) {
            $e['vigente_desde'] = 'La fecha desde es obligatoria (AAAA-MM-DD).';
        } elseif ($desde < $hoy->setTime(0, 0)) {
            $e['vigente_desde'] = 'La fecha desde no puede ser anterior a hoy.';
        }
        if (($d['vigente_hasta'] ?? '') !== '' && $hasta === null) {
            $e['vigente_hasta'] = 'La fecha hasta no es válida (AAAA-MM-DD).';
        } elseif ($desde !== null && $hasta !== null && $hasta < $desde) {
            $e['vigente_hasta'] = 'La fecha hasta debe ser igual o posterior a la fecha desde.';
        }
        return $e;
    }

    public static function desdeDatos(array $d): self
    {
        return new self(self::fecha($d['vigente_desde']), self::fecha($d['vigente_hasta'] ?? null));
    }

    public static function reconstruir(string $desde, ?string $hasta): self
    {
        return new self(self::fecha($desde), self::fecha($hasta));
    }

    public function incluye(DateTimeImmutable $fecha): bool
    {
        $f = $fecha->setTime(0, 0);
        return $this->desde <= $f && ($this->hasta === null || $this->hasta >= $f);
    }

    public function seSuperponeCon(self $otra): bool
    {
        $finEsta = $this->hasta ?? new DateTimeImmutable('9999-12-31');
        $finOtra = $otra->hasta ?? new DateTimeImmutable('9999-12-31');
        return $this->desde <= $finOtra && $otra->desde <= $finEsta;
    }

    public function cerradaEl(DateTimeImmutable $hasta): self
    {
        return new self($this->desde, $hasta);
    }

    public function texto(): string
    {
        return 'desde ' . $this->desde->format('Y-m-d')
            . ($this->hasta ? ' hasta ' . $this->hasta->format('Y-m-d') : ' sin fecha de fin');
    }

    private static function fecha(mixed $v): ?DateTimeImmutable
    {
        if (!is_string($v) || $v === '') {
            return null;
        }
        $f = DateTimeImmutable::createFromFormat('!Y-m-d', $v);
        return ($f && $f->format('Y-m-d') === $v) ? $f : null;
    }
}
