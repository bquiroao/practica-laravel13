<?php
declare(strict_types=1);

// Vista: da forma a la respuesta (estado HTTP y JSON). Un solo lugar para el mapa código → estado.
namespace Medicos\Presentacion;

final class VistaJson {
    private const ESTADOS = ['json_invalido' => 400, 'sin_sesion' => 401, 'acceso_denegado' => 403, 'ruta_no_encontrada' => 404, 'medico_no_encontrado' => 404,
        'metodo_no_permitido' => 405, 'usuario_no_disponible' => 409, 'medico_duplicado' => 409, 'especialidad_duplicada' => 409, 'error_persistencia' => 500];
    public static function creada(array $resultado): array { return [201, $resultado]; }
    public static function lista(array $franjas): array { return [200, ['franjas' => $franjas, 'total' => count($franjas)]]; }
    /** Códigos de regla sin estado propio (especialidad_invalida, vigencia_invalida, rango_invalido) → 422. */
    public static function error(string $codigo, string $mensaje = ''): array { return [self::ESTADOS[$codigo] ?? 422, array_filter(['codigo' => $codigo, 'mensaje' => $mensaje])]; }
    public static function enviar(array $r): void { http_response_code($r[0]); header('Content-Type: application/json; charset=utf-8'); echo json_encode($r[1], JSON_UNESCAPED_UNICODE); }
}
