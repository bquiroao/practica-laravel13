<?php
declare(strict_types=1);

namespace Catalogo\Presentation\Web;

use Catalogo\Application\MedicamentoVista;
use Catalogo\Application\OpcionesCatalogo;
use Catalogo\Domain\Errores\ErrorDominio;
use Catalogo\Domain\Errores\ErrorNoEncontrado;
use Catalogo\Domain\Errores\ErrorPersistencia;
use Catalogo\Domain\Errores\ErrorReglaNegocio;
use Catalogo\Domain\Errores\ErrorValidacion;

/**
 * Controlador MVC. Solo: leer la petición, llamar a UN caso de uso, elegir vista y código HTTP.
 * Sin SQL y sin reglas de negocio (lo verifica pruebas/unitarias/arquitectura.php).
 */
final class ControladorMedicamentos
{
    private const AVISOS = [
        'registrado' => 'Medicamento registrado.',
        'version' => 'Nueva versión registrada. La anterior quedó cerrada.',
        'baja' => 'Medicamento dado de baja. Su historial se conserva.',
        'eliminado' => 'Registro eliminado.',
    ];
    private const CAMPOS = ['nombre', 'principio_activo', 'concentracion_valor', 'concentracion_unidad', 'forma_farmaceutica',
        'via_administracion', 'presentacion', 'registro_sanitario', 'vigente_desde'];

    /** @param array<string, object> $app casos de uso del Composition Root */
    public function __construct(
        private readonly array $app,
        private readonly Vista $vista,
        private readonly Csrf $csrf,
        private readonly string $hospitalId,
        private readonly string $usuario,
    ) {
    }

    public function buscar(Peticion $p): Respuesta
    {
        $filtros = array_intersect_key($p->consulta, array_flip(['q', 'forma', 'fecha']));
        $datos = ['filtros' => $filtros, 'resultados' => null, 'errores' => [], 'aviso' => $this->aviso($p), 'opciones' => OpcionesCatalogo::todas()];
        if (trim((string) ($filtros['q'] ?? '')) === '' && ($filtros['forma'] ?? '') === '') {
            return $this->pagina(200, 'lista', $datos, 'Buscar medicamentos');
        }
        try {
            $datos['resultados'] = MedicamentoVista::lista($this->app['buscar']->ejecutar($this->hospitalId, $filtros));
            return $this->pagina(200, 'lista', $datos, 'Resultados de búsqueda');
        } catch (ErrorDominio $e) {
            return $this->fallo($e, 'lista', $datos, 'Buscar medicamentos');
        }
    }

    public function nuevo(Peticion $p): Respuesta
    {
        return $this->formulario(200, ['vigente_desde' => ''], [], null);
    }

    public function registrar(Peticion $p): Respuesta
    {
        $datos = $this->campos($p);
        if (!$this->csrf->valido($p->cuerpo['csrf'] ?? null)) {
            return $this->error(403, 'La sesión del formulario expiró. Recargue la página e intente de nuevo.');
        }
        try {
            $m = $this->app['registrar']->ejecutar($this->hospitalId, $datos, $this->usuario);
            return Respuesta::redirigir('/medicamentos/' . rawurlencode($m->codigo->valor) . '?aviso=registrado');
        } catch (ErrorDominio $e) {
            return $this->fallo($e, 'formulario', ['valores' => $datos, 'codigo' => null, 'csrf' => $this->csrf->token()], 'Registrar medicamento');
        }
    }

    public function detalle(Peticion $p, string $codigo): Respuesta
    {
        return $this->paginaDetalle(200, $codigo, $this->aviso($p), null, []);
    }

    public function formularioVersion(Peticion $p, string $codigo): Respuesta
    {
        try {
            $versiones = $this->app['historial']->ejecutar($this->hospitalId, $codigo);
            $ultima = MedicamentoVista::aArreglo($versiones[array_key_last($versiones)]);
            return $this->formulario(200, ['vigente_desde' => ''] + $ultima, [], $ultima['codigo']);
        } catch (ErrorDominio $e) {
            return $this->fallo($e, 'error', [], 'Nueva versión');
        }
    }

    public function registrarVersion(Peticion $p, string $codigo): Respuesta
    {
        $datos = $this->campos($p);
        if (!$this->csrf->valido($p->cuerpo['csrf'] ?? null)) {
            return $this->error(403, 'La sesión del formulario expiró. Recargue la página e intente de nuevo.');
        }
        try {
            $m = $this->app['nueva_version']->ejecutar($this->hospitalId, $codigo, $datos, $this->usuario);
            return Respuesta::redirigir('/medicamentos/' . rawurlencode($m->codigo->valor) . '?aviso=version');
        } catch (ErrorDominio $e) {
            return $this->fallo($e, 'formulario', ['valores' => $datos, 'codigo' => $codigo, 'csrf' => $this->csrf->token()], 'Nueva versión');
        }
    }

    public function baja(Peticion $p, string $codigo): Respuesta
    {
        return $this->accionSobreCodigo($p, $codigo, 'baja', fn () => $this->app['baja']->ejecutar($this->hospitalId, $codigo, $p->cuerpo['fecha_baja'] ?? null, $p->cuerpo['motivo'] ?? null));
    }

    public function eliminar(Peticion $p, string $codigo): Respuesta
    {
        return $this->accionSobreCodigo($p, $codigo, 'eliminado', fn () => $this->app['eliminar']->ejecutar($this->hospitalId, $codigo), '/medicamentos');
    }

    public function error(int $estado, string $mensaje): Respuesta
    {
        return $this->pagina($estado, 'error', ['mensaje' => $mensaje], 'Error');
    }

    private function accionSobreCodigo(Peticion $p, string $codigo, string $aviso, callable $accion, ?string $destino = null): Respuesta
    {
        if (!$this->csrf->valido($p->cuerpo['csrf'] ?? null)) {
            return $this->error(403, 'La sesión del formulario expiró. Recargue la página e intente de nuevo.');
        }
        try {
            $accion();
            return Respuesta::redirigir(($destino ?? '/medicamentos/' . rawurlencode($codigo)) . "?aviso=$aviso");
        } catch (ErrorValidacion | ErrorReglaNegocio $e) {
            // El error se muestra en la misma página de detalle, junto a la acción que lo causó.
            return $this->paginaDetalle($e instanceof ErrorValidacion ? 422 : 409, $codigo, null, $e->getMessage(), $e->detalles);
        } catch (ErrorDominio $e) {
            return $this->fallo($e, 'error', [], 'Medicamento');
        }
    }

    /** @param array<string, string> $errores */
    private function paginaDetalle(int $estado, string $codigo, ?string $aviso, ?string $mensaje, array $errores): Respuesta
    {
        try {
            $versiones = MedicamentoVista::lista($this->app['historial']->ejecutar($this->hospitalId, $codigo), $this->app['reloj']->hoy());
        } catch (ErrorDominio $e) {
            return $this->fallo($e, 'error', [], 'Medicamento');
        }
        $datos = ['versiones' => $versiones, 'codigo' => $versiones[0]['codigo'], 'aviso' => $aviso, 'mensaje' => $mensaje, 'errores' => $errores, 'csrf' => $this->csrf->token()];
        return $this->pagina($estado, 'detalle', $datos, "Medicamento {$versiones[0]['codigo']}");
    }

    /** @param array<string, string> $errores */
    private function formulario(int $estado, array $valores, array $errores, ?string $codigo, ?string $mensaje = null): Respuesta
    {
        $titulo = $codigo === null ? 'Registrar medicamento' : "Nueva versión de $codigo";
        return $this->pagina($estado, 'formulario', ['valores' => $valores, 'errores' => $errores, 'codigo' => $codigo, 'mensaje' => $mensaje,
            'csrf' => $this->csrf->token(), 'opciones' => OpcionesCatalogo::todas()], $titulo);
    }

    private function fallo(ErrorDominio $e, string $plantilla, array $datos, string $titulo): Respuesta
    {
        $estado = match (true) {
            $e instanceof ErrorValidacion => 422,
            $e instanceof ErrorReglaNegocio => 409,
            $e instanceof ErrorNoEncontrado => 404,
            $e instanceof ErrorPersistencia => 503,
            default => 500,
        };
        if ($plantilla === 'formulario') {
            return $this->formulario($estado, $datos['valores'], $e instanceof ErrorValidacion ? $e->detalles : [], $datos['codigo'], $e instanceof ErrorValidacion ? null : $e->getMessage());
        }
        if ($plantilla === 'lista' && $e instanceof ErrorValidacion) {
            return $this->pagina($estado, 'lista', ['errores' => $e->detalles] + $datos, $titulo);
        }
        return $this->error($estado, $e->getMessage());
    }

    private function pagina(int $estado, string $plantilla, array $datos, string $titulo): Respuesta
    {
        return new Respuesta($estado, $this->vista->mostrar($plantilla, $datos, $titulo));
    }

    /** @return array<string, string> */
    private function campos(Peticion $p): array
    {
        $datos = [];
        foreach (self::CAMPOS as $campo) {
            $datos[$campo] = is_string($p->cuerpo[$campo] ?? null) ? $p->cuerpo[$campo] : '';
        }
        return $datos;
    }

    private function aviso(Peticion $p): ?string
    {
        return self::AVISOS[$p->consulta['aviso'] ?? ''] ?? null;
    }
}
