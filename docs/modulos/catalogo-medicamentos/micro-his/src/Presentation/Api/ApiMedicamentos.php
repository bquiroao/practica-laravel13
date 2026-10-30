<?php
declare(strict_types=1);

namespace Catalogo\Presentation\Api;

use Catalogo\Application\MedicamentoVista;
use Catalogo\Application\OpcionesCatalogo;
use Catalogo\Presentation\Web\Peticion;
use Catalogo\Presentation\Web\Respuesta;

/**
 * Controlador JSON (semana 5). Igual que el MVC: permiso, UN caso de uso y respuesta.
 * El hospital y el usuario salen de la Identidad (token), nunca de la petición.
 */
final class ApiMedicamentos
{
    /** @param array<string, object> $app casos de uso del Composition Root */
    public function __construct(private readonly array $app, private readonly Identidad $quien)
    {
    }

    public function buscar(Peticion $p): Respuesta
    {
        $this->exigir('buscar');
        $filtros = array_intersect_key($p->consulta, array_flip(['q', 'forma', 'fecha']));
        $lista = $this->app['buscar']->ejecutar($this->quien->hospitalId, $filtros);
        $fecha = ($filtros['fecha'] ?? '') !== '' ? $filtros['fecha'] : $this->app['reloj']->hoy()->format('Y-m-d');
        return RespuestaJson::datos(200, MedicamentoVista::lista($lista), [], ['total' => count($lista), 'fecha' => $fecha, 'limite' => 50]);
    }

    public function consultar(Peticion $p, string $codigo): Respuesta
    {
        $this->exigir('consultar');
        $m = $this->app['consultar']->ejecutar($this->quien->hospitalId, $codigo, $p->consulta['fecha'] ?? null);
        return RespuestaJson::datos(200, MedicamentoVista::aArreglo($m));
    }

    public function historial(Peticion $p, string $codigo): Respuesta
    {
        $this->exigir('historial');
        return RespuestaJson::datos(200, MedicamentoVista::lista($this->app['historial']->ejecutar($this->quien->hospitalId, $codigo)));
    }

    public function registrar(Peticion $p): Respuesta
    {
        $this->exigir('registrar');
        $m = $this->app['registrar']->ejecutar($this->quien->hospitalId, CuerpoJson::leer($p), $this->quien->usuario);
        return RespuestaJson::datos(201, MedicamentoVista::aArreglo($m), ['Location' => '/api/v1/medicamentos/' . $m->codigo->valor]);
    }

    public function registrarVersion(Peticion $p, string $codigo): Respuesta
    {
        $this->exigir('version');
        $m = $this->app['nueva_version']->ejecutar($this->quien->hospitalId, $codigo, CuerpoJson::leer($p), $this->quien->usuario);
        return RespuestaJson::datos(201, MedicamentoVista::aArreglo($m), ['Location' => '/api/v1/medicamentos/' . $m->codigo->valor . '/versiones']);
    }

    public function baja(Peticion $p, string $codigo): Respuesta
    {
        $this->exigir('baja');
        $cuerpo = CuerpoJson::leer($p);
        $m = $this->app['baja']->ejecutar($this->quien->hospitalId, $codigo, $cuerpo['fecha_baja'] ?? null, $cuerpo['motivo'] ?? null);
        return RespuestaJson::datos(200, MedicamentoVista::aArreglo($m));
    }

    public function eliminar(Peticion $p, string $codigo): Respuesta
    {
        $this->exigir('eliminar');
        $this->app['eliminar']->ejecutar($this->quien->hospitalId, $codigo);
        return RespuestaJson::sinContenido();
    }

    public function opciones(Peticion $p): Respuesta
    {
        $this->exigir('opciones');
        return RespuestaJson::datos(200, OpcionesCatalogo::todas());
    }

    private function exigir(string $accion): void
    {
        if (!Permisos::permite($this->quien->rol, $accion)) {
            throw new ErrorHttp(403, 'PROHIBIDO', "El rol {$this->quien->rol} no puede realizar «{$accion}»");
        }
    }
}
