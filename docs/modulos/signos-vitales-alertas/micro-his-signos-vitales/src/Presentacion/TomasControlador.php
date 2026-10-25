<?php
declare(strict_types=1);

// Presentación: traduce HTTP ↔ caso de uso. No tiene SQL ni reglas clínicas.
namespace SignosVitales\Presentacion;

use SignosVitales\Aplicacion\{ErrorPersistencia, RegistrarToma, Usuario};
use SignosVitales\Dominio\ErrorDominio;

final class TomasControlador {
    private const ESTADOS = ['acceso_denegado' => 403, 'episodio_no_disponible' => 409, 'medicion_invalida' => 422, 'toma_vacia' => 422, 'signo_repetido' => 422];

    /** @param array<string, Usuario> $usuarios identidades de demostración mientras no se integre RBAC (no son credenciales) */
    public function __construct(private RegistrarToma $registrar, private array $usuarios) {}

    /** @return array{0: int, 1: array<string, mixed>} estado HTTP y cuerpo JSON */
    public function atender(string $metodo, string $ruta, string $usuarioDemo, string $cuerpo): array {
        if ($ruta !== '/api/v1/signos-vitales/tomas') { return [404, ['codigo' => 'ruta_no_encontrada']]; }
        if ($metodo !== 'POST') { return [405, ['codigo' => 'metodo_no_permitido']]; }
        $usuario = $this->usuarios[$usuarioDemo] ?? null;
        if ($usuario === null) { return [401, ['codigo' => 'sin_sesion']]; }
        $entrada = json_decode($cuerpo, true);
        if (!is_array($entrada)) { return [400, ['codigo' => 'json_invalido']]; }
        try {
            return [201, $this->registrar->ejecutar($entrada, $usuario)];
        } catch (ErrorDominio $e) {
            return [self::ESTADOS[$e->codigo] ?? 422, ['codigo' => $e->codigo, 'mensaje' => $e->getMessage(), 'campos' => $e->campos]];
        } catch (ErrorPersistencia) {
            return [500, ['codigo' => 'error_persistencia', 'mensaje' => 'No se pudo guardar la toma; intente de nuevo']];
        }
    }
}
