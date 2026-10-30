<?php
declare(strict_types=1);

namespace Alergias\Presentacion\Web;

/** Relaciona método + ruta con una acción de controlador. No sabe nada del negocio. */
final class Router
{
    /** @var list<array{string, string, callable}> */
    private array $rutas = [];

    public function agregar(string $metodo, string $patron, callable $accion): void
    {
        $regex = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>[A-Za-z0-9-]+)', $patron) . '$#';
        $this->rutas[] = [$metodo, $regex, $accion];
    }

    /** @param array<string, string> $formulario */
    public function despachar(string $metodo, string $ruta, array $formulario = []): Respuesta
    {
        foreach ($this->rutas as [$m, $regex, $accion]) {
            if ($m === $metodo && preg_match($regex, $ruta, $partes)) {
                $parametros = array_filter($partes, 'is_string', ARRAY_FILTER_USE_KEY);
                return $accion($parametros, $formulario);
            }
        }
        return new Respuesta(404, Vista::render('error', ['codigo' => 'RUTA_NO_ENCONTRADA', 'mensaje' => 'La página no existe.']));
    }
}
