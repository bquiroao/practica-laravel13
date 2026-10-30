<?php

declare(strict_types=1);

namespace Auditoria\Presentacion\Http;

/** Petición HTTP ya leída. Se arma desde las superglobales solo en public/index.php. */
final class Solicitud
{
    /** @var array<string, string> cabeceras con el nombre en minúsculas */
    private readonly array $cabeceras;

    /**
     * @param array<string, string> $cabeceras
     * @param array<string, mixed> $consulta
     * @param array<string, mixed>|null $cuerpo null = no hay cuerpo JSON (ausente o mal formado)
     */
    public function __construct(
        public readonly string $metodo,
        public readonly string $ruta,
        array $cabeceras = [],
        public readonly array $consulta = [],
        public readonly ?array $cuerpo = null,
    ) {
        $this->cabeceras = array_change_key_case($cabeceras, CASE_LOWER);
    }

    public static function desdeGlobales(): self
    {
        $cabeceras = [];
        foreach ($_SERVER as $clave => $valor) {
            if (str_starts_with($clave, 'HTTP_')) {
                $cabeceras[str_replace('_', '-', substr($clave, 5))] = (string) $valor;
            }
        }
        $json = json_decode((string) file_get_contents('php://input'), true);

        return new self($_SERVER['REQUEST_METHOD'] ?? 'GET', (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), $cabeceras,
            $_GET, is_array($json) ? $json : null);
    }

    public function cabecera(string $nombre): string
    {
        return trim($this->cabeceras[strtolower($nombre)] ?? '');
    }
}
