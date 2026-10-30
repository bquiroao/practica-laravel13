<?php
declare(strict_types=1);

// Controlador: lee la petición, llama al caso de uso y elige la vista. Sin SQL ni reglas clínicas.
namespace SignosVitales\Presentacion;

use SignosVitales\Aplicacion\{ConsultarTomas, ErrorPersistencia, RegistrarToma, Usuario};
use SignosVitales\Dominio\ErrorDominio;

final class TomasControlador {
    /** @param array<string, Usuario> $usuarios identidades de demostración mientras no se integre RBAC (no son credenciales) */
    public function __construct(private RegistrarToma $registrar, private ConsultarTomas $consultar, private array $usuarios) {}

    public function registrar(Peticion $p): array {
        return $this->conUsuario($p, function (Usuario $u) use ($p) {
            $entrada = json_decode($p->cuerpo, true);
            if (!is_array($entrada)) { return VistaJson::error('json_invalido'); }
            if ($p->claveIdempotencia !== null && !preg_match('/^[A-Za-z0-9-]{8,64}$/', $p->claveIdempotencia)) { return VistaJson::error('idempotency_key_invalida'); }
            $r = $this->registrar->ejecutar($entrada, $u, $p->claveIdempotencia);
            return isset($r['repetida']) ? [200, $r] : VistaJson::creada($r);
        });
    }

    public function listar(Peticion $p): array {
        return $this->conUsuario($p, fn (Usuario $u) => VistaJson::lista(
            $this->consultar->ejecutar((string) ($p->consulta['paciente_id'] ?? ''), (string) ($p->consulta['episodio_id'] ?? ''), $u)));
    }

    private function conUsuario(Peticion $p, callable $accion): array {
        $usuario = $this->usuarios[$p->usuarioDemo] ?? null;
        if ($usuario === null) { return VistaJson::error('sin_sesion'); }
        try { return $accion($usuario); }
        catch (ErrorDominio $e) { return VistaJson::error($e->codigo, $e->getMessage(), $e->campos); }
        catch (ErrorPersistencia) { return VistaJson::error('error_persistencia', 'No se pudo completar; intente de nuevo'); }
    }
}
