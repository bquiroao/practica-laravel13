<?php
declare(strict_types=1);

namespace Admision\Presentation\Web;

/**
 * Plantillas PHP con salida ESCAPADA: las plantillas solo imprimen con $e(...).
 * No se usa extract(): la plantilla recibe el arreglo $d (copia) y la función $e.
 */
final class Vista
{
    public function __construct(private readonly string $carpeta)
    {
    }

    /** @param array<string, mixed> $datos */
    public function render(string $plantilla, array $datos, string $titulo): string
    {
        $contenido = $this->incluir($plantilla, $datos);
        return $this->incluir('layout', ['titulo' => $titulo, 'contenido' => $contenido]);
    }

    private function incluir(string $plantilla, array $d): string
    {
        $e = static fn (mixed $valor): string => htmlspecialchars((string) $valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        ob_start();
        require $this->carpeta . '/' . $plantilla . '.php';
        return (string) ob_get_clean();
    }
}
