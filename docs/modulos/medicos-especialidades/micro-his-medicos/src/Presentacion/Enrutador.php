<?php
declare(strict_types=1);

// Presentación MVC (semana 4). Enrutador: elige la acción del controlador según método y ruta.
namespace Medicos\Presentacion;

final class Peticion {
    public function __construct(public readonly string $metodo, public readonly string $ruta, public readonly array $consulta, public readonly string $usuarioDemo, public readonly string $cuerpo) {}
    public static function desdeGlobales(): self { return new self($_SERVER['REQUEST_METHOD'] ?? 'GET', (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH),
                                                                  $_GET, (string) ($_SERVER['HTTP_X_USUARIO_DEMO'] ?? ''), (string) file_get_contents('php://input')); }
}

final class Enrutador {
    public function __construct(private MedicosControlador $medicos) {}
    /** @return array{0: int, 1: array<string, mixed>} */
    public function atender(Peticion $p): array {
        $rutas = ['#^/api/v1/medicos$#' => ['POST' => 'registrar'], '#^/api/v1/medicos/([\w-]+)/especialidades$#' => ['POST' => 'asociar'], '#^/api/v1/disponibilidad$#' => ['GET' => 'disponibilidad']];
        foreach ($rutas as $patron => $acciones) {
            if (!preg_match($patron, $p->ruta, $m)) { continue; }
            return isset($acciones[$p->metodo]) ? $this->medicos->{$acciones[$p->metodo]}($p, $m[1] ?? '') : VistaJson::error('metodo_no_permitido');
        }
        return VistaJson::error('ruta_no_encontrada');
    }
}
