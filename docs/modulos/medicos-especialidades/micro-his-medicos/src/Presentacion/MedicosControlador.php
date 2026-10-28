<?php
declare(strict_types=1);

// Presentación: traduce HTTP ↔ casos de uso. No tiene SQL ni reglas de negocio.
namespace Medicos\Presentacion;

use Medicos\Aplicacion\{AsociarEspecialidad, ConsultarDisponibilidad, ErrorPersistencia, RegistrarMedico, Usuario};
use Medicos\Dominio\ErrorDominio;

final class MedicosControlador {
    private const ESTADOS = ['acceso_denegado' => 403, 'medico_no_encontrado' => 404, 'usuario_no_disponible' => 409, 'medico_duplicado' => 409, 'especialidad_duplicada' => 409];
    public function __construct(private RegistrarMedico $registrar, private AsociarEspecialidad $asociar, private ConsultarDisponibilidad $disponibilidad, private array $usuarios) {} // usuarios de demostración hasta integrar RBAC (no son credenciales)
    public function atender(string $metodo, string $ruta, string $usuarioDemo, string $cuerpo = '', array $consulta = []): array {
        $usuario = $this->usuarios[$usuarioDemo] ?? null;
        $entrada = $metodo === 'POST' ? json_decode($cuerpo, true) : [];
        try {
            if ($metodo === 'POST' && $ruta === '/api/v1/medicos') { $accion = fn () => [201, $this->registrar->ejecutar($entrada, $usuario)]; }
            elseif ($metodo === 'POST' && preg_match('#^/api/v1/medicos/([\w-]+)/especialidades$#', $ruta, $m)) { $accion = fn () => [201, $this->asociar->ejecutar($m[1], $entrada, $usuario)]; }
            elseif ($metodo === 'GET' && $ruta === '/api/v1/disponibilidad') {
                $accion = fn () => [200, ['franjas' => $this->disponibilidad->ejecutar((string) ($consulta['especialidad'] ?? ''), (string) ($consulta['desde'] ?? ''), (string) ($consulta['hasta'] ?? ''), $usuario)]];
            } else { return in_array($ruta, ['/api/v1/medicos', '/api/v1/disponibilidad'], true) ? [405, ['codigo' => 'metodo_no_permitido']] : [404, ['codigo' => 'ruta_no_encontrada']]; }
            if ($usuario === null) { return [401, ['codigo' => 'sin_sesion']]; }
            if (!is_array($entrada)) { return [400, ['codigo' => 'json_invalido']]; }
            return $accion();
        } catch (ErrorDominio $e) {
            return [self::ESTADOS[$e->codigo] ?? 422, ['codigo' => $e->codigo, 'mensaje' => $e->getMessage()]];
        } catch (ErrorPersistencia) {
            return [500, ['codigo' => 'error_persistencia', 'mensaje' => 'No se pudo completar; intente de nuevo']];
        }
    }
}
