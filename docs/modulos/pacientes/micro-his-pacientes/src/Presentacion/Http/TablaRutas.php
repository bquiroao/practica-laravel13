<?php

declare(strict_types=1);

namespace Pacientes\Presentacion\Http;

use Pacientes\Aplicacion\CasosDeUso\ActualizarPaciente;
use Pacientes\Aplicacion\CasosDeUso\BuscarPacientes;
use Pacientes\Aplicacion\CasosDeUso\ConsultarPaciente;
use Pacientes\Aplicacion\CasosDeUso\RegistrarPaciente;
use Pacientes\Presentacion\Http\Acciones\ActualizarPacienteAccion;
use Pacientes\Presentacion\Http\Acciones\BuscarPacientesAccion;
use Pacientes\Presentacion\Http\Acciones\ConsultarPacienteAccion;
use Pacientes\Presentacion\Http\Acciones\RegistrarPacienteAccion;

final class TablaRutas
{
    /** @return list<Ruta> */
    public static function crear(RegistrarPaciente $registrar, BuscarPacientes $buscar, ConsultarPaciente $consultar, ActualizarPaciente $actualizar): array
    {
        $codigo = '(?<codigo>[A-Za-z0-9-]{1,20})';

        return [
            new Ruta('POST', '#^/api/v1/pacientes$#', new RegistrarPacienteAccion($registrar)),
            new Ruta('GET', '#^/api/v1/pacientes$#', new BuscarPacientesAccion($buscar)),
            new Ruta('GET', '#^/api/v1/pacientes/' . $codigo . '$#', new ConsultarPacienteAccion($consultar)),
            new Ruta('PUT', '#^/api/v1/pacientes/' . $codigo . '$#', new ActualizarPacienteAccion($actualizar)),
        ];
    }
}
