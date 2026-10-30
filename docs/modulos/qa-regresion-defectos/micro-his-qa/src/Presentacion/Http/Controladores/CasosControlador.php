<?php

declare(strict_types=1);

namespace Qa\Presentacion\Http\Controladores;

use Qa\Aplicacion\CasosDeUso\AprobarCaso;
use Qa\Aplicacion\CasosDeUso\DisenarCaso;
use Qa\Presentacion\Http\Formularios\EntradaInvalida;
use Qa\Presentacion\Http\Formularios\FormularioCaso;
use Qa\Presentacion\Http\Identidad;
use Qa\Presentacion\Http\Respuesta;
use Qa\Presentacion\Http\Solicitud;
use Qa\Presentacion\Http\Vistas\ErrorVista;
use Qa\Presentacion\Http\Vistas\SalidaVista;

/** La C de MVC para los casos de prueba: formulario → caso de uso → vista. Sin SQL ni reglas de QA (ARQ-08). */
final class CasosControlador
{
    public function __construct(
        private readonly DisenarCaso $disenar,
        private readonly AprobarCaso $aprobar,
        private readonly SalidaVista $vista = new SalidaVista(),
        private readonly ErrorVista $error = new ErrorVista(),
    ) {
    }

    public function disenar(Solicitud $s, Identidad $i): Respuesta
    {
        try {
            $datos = FormularioCaso::desde($s);
        } catch (EntradaInvalida $e) {
            return $this->error->entrada($e);
        }
        $salida = $this->disenar->ejecutar($i->usuario, $i->tenant, $datos);

        return $this->vista->mostrar($salida, isset($salida->datos['id']) ? Rutas::BASE . '/casos/' . $salida->datos['id'] : null);
    }

    /** @param array<string, string> $p */
    public function aprobar(Solicitud $s, Identidad $i, array $p): Respuesta
    {
        return $this->vista->mostrar($this->aprobar->ejecutar($i->usuario, $i->tenant, $p['id']));
    }
}
