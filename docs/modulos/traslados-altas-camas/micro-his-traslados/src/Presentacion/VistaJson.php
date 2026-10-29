<?php
declare(strict_types=1);

// Vista: da forma a la respuesta (estado HTTP y JSON). Un solo lugar para el mapa código → estado.
namespace Traslados\Presentacion;

final class VistaJson {
    private const ESTADOS = ['json_invalido' => 400, 'sin_sesion' => 401, 'acceso_denegado' => 403, 'ruta_no_encontrada' => 404, 'metodo_no_permitido' => 405,
                             'episodio_no_disponible' => 409, 'orden_requerida' => 409, 'sin_cama' => 409, 'cama_no_disponible' => 409,
                             'cama_no_cumple_requisito' => 409, 'alta_invalida' => 422, 'error_persistencia' => 500];

    public static function creada(array $resultado): array { return [201, $resultado]; }

    /** Códigos sin estado propio → 422. */
    public static function error(string $codigo, string $mensaje = '', array $campos = []): array {
        return [self::ESTADOS[$codigo] ?? 422, array_filter(['codigo' => $codigo, 'mensaje' => $mensaje, 'campos' => $campos], fn ($v) => $v !== '' && $v !== [])];
    }

    public static function enviar(array $respuesta): void {
        http_response_code($respuesta[0]);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($respuesta[1], JSON_UNESCAPED_UNICODE);
    }
}
