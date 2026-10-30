<?php
declare(strict_types=1);

namespace Catalogo\Presentation\Web;

/** Petición HTTP inmutable (separada de $_GET/$_POST para poder probar el controlador). */
final class Peticion
{
    /**
     * @param array<string, mixed> $consulta
     * @param array<string, mixed> $cuerpo
     * @param array<string, string> $cabeceras nombres en minúsculas
     * @param string $crudo cuerpo sin procesar (JSON de la API, semana 5)
     */
    public function __construct(
        public readonly string $metodo,
        public readonly string $ruta,
        public readonly array $consulta = [],
        public readonly array $cuerpo = [],
        public readonly array $cabeceras = [],
        public readonly string $crudo = '',
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
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $cabeceras['content-type'] = (string) $_SERVER['CONTENT_TYPE'];
        }
        $ruta = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
        return new self(strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')), rtrim($ruta, '/') ?: '/', $_GET, $_POST, $cabeceras,
            (string) file_get_contents('php://input'));
    }
}
