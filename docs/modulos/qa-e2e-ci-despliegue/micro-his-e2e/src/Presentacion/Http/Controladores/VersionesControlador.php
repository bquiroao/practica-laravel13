<?php

declare(strict_types=1);

namespace E2e\Presentacion\Http\Controladores;

use E2e\Aplicacion\CasosDeUso\ConsultarTrazabilidad;
use E2e\Aplicacion\CasosDeUso\EvaluarGate;
use E2e\Aplicacion\CasosDeUso\IniciarEjecucion;
use E2e\Aplicacion\CasosDeUso\PlanificarPruebas;
use E2e\Aplicacion\CasosDeUso\RegistrarDespliegue;
use E2e\Aplicacion\CasosDeUso\RegistrarVersion;
use E2e\Presentacion\Http\Formularios\EntradaInvalida;
use E2e\Presentacion\Http\Formularios\FormularioVersion;
use E2e\Presentacion\Http\Identidad;
use E2e\Presentacion\Http\Respuesta;
use E2e\Presentacion\Http\Solicitud;
use E2e\Presentacion\Http\Vistas\ErrorVista;
use E2e\Presentacion\Http\Vistas\SalidaVista;

/** La C de MVC para /versiones: registrar, plan, iniciar ejecución, gate y trazabilidad. Sin SQL ni reglas (ARQ-06). */
final class VersionesControlador
{
    public function __construct(
        private readonly RegistrarVersion $registrar,
        private readonly PlanificarPruebas $planificar,
        private readonly IniciarEjecucion $iniciar,
        private readonly EvaluarGate $gate,
        private readonly ConsultarTrazabilidad $trazabilidad,
        private readonly RegistrarDespliegue $desplegar,
        private readonly SalidaVista $vista = new SalidaVista(),
        private readonly ErrorVista $error = new ErrorVista(),
    ) {
    }

    public function registrar(Solicitud $s, Identidad $i): Respuesta
    {
        try {
            $datos = FormularioVersion::registrar($s);
        } catch (EntradaInvalida $e) {
            return $this->error->entrada($e);
        }
        $salida = $this->registrar->ejecutar($i->usuario, $i->tenant, $datos);

        return $this->vista->mostrar($salida, isset($salida->datos['numero']) ? Rutas::BASE . '/versiones/' . $salida->datos['numero'] . '/trazabilidad' : null);
    }

    /** @param array<string, string> $p */
    public function planificar(Solicitud $s, Identidad $i, array $p): Respuesta
    {
        try {
            $propuesta = FormularioVersion::plan($s);
        } catch (EntradaInvalida $e) {
            return $this->error->entrada($e);
        }

        return $this->vista->mostrar($this->planificar->ejecutar($i->usuario, $i->tenant, $p['version'], $propuesta));
    }

    /** @param array<string, string> $p */
    public function iniciarEjecucion(Solicitud $s, Identidad $i, array $p): Respuesta
    {
        try {
            $d = FormularioVersion::ejecucion($s);
        } catch (EntradaInvalida $e) {
            return $this->error->entrada($e);
        }
        $salida = $this->iniciar->ejecutar($i->usuario, $i->tenant, $p['version'], $d['digest'], $d['entorno']);

        return $this->vista->mostrar($salida, isset($salida->datos['id']) ? Rutas::BASE . '/ejecuciones/' . $salida->datos['id'] : null);
    }

    /** @param array<string, string> $p */
    public function evaluarGate(Solicitud $s, Identidad $i, array $p): Respuesta
    {
        try {
            $ejecucion = FormularioVersion::gate($s);
        } catch (EntradaInvalida $e) {
            return $this->error->entrada($e);
        }

        return $this->vista->mostrar($this->gate->ejecutar($i->usuario, $i->tenant, $p['version'], $ejecucion));
    }

    /** @param array<string, string> $p semana 12 (B-01): lo que el gate decidiría ahora, sin guardar nada */
    public function previsualizarGate(Solicitud $s, Identidad $i, array $p): Respuesta
    {
        try {
            $ejecucion = FormularioVersion::gate($s);
        } catch (EntradaInvalida $e) {
            return $this->error->entrada($e);
        }

        return $this->vista->mostrar($this->gate->previsualizar($i->usuario, $i->tenant, $p['version'], $ejecucion));
    }

    /** @param array<string, string> $p semana 6: CU-11 */
    public function desplegar(Solicitud $s, Identidad $i, array $p): Respuesta
    {
        try {
            $d = FormularioVersion::despliegue($s);
        } catch (EntradaInvalida $e) {
            return $this->error->entrada($e);
        }

        return $this->vista->mostrar($this->desplegar->ejecutar($i->usuario, $i->tenant, $p['version'], $d));
    }

    /** @param array<string, string> $p */
    public function trazabilidad(Solicitud $s, Identidad $i, array $p): Respuesta
    {
        return $this->vista->mostrar($this->trazabilidad->ejecutar($i->usuario, $i->tenant, $p['version']));
    }
}
