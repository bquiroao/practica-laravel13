<?php
declare(strict_types=1);

// Presentación MVC (semana 4). Enrutador: elige la acción del controlador según ruta y método.
namespace Traslados\Presentacion;

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
    private const RUTAS = ['/api/v1/traslados-altas/movimientos' => ['POST' => 'registrar']];

    public function __construct(private MovimientosControlador $controlador) {}

    /** @return array{0: int, 1: array<string, mixed>} */
    public function atender(Peticion $p): array {
        $acciones = self::RUTAS[$p->ruta] ?? null;
        if ($acciones === null) { return VistaJson::error('ruta_no_encontrada'); }
        $accion = $acciones[$p->metodo] ?? null;
        return $accion === null ? VistaJson::error('metodo_no_permitido') : $this->controlador->$accion($p);
    }
}
