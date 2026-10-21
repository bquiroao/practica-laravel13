<?php

declare(strict_types=1);

namespace Pacientes\Presentacion\Http\Acciones;

use Pacientes\Aplicacion\CasosDeUso\BuscarPacientes;
use Pacientes\Aplicacion\Comandos\ComandoBuscar;
use Pacientes\Presentacion\Http\Accion;
use Pacientes\Presentacion\Http\Identidad;
use Pacientes\Presentacion\Http\Respuesta;
use Pacientes\Presentacion\Http\Solicitud;
use Pacientes\Presentacion\Http\TraductorResultado;

/** GET /api/v1/pacientes?nombre=&nacimiento=&codigo=&tipo_documento=&numero_documento=&pais_documento= */
final class BuscarPacientesAccion implements Accion
{
    private const CRITERIOS = ['codigo', 'tipo_documento', 'numero_documento', 'pais_documento', 'nombre', 'nacimiento'];

    public function __construct(private readonly BuscarPacientes $buscar)
    {
    }

    public function ejecutar(Solicitud $solicitud, Identidad $identidad, array $parametros): Respuesta
    {
        $criterios = array_intersect_key($solicitud->consulta, array_flip(self::CRITERIOS));

        return TraductorResultado::aRespuesta($this->buscar->ejecutar(new ComandoBuscar($identidad->actor, $identidad->hospital, $criterios)));
    }
}
