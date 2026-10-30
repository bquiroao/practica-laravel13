<?php
declare(strict_types=1);

namespace AlertasCriticas\Presentation\Api;

use AlertasCriticas\Domain\Alerta;
use AlertasCriticas\Presentation\Web\Respuesta;

/** Formato JSON único: éxito {"datos": …} y error {"error": {codigo, mensaje, regla, request_id}}. */
final class RespuestaJson
{
    public static function exito(int $estado, array $datos, string $requestId): Respuesta
    {
        return self::crear($estado, ['datos' => $datos], $requestId);
    }

    public static function error(int $estado, string $codigo, string $mensaje, string $requestId, ?string $regla = null): Respuesta
    {
        return self::crear($estado, ['error' => ['codigo' => $codigo, 'mensaje' => $mensaje, 'regla' => $regla,
            'request_id' => $requestId]], $requestId);
    }

    /** Representación pública de una alerta: código de paciente, nunca nombre (RNF-02). */
    public static function alerta(Alerta $a, bool $conHistorial = false): array
    {
        $datos = ['id' => $a->id, 'codigo_paciente' => $a->paciente->valor, 'servicio' => $a->servicio,
            'prioridad' => $a->prioridad->value, 'descripcion' => $a->descripcion, 'estado' => $a->estado()->value,
            'nivel' => $a->nivel(), 'escalamiento_agotado' => $a->escalamientoAgotado(),
            'plazo_desde' => $a->plazoDesde()?->format(DATE_ATOM)];
        if ($conHistorial) {
            $datos['historial'] = array_map(fn ($e) => ['orden' => $e->orden, 'evento' => $e->evento, 'actor' => $e->actor,
                'momento' => $e->momento->format(DATE_ATOM), 'detalle' => $e->detalle], $a->historial());
        }
        return $datos;
    }

    private static function crear(int $estado, array $cuerpo, string $requestId): Respuesta
    {
        return new Respuesta($estado, json_encode($cuerpo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ['Content-Type' => 'application/json; charset=utf-8', 'X-Request-Id' => $requestId]);
    }
}
