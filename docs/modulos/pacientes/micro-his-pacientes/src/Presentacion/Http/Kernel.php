<?php

declare(strict_types=1);

namespace Pacientes\Presentacion\Http;

use Pacientes\Presentacion\Http\Autenticacion\Autenticador;
use Pacientes\Presentacion\Http\Observabilidad\RegistroAcceso;
use Pacientes\Presentacion\Http\Vistas\ErrorVista;

/**
 * Front controller: resuelve la ruta y aplica la cadena
 * hospital (400) → identidad (401) → token del mismo hospital (403) → controlador.
 * Semana 5: toda respuesta lleva X-Request-ID y deja una línea en el registro de acceso.
 */
final class Kernel
{
    /** @param list<Ruta> $rutas */
    public function __construct(
        private readonly array $rutas,
        private readonly Autenticador $autenticador,
        private readonly ?RegistroAcceso $registroAcceso = null,
    ) {
    }

    public function manejar(Solicitud $solicitud): Respuesta
    {
        $inicio = hrtime(true);
        $requestId = self::idDeTraza($solicitud->cabecera('X-Request-ID'));
        $identidad = null;

        $respuesta = $this->procesar($solicitud, $identidad)->conCabecera('X-Request-ID', $requestId);

        try {
            $this->registroAcceso?->registrar([
                'request_id' => $requestId,
                'metodo' => $solicitud->metodo,
                'ruta' => $solicitud->ruta, // solo la ruta: la query puede tener nombres de pacientes
                'estado' => $respuesta->estado,
                'duracion_ms' => round((hrtime(true) - $inicio) / 1e6, 3),
                'actor' => $identidad?->actor,
                'tenant' => $identidad?->hospital,
            ]);
        } catch (\Throwable) {
            // Si el registro falla, la respuesta al usuario no cambia.
        }

        return $respuesta;
    }

    private function procesar(Solicitud $solicitud, ?Identidad &$identidad): Respuesta
    {
        try {
            $encontrada = $this->resolver($solicitud);
            if ($encontrada instanceof Respuesta) {
                return $encontrada;
            }
            [$ruta, $parametros] = $encontrada;

            // D-01 / E-01: sin hospital no se continúa.
            $hospital = $solicitud->cabecera('X-Tenant-ID');
            if ($hospital === '') {
                return ErrorVista::hospitalRequerido();
            }

            $identidad = $this->autenticador->autenticar($solicitud);
            if ($identidad === null) {
                return ErrorVista::noAutenticado();
            }
            if ($identidad->hospital !== $hospital) {
                return ErrorVista::tenantNoCoincide();
            }

            return ($ruta->manejador)($solicitud, $identidad, $parametros);
        } catch (\Throwable) {
            return ErrorVista::errorInterno();
        }
    }

    /** Respeta el id del llamador si es seguro (lista blanca); si no, genera un UUID v4. */
    private static function idDeTraza(string $recibido): string
    {
        if (preg_match('/^[A-Za-z0-9-]{8,64}$/', $recibido) === 1) {
            return $recibido;
        }
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
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
