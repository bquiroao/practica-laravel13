<?php
declare(strict_types=1);

namespace AlertasCriticas\Presentation\Web;

/** Petición HTTP inmutable; se arma desde los superglobales solo en public/index.php. */
final class Peticion
{
    public function __construct(
        public readonly string $metodo,
        public readonly string $ruta,
        public readonly array $consulta = [],
        public readonly array $formulario = [],
        public readonly array $cookies = [],
        public readonly array $cabeceras = [],
        public readonly string $cuerpo = '',
    ) {}

    public static function desdeGlobales(): self
    {
        $ruta = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $cabeceras = [];
        foreach ($_SERVER as $clave => $valor) {
            if (str_starts_with($clave, 'HTTP_')) {
                $cabeceras[strtolower(str_replace('_', '-', substr($clave, 5)))] = (string) $valor;
            }
        }
        return new self(strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'), rtrim($ruta, '/') ?: '/', $_GET, $_POST, $_COOKIE,
            $cabeceras, (string) file_get_contents('php://input'));
    }

    public function cabecera(string $nombre): string
    {
        return trim($this->cabeceras[strtolower($nombre)] ?? '');
    }

    public function campo(string $nombre): string
    {
        return trim((string) ($this->formulario[$nombre] ?? ''));
    }
}
