<?php

declare(strict_types=1);

namespace E2e\Presentacion\Http\Controladores;

use E2e\Aplicacion\CasosDeUso\RegistrarEscenario;
use E2e\Presentacion\Http\Formularios\EntradaInvalida;
use E2e\Presentacion\Http\Formularios\FormularioEscenario;
use E2e\Presentacion\Http\Identidad;
use E2e\Presentacion\Http\Respuesta;
use E2e\Presentacion\Http\Solicitud;
use E2e\Presentacion\Http\Vistas\ErrorVista;
use E2e\Presentacion\Http\Vistas\SalidaVista;

/** La C de MVC para los escenarios: formulario → caso de uso → vista. Sin SQL ni reglas (ARQ-06). */
final class EscenariosControlador
{
    public function __construct(
        private readonly RegistrarEscenario $registrar,
        private readonly SalidaVista $vista = new SalidaVista(),
        private readonly ErrorVista $error = new ErrorVista(),
    ) {
    }

    public function registrar(Solicitud $s, Identidad $i): Respuesta
    {
        try {
            $datos = FormularioEscenario::desde($s);
        } catch (EntradaInvalida $e) {
            return $this->error->entrada($e);
        }

        return $this->vista->mostrar($this->registrar->ejecutar($i->usuario, $i->tenant, $datos));
    }
}
