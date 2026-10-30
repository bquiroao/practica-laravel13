<?php

declare(strict_types=1);

namespace E2e\Presentacion\Http\Controladores;

use E2e\Aplicacion\CasosDeUso\CerrarEjecucion;
use E2e\Aplicacion\CasosDeUso\ReportarResultado;
use E2e\Presentacion\Http\Formularios\EntradaInvalida;
use E2e\Presentacion\Http\Formularios\FormularioResultado;
use E2e\Presentacion\Http\Identidad;
use E2e\Presentacion\Http\Respuesta;
use E2e\Presentacion\Http\Solicitud;
use E2e\Presentacion\Http\Vistas\ErrorVista;
use E2e\Presentacion\Http\Vistas\SalidaVista;

/** La C de MVC para /ejecuciones: reportar un resultado y cerrar. Sin SQL ni reglas (ARQ-06). */
final class EjecucionesControlador
{
    public function __construct(
        private readonly ReportarResultado $reportar,
        private readonly CerrarEjecucion $cerrar,
        private readonly SalidaVista $vista = new SalidaVista(),
        private readonly ErrorVista $error = new ErrorVista(),
    ) {
    }

    /** @param array<string, string> $p */
    public function reportar(Solicitud $s, Identidad $i, array $p): Respuesta
    {
        try {
            $f = FormularioResultado::desde($s);
        } catch (EntradaInvalida $e) {
            return $this->error->entrada($e);
        }

        return $this->vista->mostrar($this->reportar->ejecutar($i->usuario, $i->tenant, (int) $p['id'], $f['clave'], $f['datos']));
    }

    /** @param array<string, string> $p */
    public function cerrar(Solicitud $s, Identidad $i, array $p): Respuesta
    {
        try {
            $motivo = FormularioResultado::cierre($s);
        } catch (EntradaInvalida $e) {
            return $this->error->entrada($e);
        }

        return $this->vista->mostrar($this->cerrar->ejecutar($i->usuario, $i->tenant, (int) $p['id'], $motivo));
    }
}
