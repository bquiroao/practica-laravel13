<?php
declare(strict_types=1);

// Vista: da forma a la respuesta (estado, cuerpo y cabeceras). Un solo lugar para el mapa código → estado y para el formato de salida.
namespace Reportes\Presentacion;

final class VistaHttp {
    private const ESTADOS = ['formato_no_soportado' => 400, 'sin_sesion' => 401, 'acceso_denegado' => 403, 'reporte_no_encontrado' => 404, 'exportacion_no_encontrada' => 404,
                             'ruta_no_encontrada' => 404, 'metodo_no_permitido' => 405, 'error_persistencia' => 500];

    public static function json(array $cuerpo): array { return [200, $cuerpo, []]; }

    /** @param array{id: string, hash: string, contenido: string} $e */
    public static function csv(array $e): array {
        return [200, $e['contenido'], ['Content-Type' => 'text/csv; charset=utf-8', 'X-Exportacion-Id' => $e['id'], 'X-Reporte-Sha256' => $e['hash']]];
    }

    /** Errores de dominio sin estado propio (filtro_invalido) → 422. */
    public static function error(string $codigo, string $mensaje = '', ?string $campo = null): array {
        return [self::ESTADOS[$codigo] ?? 422, array_filter(['codigo' => $codigo, 'mensaje' => $mensaje, 'campo' => $campo]), []];
    }

    public static function enviar(array $r): void {
        http_response_code($r[0]);
        foreach ($r[2] + (is_array($r[1]) ? ['Content-Type' => 'application/json; charset=utf-8'] : []) as $nombre => $valor) { header("$nombre: $valor"); }
        echo is_array($r[1]) ? json_encode($r[1], JSON_UNESCAPED_UNICODE) : $r[1];
    }
}
