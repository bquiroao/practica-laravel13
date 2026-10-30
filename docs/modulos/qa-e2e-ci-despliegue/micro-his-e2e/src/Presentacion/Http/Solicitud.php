<?php

declare(strict_types=1);

namespace E2e\Presentacion\Http;

final readonly class Solicitud
{
    /** @param array<string, string> $cabeceras en minúsculas */
    public function __construct(public string $metodo, public string $ruta, public array $cabeceras = [], public string $cuerpo = '')
    {
    }

    public static function desdeGlobales(): self
    {
        $cabeceras = [];
        foreach ($_SERVER as $k => $v) {
            if (str_starts_with($k, 'HTTP_')) {
                $cabeceras[strtolower(str_replace('_', '-', substr($k, 5)))] = (string) $v;
            }
        }

        return new self($_SERVER['REQUEST_METHOD'] ?? 'GET', (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), $cabeceras,
            (string) file_get_contents('php://input'));
    }

    public function cabecera(string $nombre): ?string
    {
        return $this->cabeceras[strtolower($nombre)] ?? null;
    }
}
