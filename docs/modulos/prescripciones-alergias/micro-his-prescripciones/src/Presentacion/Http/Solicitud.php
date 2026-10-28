<?php

declare(strict_types=1);

namespace Prescripciones\Presentacion\Http;

final readonly class Solicitud
{
    /** @param array<string, string> $cabeceras nombres en minúsculas */
    public function __construct(
        public string $metodo,
        public string $ruta,
        public array $cabeceras = [],
        public string $cuerpo = '',
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

        return new self(
            strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')),
            (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH),
            $cabeceras,
            (string) file_get_contents('php://input'),
        );
    }

    public function cabecera(string $nombre): ?string
    {
        return $this->cabeceras[strtolower($nombre)] ?? null;
    }
}
