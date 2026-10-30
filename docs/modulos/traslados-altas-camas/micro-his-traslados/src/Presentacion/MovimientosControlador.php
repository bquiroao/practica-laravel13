<?php
declare(strict_types=1);

// Controlador: lee la petición, identifica al usuario, llama al caso de uso y elige la vista. Sin SQL ni reglas de negocio.
namespace Traslados\Presentacion;

use Traslados\Aplicacion\{ErrorPersistencia, RegistrarMovimiento, Usuario};
use Traslados\Dominio\ErrorDominio;

final class MovimientosControlador {
    /** @param array<string, Usuario> $usuarios identidades de demostración mientras no se integre RBAC (no son credenciales) */
    public function __construct(private RegistrarMovimiento $registrar, private array $usuarios) {}

    public function registrar(Peticion $p): array {
        return $this->conUsuario($p, function (Usuario $u) use ($p) {
            $entrada = json_decode($p->cuerpo, true);
            if (!is_array($entrada)) { return VistaJson::error('json_invalido'); }
            $r = $this->registrar->ejecutar($entrada, $u);
            return $r['aplicado'] ? VistaJson::creada($r) : VistaJson::error((string) $r['rechazo']);
        });
    }

    private function conUsuario(Peticion $p, callable $accion): array {
        $usuario = $this->usuarios[$p->usuarioDemo] ?? null;
        if ($usuario === null) { return VistaJson::error('sin_sesion'); }
        try { return $accion($usuario); }
        catch (ErrorDominio $e) { return VistaJson::error($e->codigo, $e->getMessage(), $e->campos); }
        catch (ErrorPersistencia) { return VistaJson::error('error_persistencia', 'No se pudo registrar el movimiento; intente de nuevo'); }
    }
}
