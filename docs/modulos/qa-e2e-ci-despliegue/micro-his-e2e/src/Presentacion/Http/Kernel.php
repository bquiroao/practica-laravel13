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
    private ?Identidad $identidad = null;
    /** @var (callable(string): void)|null */
    private $registroAcceso;

    /**
     * @param array<string, object>                      $controladores nombre => controlador
     * @param array<string, array{0: string, 1: string}> $tokens token => [usuario, tenant]
     * @param (callable(string): void)|null              $registroAcceso recibe una línea JSON por petición (semana 5)
     */
    public function __construct(private readonly array $controladores, private readonly array $tokens, ?callable $registroAcceso = null)
    {
        $this->error = new ErrorVista();
        $this->registroAcceso = $registroAcceso;
    }

    /**
     * Observabilidad (semana 5): toda respuesta lleva X-Request-ID (el del cliente si es seguro; si no, uno nuevo) y deja
     * una línea JSON en el registro de acceso SIN cuerpo, token ni artefactos. Si el registro falla, la respuesta no cambia.
     */
    public function manejar(Solicitud $s): Respuesta
    {
        $inicio = hrtime(true);
        $propio = (string) $s->cabecera('x-request-id');
        $id = preg_match('/^[A-Za-z0-9-]{8,64}$/', $propio) === 1 ? $propio : bin2hex(random_bytes(8));
        $this->identidad = null;
        $r = $this->procesar($s);
        $r = new Respuesta($r->estado, $r->cuerpo, $r->cabeceras + ['X-Request-ID' => $id]);
        if ($this->registroAcceso !== null) {
            try {
                ($this->registroAcceso)(json_encode(['en' => gmdate('c'), 'request_id' => $id, 'metodo' => $s->metodo, 'ruta' => $s->ruta,
                    'estado' => $r->estado, 'codigo' => $r->cuerpo['codigo'] ?? $r->cuerpo['error'] ?? null, 'ms' => round((hrtime(true) - $inicio) / 1e6, 1),
                    'usuario' => $this->identidad?->usuario, 'tenant' => $this->identidad?->tenant], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            } catch (\Throwable) {
            }
        }

        return $r;
    }

    private function procesar(Solicitud $s): Respuesta
    {
        try {
            $identidad = $this->autenticar($s);
            if ($identidad instanceof Respuesta) {
                return $identidad;
            }
            $this->identidad = $identidad;
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
