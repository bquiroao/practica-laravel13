<?php
declare(strict_types=1);

namespace AlertasCriticas\Presentation\Web;

/** Enrutador mínimo: método + patrón con {parametros} → manejador. */
final class Router
{
    /** @var array<int, array{0: string, 1: string, 2: callable}> */
    private array $rutas = [];

    public function agregar(string $metodo, string $patron, callable $manejador): void
    {
        $regex = '#^' . preg_replace('#\{([a-z]+)\}#', '(?P<$1>[^/]+)', $patron) . '$#';
        $this->rutas[] = [strtoupper($metodo), $regex, $manejador];
    }

    public function despachar(Peticion $peticion): Respuesta
    {
        $rutaExiste = false;
        foreach ($this->rutas as [$metodo, $regex, $manejador]) {
            if (!preg_match($regex, $peticion->ruta, $m)) {
                continue;
            }
            $rutaExiste = true;
            if ($metodo === $peticion->metodo) {
                $parametros = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
                return $manejador($peticion, ...$parametros);
            }
        }
        return $rutaExiste
            ? new Respuesta(405, 'Método no permitido', ['Content-Type' => 'text/plain; charset=utf-8'])
            : new Respuesta(404, 'Página no encontrada', ['Content-Type' => 'text/plain; charset=utf-8']);
    }
}
