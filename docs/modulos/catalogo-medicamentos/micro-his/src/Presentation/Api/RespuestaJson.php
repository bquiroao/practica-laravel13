<?php
declare(strict_types=1);

namespace Catalogo\Presentation\Api;

use Catalogo\Presentation\Web\Respuesta;

/** Respuestas JSON con un único formato de error para toda la API. */
final class RespuestaJson
{
    /** @param array<string, mixed>|null $meta */
    public static function datos(int $estado, mixed $datos, array $cabeceras = [], ?array $meta = null): Respuesta
    {
        $cuerpo = $meta === null ? ['datos' => $datos] : ['datos' => $datos, 'meta' => $meta];
        return new Respuesta($estado, self::json($cuerpo), $cabeceras + ['Content-Type' => 'application/json; charset=UTF-8']);
    }

    /** @param array<string, string> $detalles */
    public static function error(int $estado, string $codigo, string $mensaje, array $detalles = [], string $requestId = ''): Respuesta
    {
        $cuerpo = ['error' => ['codigo' => $codigo, 'mensaje' => $mensaje, 'detalles' => (object) $detalles, 'request_id' => $requestId]];
        return new Respuesta($estado, self::json($cuerpo), ['Content-Type' => 'application/json; charset=UTF-8']);
    }

    public static function sinContenido(): Respuesta
    {
        return new Respuesta(204, '', []);
    }

    private static function json(mixed $valor): string
    {
        return json_encode($valor, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
