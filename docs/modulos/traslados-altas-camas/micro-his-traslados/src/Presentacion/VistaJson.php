<?php
declare(strict_types=1);

// Vista: da forma a la respuesta (estado HTTP y JSON). Un solo lugar para el mapa código → estado.
namespace Traslados\Presentacion;

final class VistaJson {
    private const ESTADOS = ['json_invalido' => 400, 'idempotency_key_invalida' => 400, 'sin_sesion' => 401, 'acceso_denegado' => 403, 'ruta_no_encontrada' => 404, 'metodo_no_permitido' => 405,
                             'episodio_no_disponible' => 409, 'clave_reutilizada' => 409, 'orden_requerida' => 409, 'sin_cama' => 409, 'cama_no_disponible' => 409,
                             'cama_no_cumple_requisito' => 409, 'alta_invalida' => 422, 'error_persistencia' => 500];

    public static function creada(array $resultado): array { return [201, $resultado]; }

    /** Códigos sin estado propio → 422. */
    public static function error(string $codigo, string $mensaje = '', array $campos = []): array {
        return [self::ESTADOS[$codigo] ?? 422, array_filter(['codigo' => $codigo, 'mensaje' => $mensaje, 'campos' => $campos], fn ($v) => $v !== '' && $v !== [])];
    }

    /** Observabilidad (semana 5): una línea JSON por petición, sin ids de episodio, cama ni paciente. */
    public static function registro(Peticion $p, array $respuesta, float $ms): string {
        return json_encode(['id' => $p->idSolicitud, 'metodo' => $p->metodo, 'ruta' => $p->ruta, 'estado' => $respuesta[0], 'codigo' => $respuesta[1]['codigo'] ?? null, 'ms' => round($ms, 2)]);
    }

    public static function enviar(array $respuesta, string $idSolicitud = ''): void {
        http_response_code($respuesta[0]);
        if ($idSolicitud !== '') { header('X-Request-Id: ' . $idSolicitud); }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($respuesta[1], JSON_UNESCAPED_UNICODE);
    }
}
