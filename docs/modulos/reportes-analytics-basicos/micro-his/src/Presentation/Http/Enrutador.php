<?php
declare(strict_types=1);

namespace MicroHis\Presentation\Http;

/** Front Controller: asocia método + ruta con la acción de un controlador. */
final class Enrutador
{
    /** @var list<array{string,string,callable}> */
    private array $rutas = [];

    /** @param string $patron expresión regular de la ruta; los grupos con nombre llegan a la acción */
    public function agregar(string $metodo, string $patron, callable $accion): void
    {
        $this->rutas[] = [$metodo, $patron, $accion];
    }

    /** @param array<string,string> $consulta parámetros de la URL */
    public function despachar(string $metodo, string $ruta, array $consulta): Respuesta
    {
        foreach ($this->rutas as [$metodoRuta, $patron, $accion]) {
            if ($metodoRuta === $metodo && preg_match("#^{$patron}$#", $ruta, $coincidencias) === 1) {
                return $accion(array_filter($coincidencias, 'is_string', ARRAY_FILTER_USE_KEY) + $consulta);
            }
        }
        return new Respuesta(404, 'Ruta no encontrada');
    }
}
