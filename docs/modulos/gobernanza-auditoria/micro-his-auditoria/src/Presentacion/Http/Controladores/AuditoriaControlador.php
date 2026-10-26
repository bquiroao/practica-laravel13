<?php

declare(strict_types=1);

namespace Auditoria\Presentacion\Http\Controladores;

use Auditoria\Aplicacion\CasosDeUso\ConsultarMovimientos;
use Auditoria\Aplicacion\CasosDeUso\RegistrarMovimiento;
use Auditoria\Aplicacion\CasosDeUso\VerDetalleMovimiento;
use Auditoria\Aplicacion\CasosDeUso\VerificarIntegridad;
use Auditoria\Presentacion\Http\Formularios\EntradaInvalida;
use Auditoria\Presentacion\Http\Formularios\FormularioMovimientos;
use Auditoria\Presentacion\Http\Identidad;
use Auditoria\Presentacion\Http\Respuesta;
use Auditoria\Presentacion\Http\Solicitud;
use Auditoria\Presentacion\Http\Vistas\ErrorVista;
use Auditoria\Presentacion\Http\Vistas\MovimientosVista;

/**
 * MVC — Controlador. Cada acción hace exactamente tres cosas: formulario → caso de uso (Modelo) → vista.
 * Sin SQL, sin reglas de negocio, sin códigos HTTP y sin crear objetos de otras capas: lo verifica ARQ-07.
 */
final class AuditoriaControlador
{
    public function __construct(
        private readonly RegistrarMovimiento $registrar,
        private readonly ConsultarMovimientos $consultar,
        private readonly VerDetalleMovimiento $detalle,
        private readonly VerificarIntegridad $verificar,
    ) {
    }

    public function registrar(Solicitud $solicitud, Identidad $identidad): Respuesta
    {
        try {
            return MovimientosVista::registro($this->registrar->ejecutar(FormularioMovimientos::registro($solicitud, $identidad)));
        } catch (EntradaInvalida $e) {
            return ErrorVista::entradaInvalida($e);
        }
    }

    public function consultar(Solicitud $solicitud, Identidad $identidad): Respuesta
    {
        try {
            return MovimientosVista::lista($this->consultar->ejecutar(FormularioMovimientos::consulta($solicitud, $identidad)));
        } catch (EntradaInvalida $e) {
            return ErrorVista::entradaInvalida($e);
        }
    }

    /** @param array<string, string> $parametros */
    public function verDetalle(Solicitud $solicitud, Identidad $identidad, array $parametros): Respuesta
    {
        return MovimientosVista::detalle($this->detalle->ejecutar(FormularioMovimientos::detalle($solicitud, $identidad, $parametros)));
    }

    public function verificar(Solicitud $solicitud, Identidad $identidad): Respuesta
    {
        try {
            return MovimientosVista::verificacion($this->verificar->ejecutar(FormularioMovimientos::verificacion($solicitud, $identidad)));
        } catch (EntradaInvalida $e) {
            return ErrorVista::entradaInvalida($e);
        }
    }
}
