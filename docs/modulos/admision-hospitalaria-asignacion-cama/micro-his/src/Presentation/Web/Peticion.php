<?php
declare(strict_types=1);

namespace Admision\Presentation\Web;

/** Petición HTTP mínima, sin superglobales dentro de la aplicación (se pueden construir en pruebas). */
final class Peticion
{
    /**
     * @param array<string, string> $consulta   parámetros de la URL
     * @param array<string, string> $formulario campos enviados por POST
     * @param array<string, string> $cabeceras  nombres en minúsculas
     */
    public function __construct(
        public readonly string $metodo,
        public readonly string $ruta,
        public readonly array $consulta = [],
        public readonly array $formulario = [],
        public readonly array $cabeceras = [],
        public readonly string $cuerpo = '',
    ) {
    }

    public static function desdeGlobales(): self
    {
        $cabeceras = [];
        foreach ($_SERVER as $clave => $valor) {
            if (str_starts_with($clave, 'HTTP_')) {
                $cabeceras[strtolower(str_replace('_', '-', substr($clave, 5)))] = (string) $valor;
            }
        }
        $ruta = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        return new self($_SERVER['REQUEST_METHOD'] ?? 'GET', $ruta, $_GET, $_POST, $cabeceras, (string) file_get_contents('php://input'));
    }

    public function dato(string $nombre): string
    {
        $valor = $this->formulario[$nombre] ?? $this->consulta[$nombre] ?? '';
        return is_string($valor) ? trim($valor) : '';
    }
}
