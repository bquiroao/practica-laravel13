<?php

declare(strict_types=1);

namespace Qa\Presentacion\Http\Controladores;

use Qa\Aplicacion\CasosDeUso\ClasificarDefecto;
use Qa\Aplicacion\CasosDeUso\MarcarCorregido;
use Qa\Presentacion\Http\Formularios\EntradaInvalida;
use Qa\Presentacion\Http\Formularios\FormularioBuild;
use Qa\Presentacion\Http\Formularios\FormularioClasificacion;
use Qa\Presentacion\Http\Identidad;
use Qa\Presentacion\Http\Respuesta;
use Qa\Presentacion\Http\Solicitud;
use Qa\Presentacion\Http\Vistas\ErrorVista;
use Qa\Presentacion\Http\Vistas\SalidaVista;

/** La C de MVC para los defectos: clasificar (Líder QA) y marcar corregido (Desarrollador). */
final class DefectosControlador
{
    public function __construct(
        private readonly ClasificarDefecto $clasificar,
        private readonly MarcarCorregido $corregir,
        private readonly SalidaVista $vista = new SalidaVista(),
        private readonly ErrorVista $error = new ErrorVista(),
    ) {
    }

    /** @param array<string, string> $p */
    public function clasificar(Solicitud $s, Identidad $i, array $p): Respuesta
    {
        try {
            $datos = FormularioClasificacion::desde($s);
        } catch (EntradaInvalida $e) {
            return $this->error->entrada($e);
        }

        return $this->vista->mostrar($this->clasificar->ejecutar($i->usuario, $i->tenant, (int) $p['id'], $datos));
    }

    /** @param array<string, string> $p */
    public function corregir(Solicitud $s, Identidad $i, array $p): Respuesta
    {
        try {
            $build = FormularioBuild::desde($s);
        } catch (EntradaInvalida $e) {
            return $this->error->entrada($e);
        }

        return $this->vista->mostrar($this->corregir->ejecutar($i->usuario, $i->tenant, (int) $p['id'], $build));
    }
}
