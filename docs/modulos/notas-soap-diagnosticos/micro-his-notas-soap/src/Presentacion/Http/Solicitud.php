<?php

declare(strict_types=1);

namespace NotasSoap\Presentacion\Http;

/** Petición HTTP inmutable. En pruebas se construye a mano; en producción desde las superglobales. */
final class Solicitud
{
    /**
     * @param array<string, string> $cabeceras nombres en minúsculas
     * @param array<string, mixed>|null $cuerpo
     */
    public function __construct(
        public readonly string $metodo,
        public readonly string $ruta,
        public readonly array $cabeceras = [],
        public readonly ?array $cuerpo = null,
    ) {
    }

    public static function desdeGlobales(): self
    {
        $cabeceras = [];
        foreach ($_SERVER as $clave => $valor) {
            if (str_starts_with((string) $clave, 'HTTP_')) {
                $cabeceras[strtolower(str_replace('_', '-', substr((string) $clave, 5)))] = (string) $valor;
            }
        }
        $json = json_decode((string) file_get_contents('php://input'), true);

        return new self(
            strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')),
            (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH),
            $cabeceras,
            is_array($json) ? $json : null,
        );
    }

    public function cabecera(string $nombre): string
    {
        return trim($this->cabeceras[strtolower($nombre)] ?? '');
    }

    /** El navegador pide HTML (Accept: text/html); curl, Postman y el frontend piden JSON. */
    public function aceptaHtml(): bool
    {
        $accept = strtolower($this->cabecera('Accept'));

        return str_contains($accept, 'text/html') && !str_contains($accept, 'application/json');
    }
}
