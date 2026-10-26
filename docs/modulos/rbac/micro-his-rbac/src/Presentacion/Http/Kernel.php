<?php

declare(strict_types=1);

namespace Rbac\Presentacion\Http;

use Rbac\Presentacion\Http\Autenticacion\Autenticador;
use Rbac\Presentacion\Http\Vistas\ErrorVista;

/**
 * Front controller: resuelve la ruta y aplica la cadena
 * tenant (400) → autenticación (401) → autorización (403) → controlador.
 *
 * Observabilidad: toda respuesta lleva X-Request-ID (el recibido si es válido, o uno nuevo)
 * y cada petición produce un registro de acceso estructurado, sin tokens ni cuerpos.
 */
final class Kernel
{
    private const ID_VALIDO = '/^[A-Za-z0-9-]{8,64}$/';

    private readonly \Closure $registrarAcceso;
    private readonly \Closure $generarId;

    /** @param list<Ruta> $rutas */
    public function __construct(
        private readonly array $rutas,
        private readonly Autenticador $autenticador,
        private readonly MiddlewareAutorizacion $autorizacion,
        ?\Closure $registrarAcceso = null,
        ?\Closure $generarId = null,
    ) {
        $this->registrarAcceso = $registrarAcceso ?? static function (array $registro): void {
        };
        $this->generarId = $generarId ?? static fn (): string => bin2hex(random_bytes(16));
    }

    public function manejar(Solicitud $solicitud): Respuesta
    {
        $inicio = hrtime(true);
        $recibido = $solicitud->cabecera('X-Request-ID');
        $requestId = preg_match(self::ID_VALIDO, $recibido) === 1 ? $recibido : ($this->generarId)();

        [$respuesta, $identidad] = $this->procesar($solicitud);

        try {
            ($this->registrarAcceso)([
                'request_id' => $requestId,
                'metodo' => $solicitud->metodo,
                'ruta' => $solicitud->ruta,
                'estado' => $respuesta->estado,
                'duracion_ms' => round((hrtime(true) - $inicio) / 1e6, 3),
                'usuario' => $identidad?->usuarioId,
                'tenant' => $solicitud->cabecera('X-Tenant-ID') ?: null,
                'correlation_id' => is_string($respuesta->cuerpo['correlation_id'] ?? null) ? $respuesta->cuerpo['correlation_id'] : null,
            ]);
        } catch (\Throwable) {
            // Un registro de observabilidad que falla nunca debe tumbar la respuesta.
        }

        return $respuesta->conCabecera('X-Request-ID', $requestId);
    }

    /** @return array{0: Respuesta, 1: ?Identidad} */
    private function procesar(Solicitud $solicitud): array
    {
        $identidad = null;

        try {
            $encontrada = $this->resolver($solicitud);
            if ($encontrada instanceof Respuesta) {
                return [$encontrada, null];
            }
            [$ruta, $parametros] = $encontrada;

            // D-05 / E-05: sin hospital no se evalúan permisos.
            if ($solicitud->cabecera('X-Tenant-ID') === '') {
                return [ErrorVista::sinTenant(), null];
            }

            // D-06 / E-06: sin identidad no hay a quién autorizar.
            $identidad = $this->autenticador->autenticar($solicitud);
            if ($identidad === null) {
                return [ErrorVista::noAutenticado(), null];
            }

            // CU-03 / CU-04: protección de la ruta por su permiso declarado.
            if ($ruta->permiso !== null) {
                $autorizacion = $this->autorizacion->verificar($solicitud, $identidad, $ruta->permiso);
                if (!$autorizacion->permitido) {
                    return [ErrorVista::accesoDenegado($autorizacion->correlationId), $identidad];
                }
            }

            $respuesta = ($ruta->destino)(new Peticion($solicitud, $identidad, $parametros));

            return [$respuesta instanceof Respuesta ? $respuesta : ErrorVista::errorInterno(), $identidad];
        } catch (\Throwable) {
            return [ErrorVista::errorInterno(), $identidad];
        }
    }

    /** @return array{0: Ruta, 1: array<string, string>}|Respuesta */
    private function resolver(Solicitud $solicitud): array|Respuesta
    {
        $metodoNoPermitido = false;
        foreach ($this->rutas as $ruta) {
            $parametros = $ruta->coincide($solicitud->ruta);
            if ($parametros === null) {
                continue;
            }
            if ($ruta->metodo === $solicitud->metodo) {
                return [$ruta, $parametros];
            }
            $metodoNoPermitido = true;
        }

        return $metodoNoPermitido ? ErrorVista::metodoNoPermitido() : ErrorVista::rutaNoEncontrada();
    }
}
