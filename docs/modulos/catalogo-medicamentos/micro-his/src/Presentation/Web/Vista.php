<?php
declare(strict_types=1);

namespace Catalogo\Presentation\Web;

/** Plantillas PHP con salida escapada: toda variable se imprime con $e(...). */
final class Vista
{
    public function __construct(private readonly string $carpeta = __DIR__ . '/Vistas')
    {
    }

    public static function e(mixed $valor): string
    {
        return htmlspecialchars((string) $valor, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }

    /** @param array<string, mixed> $datos */
    public function mostrar(string $plantilla, array $datos, string $titulo): string
    {
        $contenido = $this->renderizar($plantilla, $datos);
        return $this->renderizar('layout', ['titulo' => $titulo, 'contenido' => $contenido, 'aviso' => $datos['aviso'] ?? null]);
    }

    /** @param array<string, mixed> $datos */
    private function renderizar(string $plantilla, array $datos): string
    {
        // Se copia el arreglo antes de extract(): extract() no acepta propiedades readonly.
        $variables = $datos;
        $variables['e'] = self::e(...);
        return (static function (string $archivo, array $variables): string {
            extract($variables, EXTR_SKIP);
            ob_start();
            require $archivo;
            return (string) ob_get_clean();
        })($this->carpeta . "/$plantilla.php", $variables);
    }
}
