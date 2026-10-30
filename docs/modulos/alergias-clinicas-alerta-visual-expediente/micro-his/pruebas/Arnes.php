<?php
declare(strict_types=1);

/** Afirmaciones mínimas del ejecutor propio. */
final class Arnes
{
    public static function afirmar(bool $condicion, string $mensaje): void
    {
        if (!$condicion) {
            throw new FallaPrueba($mensaje);
        }
    }

    public static function igual(mixed $esperado, mixed $real, string $que = 'valor'): void
    {
        if ($esperado !== $real) {
            throw new FallaPrueba(sprintf('%s: se esperaba %s y se obtuvo %s', $que, var_export($esperado, true), var_export($real, true)));
        }
    }

    /** Ejecuta $accion y exige que lance $clase (y el código de negocio, si se indica). */
    public static function lanza(callable $accion, string $clase, ?string $codigo = null): Throwable
    {
        try {
            $accion();
        } catch (Throwable $e) {
            self::afirmar($e instanceof $clase, 'se esperaba ' . $clase . ' y llegó ' . $e::class . ': ' . $e->getMessage());
            if ($codigo !== null) {
                self::igual($codigo, $e->codigo ?? null, 'código');
            }
            return $e;
        }
        throw new FallaPrueba("se esperaba {$clase} y no se lanzó nada");
    }
}

final class FallaPrueba extends RuntimeException
{
}

/** Prueba que no puede ejecutarse en este entorno (por ejemplo, sin base). */
final class Omitida extends RuntimeException
{
}
