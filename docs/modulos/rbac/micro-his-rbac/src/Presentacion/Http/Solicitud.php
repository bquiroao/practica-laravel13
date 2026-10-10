<?php

declare(strict_types=1);

namespace Rbac\Presentacion\Http;

/** Petición HTTP independiente de las superglobales (se puede construir en pruebas). */
final class Solicitud
{
    /** @var array<string, string> */
    private readonly array $cabeceras;

    /**
     * @param array<string, string> $cabeceras
     * @param array<string, mixed>|null $cuerpo
     */
    public function __construct(
        public readonly string $metodo,
        public readonly string $ruta,
        array $cabeceras = [],
        public readonly ?array $cuerpo = null,
        public readonly string $ip = '127.0.0.1',
    ) {
        $normalizadas = [];
        foreach ($cabeceras as $nombre => $valor) {
            $normalizadas[strtolower($nombre)] = $valor;
        }
        $this->cabeceras = $normalizadas;
    }

    public static function desdeGlobales(): self
    {
        $cabeceras = [];
        foreach ($_SERVER as $clave => $valor) {
            if (str_starts_with($clave, 'HTTP_')) {
                $cabeceras[str_replace('_', '-', substr($clave, 5))] = (string) $valor;
            }
        }

        $cuerpo = json_decode((string) file_get_contents('php://input'), true);

        return new self(
            strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')),
            (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH),
            $cabeceras,
            is_array($cuerpo) ? $cuerpo : null,
            (string) ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'),
        );
    }

    public function cabecera(string $nombre): string
    {
        return trim($this->cabeceras[strtolower($nombre)] ?? '');
    }
}
