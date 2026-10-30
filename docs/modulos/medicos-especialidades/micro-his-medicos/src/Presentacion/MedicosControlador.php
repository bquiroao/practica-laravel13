<?php
declare(strict_types=1);

// Presentación: traduce HTTP ↔ casos de uso. No tiene SQL ni reglas de negocio.
namespace Medicos\Presentacion;

use Medicos\Aplicacion\{AsociarEspecialidad, ConsultarDisponibilidad, ErrorPersistencia, RegistrarMedico, Usuario};
use Medicos\Dominio\ErrorDominio;

final class MedicosControlador {
    public function __construct(private RegistrarMedico $registrar, private AsociarEspecialidad $asociar, private ConsultarDisponibilidad $disponibilidad, private array $usuarios) {} // usuarios de demostración hasta integrar RBAC (no son credenciales)
    public function registrar(Peticion $p): array { return $this->conCuerpo($p, fn (array $e, Usuario $u) => VistaJson::creada($this->registrar->ejecutar($e, $u))); }
    public function asociar(Peticion $p, string $medicoId): array { return $this->conCuerpo($p, fn (array $e, Usuario $u) => VistaJson::creada($this->asociar->ejecutar($medicoId, $e, $u))); }
    public function disponibilidad(Peticion $p): array {
        $q = fn (string $k) => (string) ($p->consulta[$k] ?? '');
        return $this->conUsuario($p, fn (Usuario $u) => VistaJson::lista($this->disponibilidad->ejecutar($q('especialidad'), $q('desde'), $q('hasta'), $u)));
    }
    private function conCuerpo(Peticion $p, callable $accion): array {
        return $this->conUsuario($p, fn (Usuario $u) => is_array($e = json_decode($p->cuerpo, true)) ? $accion($e, $u) : VistaJson::error('json_invalido'));
    }
    private function conUsuario(Peticion $p, callable $accion): array {
        $usuario = $this->usuarios[$p->usuarioDemo] ?? null;
        if ($usuario === null) { return VistaJson::error('sin_sesion'); }
        try { return $accion($usuario); }
        catch (ErrorDominio $e) { return VistaJson::error($e->codigo, $e->getMessage()); }
        catch (ErrorPersistencia) { return VistaJson::error('error_persistencia', 'No se pudo completar; intente de nuevo'); }
    }
}
