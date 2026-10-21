<?php

declare(strict_types=1);

namespace Pacientes\Presentacion\Http\Acciones;

use Pacientes\Aplicacion\CasosDeUso\ConsultarPaciente;
use Pacientes\Aplicacion\Comandos\ComandoConsultar;
use Pacientes\Presentacion\Http\Accion;
use Pacientes\Presentacion\Http\Identidad;
use Pacientes\Presentacion\Http\Respuesta;
use Pacientes\Presentacion\Http\Solicitud;
use Pacientes\Presentacion\Http\TraductorResultado;

/** GET /api/v1/pacientes/{codigo}: la versión viaja en ETag para usarla luego en If-Match. */
final class ConsultarPacienteAccion implements Accion
{
    public function __construct(private readonly ConsultarPaciente $consultar)
    {
    }

    public function ejecutar(Solicitud $solicitud, Identidad $identidad, array $parametros): Respuesta
    {
        $resultado = $this->consultar->ejecutar(new ComandoConsultar($identidad->actor, $identidad->hospital, $parametros['codigo']));

        return TraductorResultado::aRespuesta($resultado, $resultado->exitoso()
            ? ['ETag' => '"' . $resultado->datos['paciente']['version'] . '"']
            : []);
    }
}
