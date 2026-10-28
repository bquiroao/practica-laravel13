<?php

declare(strict_types=1);

// Mini arnés de pruebas sin dependencias externas.

final class PruebaOmitida extends \RuntimeException
{
}

/** @var list<array{grupo: string, id: string, descripcion: string, estado: string, detalle: ?string}> */
$GLOBALS['resultados'] = [];
$GLOBALS['grupoActual'] = '';

function prueba(string $id, string $descripcion, callable $cuerpo): void
{
    try {
        $cuerpo();
        $estado = 'PASA';
        $detalle = null;
    } catch (PruebaOmitida $e) {
        $estado = 'OMITE';
        $detalle = $e->getMessage();
    } catch (\Throwable $e) {
        $estado = 'FALLA';
        $detalle = $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')';
    }

    $GLOBALS['resultados'][] = [
        'grupo' => $GLOBALS['grupoActual'],
        'id' => $id,
        'descripcion' => $descripcion,
        'estado' => $estado,
        'detalle' => $detalle,
    ];
}

function igual(mixed $esperado, mixed $real, string $que): void
{
    if ($esperado !== $real) {
        throw new \RuntimeException(sprintf('%s: esperado %s, obtenido %s', $que, var_export($esperado, true), var_export($real, true)));
    }
}

function afirmar(bool $condicion, string $mensaje): void
{
    if (!$condicion) {
        throw new \RuntimeException($mensaje);
    }
}

/**
 * @template T of \Throwable
 * @param class-string<T> $clase
 * @return T
 */
function lanza(string $clase, callable $cuerpo, string $que): \Throwable
{
    try {
        $cuerpo();
    } catch (\Throwable $e) {
        if ($e instanceof $clase) {
            return $e;
        }
        throw new \RuntimeException(sprintf('%s: se esperaba %s, se lanzó %s (%s)', $que, $clase, $e::class, $e->getMessage()));
    }

    throw new \RuntimeException(sprintf('%s: se esperaba %s y no se lanzó nada', $que, $clase));
}

function omitir(string $razon): never
{
    throw new PruebaOmitida($razon);
}
