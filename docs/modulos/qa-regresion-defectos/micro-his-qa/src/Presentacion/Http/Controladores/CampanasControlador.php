<?php

declare(strict_types=1);

namespace Qa\Presentacion\Http\Controladores;

use Qa\Aplicacion\CasosDeUso\CerrarCampana;
use Qa\Aplicacion\CasosDeUso\ConsultarTrazabilidad;
use Qa\Aplicacion\CasosDeUso\IniciarCampana;
use Qa\Aplicacion\CasosDeUso\PlanificarCampana;
use Qa\Aplicacion\CasosDeUso\RegistrarResultado;
use Qa\Presentacion\Http\Formularios\EntradaInvalida;
use Qa\Presentacion\Http\Formularios\FormularioBuild;
use Qa\Presentacion\Http\Formularios\FormularioCampana;
use Qa\Presentacion\Http\Formularios\FormularioResultado;
use Qa\Presentacion\Http\Identidad;
use Qa\Presentacion\Http\Respuesta;
use Qa\Presentacion\Http\Solicitud;
use Qa\Presentacion\Http\Vistas\ErrorVista;
use Qa\Presentacion\Http\Vistas\SalidaVista;

/** La C de MVC para campañas y resultados. Cada acción: formulario → caso de uso → vista. */
final class CampanasControlador
{
    public function __construct(
        private readonly PlanificarCampana $planificar,
        private readonly IniciarCampana $iniciar,
        private readonly RegistrarResultado $registrar,
        private readonly CerrarCampana $cerrar,
        private readonly ConsultarTrazabilidad $trazabilidad,
        private readonly SalidaVista $vista = new SalidaVista(),
        private readonly ErrorVista $error = new ErrorVista(),
    ) {
    }

    public function planificar(Solicitud $s, Identidad $i): Respuesta
    {
        try {
            $datos = FormularioCampana::desde($s);
        } catch (EntradaInvalida $e) {
            return $this->error->entrada($e);
        }
        $salida = $this->planificar->ejecutar($i->usuario, $i->tenant, $datos);

        return $this->vista->mostrar($salida, isset($salida->datos['id']) ? Rutas::BASE . '/campanas/' . $salida->datos['id'] . '/trazabilidad' : null);
    }

    /** @param array<string, string> $p */
    public function iniciar(Solicitud $s, Identidad $i, array $p): Respuesta
    {
        try {
            $build = FormularioBuild::desde($s);
        } catch (EntradaInvalida $e) {
            return $this->error->entrada($e);
        }

        return $this->vista->mostrar($this->iniciar->ejecutar($i->usuario, $i->tenant, (int) $p['id'], $build));
    }

    /** @param array<string, string> $p */
    public function registrar(Solicitud $s, Identidad $i, array $p): Respuesta
    {
        try {
            ['clave' => $clave, 'datos' => $datos] = FormularioResultado::desde($s);
        } catch (EntradaInvalida $e) {
            return $this->error->entrada($e);
        }

        return $this->vista->mostrar($this->registrar->ejecutar($i->usuario, $i->tenant, (int) $p['id'], $clave, $datos));
    }

    /** @param array<string, string> $p */
    public function cerrar(Solicitud $s, Identidad $i, array $p): Respuesta
    {
        return $this->vista->mostrar($this->cerrar->ejecutar($i->usuario, $i->tenant, (int) $p['id']));
    }

    /** @param array<string, string> $p */
    public function trazabilidad(Solicitud $s, Identidad $i, array $p): Respuesta
    {
        return $this->vista->mostrar($this->trazabilidad->ejecutar($i->usuario, $i->tenant, (int) $p['id']));
    }
}
