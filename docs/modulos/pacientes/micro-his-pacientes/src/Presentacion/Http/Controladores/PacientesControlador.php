<?php

declare(strict_types=1);

namespace Pacientes\Presentacion\Http\Controladores;

use Pacientes\Aplicacion\CasosDeUso\ActualizarPaciente;
use Pacientes\Aplicacion\CasosDeUso\BuscarPacientes;
use Pacientes\Aplicacion\CasosDeUso\ConsultarPaciente;
use Pacientes\Aplicacion\CasosDeUso\RegistrarPaciente;
use Pacientes\Presentacion\Http\Formularios\EntradaInvalida;
use Pacientes\Presentacion\Http\Formularios\FormularioPaciente;
use Pacientes\Presentacion\Http\Identidad;
use Pacientes\Presentacion\Http\Respuesta;
use Pacientes\Presentacion\Http\Solicitud;
use Pacientes\Presentacion\Http\Vistas\ErrorVista;
use Pacientes\Presentacion\Http\Vistas\PacienteVista;

/**
 * C de MVC: coordina formulario → caso de uso (Modelo) → vista.
 * No tiene SQL, ni reglas de negocio, ni crea objetos, ni arma JSON (ARQ-07, ARQ-08).
 */
final class PacientesControlador
{
    public function __construct(
        private readonly RegistrarPaciente $registrarPaciente,
        private readonly BuscarPacientes $buscarPacientes,
        private readonly ConsultarPaciente $consultarPaciente,
        private readonly ActualizarPaciente $actualizarPaciente,
    ) {
    }

    /** POST /api/v1/pacientes */
    public function registrar(Solicitud $solicitud, Identidad $identidad, array $parametros): Respuesta
    {
        try {
            $comando = FormularioPaciente::registro($solicitud, $identidad);
        } catch (EntradaInvalida $e) {
            return ErrorVista::entradaInvalida($e);
        }

        return PacienteVista::registrado($this->registrarPaciente->ejecutar($comando));
    }

    /** GET /api/v1/pacientes?… */
    public function buscar(Solicitud $solicitud, Identidad $identidad, array $parametros): Respuesta
    {
        return PacienteVista::lista($this->buscarPacientes->ejecutar(FormularioPaciente::busqueda($solicitud, $identidad)));
    }

    /** GET /api/v1/pacientes/{codigo} */
    public function consultar(Solicitud $solicitud, Identidad $identidad, array $parametros): Respuesta
    {
        return PacienteVista::detalle($this->consultarPaciente->ejecutar(FormularioPaciente::consulta($identidad, $parametros['codigo'])),
            $solicitud->cabecera('If-None-Match'));
    }

    /** PUT /api/v1/pacientes/{codigo} con If-Match */
    public function actualizar(Solicitud $solicitud, Identidad $identidad, array $parametros): Respuesta
    {
        try {
            $comando = FormularioPaciente::actualizacion($solicitud, $identidad, $parametros['codigo']);
        } catch (EntradaInvalida $e) {
            return ErrorVista::entradaInvalida($e);
        }

        return PacienteVista::actualizado($this->actualizarPaciente->ejecutar($comando));
    }
}
