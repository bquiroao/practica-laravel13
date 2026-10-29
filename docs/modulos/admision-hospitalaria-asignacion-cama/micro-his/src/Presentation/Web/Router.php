<?php
declare(strict_types=1);

namespace Admision\Presentation\Web;

/** Enrutador: método + ruta exacta -> acción del controlador. Las rutas con {id} capturan un segmento. */
final class Router
{
    /** @var list<array{string, string, \Closure}> */
    private array $rutas = [];

    public function agregar(string $metodo, string $patron, \Closure $accion): void
    {
        $this->rutas[] = [$metodo, $patron, $accion];
    }

    public function despachar(Peticion $peticion): Respuesta
    {
        $metodoNoPermitido = false;
        foreach ($this->rutas as [$metodo, $patron, $accion]) {
            $regex = '#^' . preg_replace('#\\\{[a-z]+\\\}#', '([^/]+)', preg_quote($patron, '#')) . '$#';
            if (preg_match($regex, $peticion->ruta, $m) !== 1) {
                continue;
            }
            if ($metodo !== $peticion->metodo) {
                $metodoNoPermitido = true;
                continue;
            }
            return $accion($peticion, ...array_slice($m, 1));
        }
        return $metodoNoPermitido
            ? new Respuesta(405, 'Método no permitido.', ['Content-Type' => 'text/plain; charset=utf-8'])
            : new Respuesta(404, 'Página no encontrada.', ['Content-Type' => 'text/plain; charset=utf-8']);
    }
}
