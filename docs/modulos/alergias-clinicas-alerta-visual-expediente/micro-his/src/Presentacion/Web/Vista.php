<?php
declare(strict_types=1);

namespace Alergias\Presentacion\Web;

/** Plantillas PHP con salida escapada: toda variable se imprime con $e(). */
final class Vista
{
    public static function render(string $plantilla, array $datos): string
    {
        $datos['e'] = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $datos['contenido'] = self::incluir(__DIR__ . "/vistas/{$plantilla}.php", $datos);
        return self::incluir(__DIR__ . '/vistas/layout.php', $datos);
    }

    private static function incluir(string $__archivo, array $__datos): string
    {
        extract($__datos); // $__datos es una copia local, no una propiedad readonly
        ob_start();
        require $__archivo;
        return (string) ob_get_clean();
    }
}
