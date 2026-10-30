<?php
declare(strict_types=1);

namespace AlertasCriticas\Presentation\Web;

/** Renderiza plantillas PHP. Toda salida pasa por $e() (escape HTML) dentro de las plantillas. */
final class Vista
{
    public function __construct(private string $carpeta) {}

    public static function escapar(mixed $valor): string
    {
        return htmlspecialchars((string) $valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** Las plantillas reciben $d (datos) y $e (escape); no se usa extract(). */
    public function render(string $plantilla, array $datos, string $titulo): string
    {
        $contenido = $this->incluir("$plantilla.php", $datos);
        return $this->incluir('layout.php', $datos + ['contenido' => $contenido, 'titulo' => $titulo]);
    }

    private function incluir(string $archivo, array $d): string
    {
        $e = [self::class, 'escapar'];
        ob_start();
        try {
            (static function (string $__ruta, array $d, callable $e): void { require $__ruta; })("{$this->carpeta}/$archivo", $d, $e);
        } catch (\Throwable $t) {
            ob_end_clean();
            throw $t;
        }
        return (string) ob_get_clean();
    }
}
