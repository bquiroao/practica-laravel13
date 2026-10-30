<?php
declare(strict_types=1);

// Presentación MVC (semana 4). Enrutador: método + ruta → acción del controlador; 404 si la ruta no existe, 405 si el método no corresponde.
namespace Reportes\Presentacion;

final class Peticion {
    public function __construct(public readonly string $metodo, public readonly string $ruta, public readonly array $consulta, public readonly string $usuarioDemo) {}

    public static function desdeGlobales(): self {
        return new self($_SERVER['REQUEST_METHOD'] ?? 'GET', (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), $_GET, (string) ($_SERVER['HTTP_X_USUARIO_DEMO'] ?? ''));
    }
}

final class Enrutador {
    private const RUTAS = [['GET', '#^/api/v1/reportes/(\w+)/exportar$#', 'exportar'], ['POST', '#^/api/v1/exportaciones/([\w-]+)/reproducir$#', 'reproducir']];

    public function __construct(private ReportesControlador $controlador) {}

    /** @return array{0: int, 1: array|string, 2: array<string, string>} */
    public function atender(Peticion $p): array {
        $existe = false;
        foreach (self::RUTAS as [$metodo, $patron, $accion]) {
            if (!preg_match($patron, $p->ruta, $m)) { continue; }
            $existe = true;
            if ($metodo === $p->metodo) { return $this->controlador->$accion($p, ...array_slice($m, 1)); }
        }
        return VistaHttp::error($existe ? 'metodo_no_permitido' : 'ruta_no_encontrada');
    }
}
