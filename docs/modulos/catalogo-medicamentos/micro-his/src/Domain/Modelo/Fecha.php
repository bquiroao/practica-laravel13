<?php
declare(strict_types=1);

namespace Catalogo\Domain\Modelo;

use Catalogo\Domain\Errores\ErrorValidacion;

/** Fechas de calendario (sin hora) en formato AAAA-MM-DD dentro del rango de RN-05. */
final class Fecha
{
    public static function desdeTexto(string $campo, mixed $valor): \DateTimeImmutable
    {
        if (!is_string($valor) || preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $valor, $p) !== 1
            || !checkdate((int) $p[2], (int) $p[3], (int) $p[1])) {
            throw new ErrorValidacion([$campo => 'Use una fecha válida con formato AAAA-MM-DD']);
        }
        if ($valor < Limites::FECHA_MIN || $valor > Limites::FECHA_MAX) {
            throw new ErrorValidacion([$campo => 'La fecha debe estar entre ' . Limites::FECHA_MIN . ' y ' . Limites::FECHA_MAX]);
        }
        return new \DateTimeImmutable($valor . ' 00:00:00', new \DateTimeZone('UTC'));
    }

    public static function texto(?\DateTimeImmutable $fecha): ?string
    {
        return $fecha?->format('Y-m-d');
    }

    /** Normaliza cualquier fecha (por ejemplo la del reloj) al día calendario en UTC. */
    public static function dia(\DateTimeImmutable $fecha): \DateTimeImmutable
    {
        return new \DateTimeImmutable($fecha->format('Y-m-d') . ' 00:00:00', new \DateTimeZone('UTC'));
    }
}
