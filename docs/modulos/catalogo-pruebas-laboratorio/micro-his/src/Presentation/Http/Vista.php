<?php
declare(strict_types=1);

namespace Lab\Presentation\Http;

/** Convierte una Respuesta en HTML usando las plantillas de vistas/. */
final class Vista
{
    public static function render(Respuesta $r): string
    {
        $e = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $datos = $r->datos;   // copia: extract() no puede tomar una propiedad readonly
        extract($datos);
        ob_start();
        require __DIR__ . '/../vistas/' . $r->vista . '.php';
        $contenido = ob_get_clean();
        ob_start();
        require __DIR__ . '/../vistas/layout.php';
        return (string) ob_get_clean();
    }
}
