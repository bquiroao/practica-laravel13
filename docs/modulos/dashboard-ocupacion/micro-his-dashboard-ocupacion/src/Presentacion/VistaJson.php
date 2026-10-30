<?php
declare(strict_types=1);

namespace MicroHis\DashboardOcupacion\Presentacion;

use MicroHis\DashboardOcupacion\Dominio\{Corte, ReglaDeDominio};

/** Vista (MVC): convierte el resultado del caso de uso en la respuesta HTTP. Es la única dueña de los códigos y textos al usuario. */
final class VistaJson
{
    public function corteCreado(Corte $c): array { return ['status' => 201, 'hora' => $c->hora, 'salas' => count($c->salas)]; }
    public function consulta(array $resultado): array { return ['status' => 200] + $resultado; }
    public function rechazo(ReglaDeDominio $e): array { return ['status' => 422, 'error' => $e->codigo, 'mensaje' => $e->getMessage()]; }
    public function noDisponible(): array { return ['status' => 503, 'error' => 'E8', 'mensaje' => 'No pudimos leer la ocupación. Se conserva el corte anterior.']; }
    public function rutaDesconocida(): array { return ['status' => 404]; }

    /** Envía la respuesta al cliente: código HTTP y cuerpo JSON. Solo la usa el front controller. */
    public static function emitir(array $respuesta): void
    {
        http_response_code($respuesta['status']);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array_diff_key($respuesta, ['status' => true]) ?: new \stdClass(), JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
    }
}
