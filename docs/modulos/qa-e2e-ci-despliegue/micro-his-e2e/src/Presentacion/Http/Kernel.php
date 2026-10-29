<?php

declare(strict_types=1);

namespace E2e\Presentacion\Http;

use E2e\Presentacion\Http\Controladores\Rutas;
use E2e\Presentacion\Http\Vistas\ErrorVista;

/**
 * Semana 4: el kernel solo autentica (token → Identidad), busca la ruta y llama a la acción del controlador.
 * La forma de la entrada la revisan los formularios, las reglas el dominio y los códigos HTTP la vista.
 */
final class Kernel
{
    private readonly ErrorVista $error;

    /**
     * @param array<string, object>                      $controladores nombre => controlador
     * @param array<string, array{0: string, 1: string}> $tokens token => [usuario, tenant]
     */
    public function __construct(private readonly array $controladores, private readonly array $tokens)
    {
        $this->error = new ErrorVista();
    }

    public function manejar(Solicitud $s): Respuesta
    {
        try {
            $identidad = $this->autenticar($s);
            if ($identidad instanceof Respuesta) {
                return $identidad;
            }
            $metodoDistinto = false;
            foreach (Rutas::tabla() as [$metodo, $patron, $controlador, $accion]) {
                if (preg_match('#^' . Rutas::BASE . $patron . '$#', $s->ruta, $m) !== 1) {
                    continue;
                }
                if ($metodo !== $s->metodo) {
                    $metodoDistinto = true;
                    continue;
                }
                $parametros = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);

                return $parametros === [] ? $this->controladores[$controlador]->$accion($s, $identidad)
                    : $this->controladores[$controlador]->$accion($s, $identidad, $parametros);
            }

            return $metodoDistinto ? $this->error->estado(405, 'METODO_NO_PERMITIDO') : $this->error->estado(404, 'RUTA_DESCONOCIDA');
        } catch (\Throwable) {
            return $this->error->estado(500, 'ERROR_INTERNO');           // sin detalles: pueden filtrar el SQL o rutas
        }
    }

    private function autenticar(Solicitud $s): Identidad|Respuesta
    {
        $token = preg_replace('/^Bearer\s+/i', '', (string) $s->cabecera('authorization'));
        [$usuario, $tenantDelToken] = $this->tokens[$token] ?? [null, null];
        if ($usuario === null) {
            return $this->error->estado(401, 'NO_AUTENTICADO', ['WWW-Authenticate' => 'Bearer']);
        }
        $tenant = (string) $s->cabecera('x-tenant-id');
        if ($tenant === '') {
            return $this->error->estado(400, 'TENANT_REQUERIDO');
        }

        return $tenant === $tenantDelToken ? new Identidad($usuario, $tenant) : $this->error->estado(403, 'TENANT_AJENO');
    }
}
