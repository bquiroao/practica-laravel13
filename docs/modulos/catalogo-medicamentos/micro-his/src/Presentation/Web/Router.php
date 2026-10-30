<?php
declare(strict_types=1);

namespace Catalogo\Presentation\Web;

/** Enrutador mínimo: método + patrón con {parametros}. No conoce reglas ni datos. */
final class Router
{
    /** @var list<array{0: string, 1: string, 2: callable}> */
    private array $rutas = [];

    public function agregar(string $metodo, string $patron, callable $accion): void
    {
        $regex = '#^' . preg_replace('#\{([a-z]+)\}#', '(?P<$1>[^/]+)', $patron) . '$#';
        $this->rutas[] = [$metodo, $regex, $accion];
    }

    /** @param callable(int, string, list<string>): Respuesta $error recibe los métodos permitidos en el 405 */
    public function despachar(Peticion $p, callable $error): Respuesta
    {
        $metodosPermitidos = [];
        foreach ($this->rutas as [$metodo, $regex, $accion]) {
            if (preg_match($regex, $p->ruta, $m) !== 1) {
                continue;
            }
            if ($metodo !== $p->metodo) {
                $metodosPermitidos[] = $metodo;
                continue;
            }
            $parametros = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
            return $accion($p, ...array_values($parametros));
        }
        $metodosPermitidos = array_values(array_unique($metodosPermitidos));
        return $metodosPermitidos !== []
            ? $error(405, 'Método no permitido', $metodosPermitidos)->conCabecera('Allow', implode(', ', $metodosPermitidos))
            : $error(404, 'No existe la página solicitada');
    }
}
