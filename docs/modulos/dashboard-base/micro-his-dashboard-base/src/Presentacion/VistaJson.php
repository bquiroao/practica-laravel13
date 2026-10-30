<?php
declare(strict_types=1);

namespace MicroHis\DashboardBase\Presentacion;

use MicroHis\DashboardBase\Dominio\{EstadoTarjeta, ReglaDeDominio};

/** Vista (MVC): convierte el resultado del caso de uso en la respuesta HTTP. Es la única dueña de los códigos y textos al usuario. */
final class VistaJson
{
    private const HTTP = ['E1' => 401, 'E4' => 403, 'E6' => 403, 'NF' => 404];

    public function inicio(array $resumen): array { return ['status' => 200] + $resumen; }
    /** Una tarjeta en «error» responde 503 E5 con su texto amable; las demás, 200 (R-03, R-09). */
    public function tarjeta(array $t): array { return ($t['estado'] === EstadoTarjeta::Error->value ? ['status' => 503, 'error' => 'E5'] : ['status' => 200]) + $t; }
    public function navegacion(array $destino): array { return ['status' => 200] + $destino; }
    public function rechazo(ReglaDeDominio $e): array { return ['status' => self::HTTP[$e->codigo], 'error' => $e->codigo, 'mensaje' => $e->getMessage()]; }
    public function rutaDesconocida(): array { return ['status' => 404]; }

    /** Envía la respuesta al cliente: código HTTP y cuerpo JSON. Solo la usa el front controller. */
    public static function emitir(array $respuesta): void
    {
        http_response_code($respuesta['status']);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array_diff_key($respuesta, ['status' => true]) ?: new \stdClass(), JSON_UNESCAPED_UNICODE);
    }
}
