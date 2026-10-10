<?php

declare(strict_types=1);

namespace Rbac\Presentacion\Http\Acciones;

use Rbac\Aplicacion\CasosDeUso\AsignarRol;
use Rbac\Aplicacion\Comandos\ComandoAsignarRol;
use Rbac\Presentacion\Http\Accion;
use Rbac\Presentacion\Http\Identidad;
use Rbac\Presentacion\Http\Respuesta;
use Rbac\Presentacion\Http\Solicitud;

/** POST /api/v1/roles/asignaciones — valida el formato de la entrada y delega en AsignarRol. */
final class AsignarRolAccion implements Accion
{
    public function __construct(private readonly AsignarRol $asignarRol)
    {
    }

    public function ejecutar(Solicitud $solicitud, Identidad $identidad, array $parametros): Respuesta
    {
        $cuerpo = $solicitud->cuerpo ?? [];
        $usuario = is_string($cuerpo['usuario_id'] ?? null) ? trim($cuerpo['usuario_id']) : '';
        $rol = is_string($cuerpo['rol'] ?? null) ? trim($cuerpo['rol']) : '';
        $desde = self::fecha($cuerpo['valido_desde'] ?? null);
        $hasta = isset($cuerpo['valido_hasta']) ? self::fecha($cuerpo['valido_hasta']) : null;

        $invalidos = [];
        if ($usuario === '') {
            $invalidos[] = 'usuario_id';
        }
        if ($rol === '') {
            $invalidos[] = 'rol';
        }
        if ($desde === null) {
            $invalidos[] = 'valido_desde';
        }
        if (isset($cuerpo['valido_hasta']) && $hasta === null) {
            $invalidos[] = 'valido_hasta';
        }
        if ($invalidos !== []) {
            return Respuesta::json(422, ['codigo' => 'DATOS_INVALIDOS', 'campos' => $invalidos]);
        }

        $resultado = $this->asignarRol->ejecutar(new ComandoAsignarRol(
            $identidad->usuarioId,
            $identidad->tenantToken,
            $solicitud->cabecera('X-Tenant-ID'),
            $usuario,
            $rol,
            $desde,
            $hasta,
            $solicitud->ip,
        ));

        return match ($resultado->codigoHttp) {
            201 => Respuesta::json(201, ['codigo' => $resultado->codigo, 'correlation_id' => $resultado->correlationId]),
            403 => Respuesta::json(403, ['mensaje' => 'Acceso denegado.', 'correlation_id' => $resultado->correlationId]),
            500 => Respuesta::json(500, ['mensaje' => 'No se pudo completar la operación.']),
            default => Respuesta::json($resultado->codigoHttp, ['codigo' => $resultado->codigo]),
        };
    }

    private static function fecha(mixed $valor): ?\DateTimeImmutable
    {
        if (!is_string($valor) || $valor === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable($valor);
        } catch (\Exception) {
            return null;
        }
    }
}
