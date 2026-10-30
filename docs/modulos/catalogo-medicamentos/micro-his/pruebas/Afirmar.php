<?php
declare(strict_types=1);

/** Afirmaciones mínimas del ejecutor propio. Cada falla lanza FallaPrueba con un mensaje claro. */
final class FallaPrueba extends RuntimeException
{
}

final class Afirmar
{
    public static function igual(mixed $esperado, mixed $obtenido, string $que = ''): void
    {
        if ($esperado !== $obtenido) {
            throw new FallaPrueba(trim("$que: se esperaba " . var_export($esperado, true) . ' y se obtuvo ' . var_export($obtenido, true), ': '));
        }
    }

    /** Igualdad de valor (==): para objetos de valor como FichaMedicamento. */
    public static function equivalente(object $esperado, object $obtenido, string $que): void
    {
        if ($esperado != $obtenido) {
            throw new FallaPrueba("$que: los objetos no son equivalentes");
        }
    }

    public static function verdadero(bool $condicion, string $que): void
    {
        if (!$condicion) {
            throw new FallaPrueba("No se cumplió: $que");
        }
    }

    /**
     * Ejecuta $accion y exige que lance $clase (y, si se indica, con ese código de dominio).
     * Devuelve la excepción para revisar sus detalles.
     */
    public static function lanza(string $clase, callable $accion, ?string $codigo = null): Throwable
    {
        try {
            $accion();
        } catch (Throwable $e) {
            if (!$e instanceof $clase) {
                throw new FallaPrueba("Se esperaba $clase y se lanzó " . $e::class . ': ' . $e->getMessage());
            }
            if ($codigo !== null && ($e->codigo ?? null) !== $codigo) {
                throw new FallaPrueba("Se esperaba el código $codigo y se obtuvo " . ($e->codigo ?? '(sin código)'));
            }
            return $e;
        }
        throw new FallaPrueba("Se esperaba $clase y no se lanzó nada");
    }
}
