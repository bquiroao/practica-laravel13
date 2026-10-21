<?php

declare(strict_types=1);

namespace Pacientes\Presentacion\Http;

use Pacientes\Presentacion\Http\Autenticacion\Autenticador;
use Pacientes\Presentacion\Http\Vistas\ErrorVista;

/**
 * Front controller: resuelve la ruta y aplica la cadena
 * hospital (400) → identidad (401) → token del mismo hospital (403) → controlador.
 * Los permisos por operación los decide la Aplicación (R-05).
 */
final class Kernel
{
    /** @param list<Ruta> $rutas */
    public function __construct(
        private readonly array $rutas,
        private readonly Autenticador $autenticador,
    ) {
    }

    public function manejar(Solicitud $solicitud): Respuesta
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
