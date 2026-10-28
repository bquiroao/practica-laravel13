<?php
declare(strict_types=1);

// Presentación: traduce HTTP ↔ caso de uso. No tiene SQL ni reglas de negocio.
namespace Traslados\Presentacion;

use Traslados\Aplicacion\{ErrorPersistencia, RegistrarMovimiento, Usuario};
use Traslados\Dominio\ErrorDominio;

final class MovimientosControlador {
    private const ESTADOS = ['acceso_denegado' => 403, 'episodio_no_disponible' => 409, 'orden_requerida' => 409, 'sin_cama' => 409,
                             'cama_no_disponible' => 409, 'cama_no_cumple_requisito' => 409, 'alta_invalida' => 422];
    /** @param array<string, Usuario> $usuarios identidades de demostración mientras no se integre RBAC (no son credenciales) */
    public function __construct(private RegistrarMovimiento $registrar, private array $usuarios) {}
    /** @return array{0: int, 1: array<string, mixed>} estado HTTP y cuerpo JSON */
    public function atender(string $metodo, string $ruta, string $usuarioDemo, string $cuerpo): array {
        if ($ruta !== '/api/v1/traslados-altas/movimientos') { return [404, ['codigo' => 'ruta_no_encontrada']]; }
        if ($metodo !== 'POST') { return [405, ['codigo' => 'metodo_no_permitido']]; }
        $usuario = $this->usuarios[$usuarioDemo] ?? null;
        if ($usuario === null) { return [401, ['codigo' => 'sin_sesion']]; }
        $entrada = json_decode($cuerpo, true);
        if (!is_array($entrada)) { return [400, ['codigo' => 'json_invalido']]; }
        try {
            $r = $this->registrar->ejecutar($entrada, $usuario);
            if (!$r['aplicado']) { return [self::ESTADOS[$r['rechazo']] ?? 422, ['codigo' => $r['rechazo']]]; }
            return [201, $r];
        } catch (ErrorDominio $e) {
            return [self::ESTADOS[$e->codigo] ?? 422, ['codigo' => $e->codigo, 'mensaje' => $e->getMessage(), 'campos' => $e->campos]];
        } catch (ErrorPersistencia) {
            return [500, ['codigo' => 'error_persistencia', 'mensaje' => 'No se pudo registrar el movimiento; intente de nuevo']];
        }
    }
}
