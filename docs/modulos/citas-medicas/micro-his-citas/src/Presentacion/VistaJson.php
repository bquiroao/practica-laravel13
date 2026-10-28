<?php
declare(strict_types=1);

namespace MicroHis\Citas\Presentacion;

use MicroHis\Citas\Dominio\ReglaDeDominio;

/** Vista (MVC): convierte el resultado del caso de uso en la respuesta HTTP. Es la única dueña de los códigos y textos al usuario. */
final class VistaJson
{
    private const HTTP = ['E2' => 422, 'E3' => 422, 'E4' => 409, 'E5' => 409, 'E7' => 422, 'NF' => 404];

    public function exito(int $status, array $datos = []): array { return ['status' => $status] + $datos; }
    public function rechazo(ReglaDeDominio $e): array { return ['status' => self::HTTP[$e->codigo], 'error' => $e->codigo, 'mensaje' => $e->getMessage()]; }
    public function noDisponible(): array { return ['status' => 503, 'error' => 'E8', 'mensaje' => 'La agenda no está disponible. Intenta más tarde.']; }
    public function rutaDesconocida(): array { return ['status' => 404]; }

    /** Envía la respuesta al cliente: código HTTP y cuerpo JSON. Solo la usa el front controller. */
    public static function emitir(array $respuesta): void
    {
        http_response_code($respuesta['status']);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array_diff_key($respuesta, ['status' => true]) ?: new \stdClass(), JSON_UNESCAPED_UNICODE);
    }
}
