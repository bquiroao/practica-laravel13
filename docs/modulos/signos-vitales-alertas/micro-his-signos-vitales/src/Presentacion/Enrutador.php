<?php
declare(strict_types=1);

// Presentación MVC (semana 4). Enrutador: elige la acción del controlador según método y ruta.
namespace SignosVitales\Presentacion;

final class Peticion {
    public function __construct(public readonly string $metodo, public readonly string $ruta, public readonly array $consulta,
                                public readonly string $usuarioDemo, public readonly string $cuerpo,
                                public readonly ?string $claveIdempotencia = null, public readonly string $idSolicitud = '') {}

    public static function desdeGlobales(): self {
        return new self($_SERVER['REQUEST_METHOD'] ?? 'GET', (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), $_GET,
                        (string) ($_SERVER['HTTP_X_USUARIO_DEMO'] ?? ''), (string) file_get_contents('php://input'), $_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? null,
                        preg_match('/^[A-Za-z0-9-]{8,64}$/', $_SERVER['HTTP_X_REQUEST_ID'] ?? '') ? $_SERVER['HTTP_X_REQUEST_ID'] : bin2hex(random_bytes(8)));
    }
}

final class Enrutador {
    public function __construct(private TomasControlador $tomas) {}

    /** @return array{0: int, 1: array<string, mixed>} */
    public function atender(Peticion $p): array {
        if ($p->ruta !== '/api/v1/signos-vitales/tomas') { return VistaJson::error('ruta_no_encontrada'); }
        return match ($p->metodo) { 'POST' => $this->tomas->registrar($p), 'GET' => $this->tomas->listar($p), default => VistaJson::error('metodo_no_permitido') };
    }
}
