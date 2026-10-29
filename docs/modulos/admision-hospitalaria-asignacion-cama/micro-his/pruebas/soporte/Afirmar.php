<?php
declare(strict_types=1);

namespace Pruebas;

final class FallaPrueba extends \RuntimeException
{
}

final class PruebaOmitida extends \RuntimeException
{
}

final class Afirmar
{
    public static function igual(mixed $esperado, mixed $real, string $que = ''): void
    {
        if ($esperado !== $real) {
            throw new FallaPrueba(trim($que . ' esperado ' . json_encode($esperado, JSON_UNESCAPED_UNICODE) . ', obtenido ' . json_encode($real, JSON_UNESCAPED_UNICODE)));
        }
    }

    public static function verdadero(bool $condicion, string $que): void
    {
        if (!$condicion) {
            throw new FallaPrueba($que);
        }
    }

    /** Ejecuta $accion y exige que lance $clase. Devuelve la excepción para revisarla. */
    public static function lanza(string $clase, callable $accion): \Throwable
    {
        try {
            $accion();
        } catch (\Throwable $e) {
            if ($e instanceof $clase) {
                return $e;
            }
            throw new FallaPrueba("se esperaba {$clase} y se obtuvo " . get_class($e) . ': ' . $e->getMessage());
        }
        throw new FallaPrueba("se esperaba {$clase} y no se lanzó nada");
    }
}
