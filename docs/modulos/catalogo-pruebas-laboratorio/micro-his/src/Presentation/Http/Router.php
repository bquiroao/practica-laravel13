<?php
declare(strict_types=1);

namespace Lab\Presentation\Http;

/** Asocia método + ruta con una acción del controlador. */
final class Router
{
    public function __construct(private readonly LabTestController $controlador)
    {
    }

    public function despachar(string $metodo, string $ruta, array $query, array $post): Respuesta
    {
        $ruta = rtrim($ruta, '/') ?: '/';
        return match (true) {
            $metodo === 'GET' && in_array($ruta, ['/', '/pruebas'], true) => $this->controlador->index($query),
            $metodo === 'GET' && $ruta === '/pruebas/nueva' => $this->controlador->formulario(),
            $metodo === 'POST' && $ruta === '/pruebas' => $this->controlador->guardar($post),
            $metodo === 'GET' && preg_match('#^/pruebas/([A-Z]{3}-\d{3})$#', $ruta, $m) === 1 => $this->controlador->mostrar($m[1], $query),
            default => new Respuesta(404, 'mensaje', ['titulo' => 'No encontrado', 'mensaje' => 'La ruta no existe.']),
        };
    }
}
