<?php
declare(strict_types=1);

namespace MicroHis\Presentation\Controller;

use MicroHis\Application\CalcularIndicador;
use MicroHis\Application\ResultadoConsulta;
use MicroHis\Domain\Filtros;
use MicroHis\Presentation\Http\Respuesta;
use MicroHis\Presentation\View\VistaIndicador;

/**
 * Controlador (MVC): traduce la petición, llama al modelo (caso de uso) y elige la vista.
 * Sin SQL ni reglas de negocio: eso vive en Domain y Persistence.
 * El rol llega por parámetro solo en este prototipo; en el SHI sale del token JWT.
 */
final class ReporteController
{
    public function __construct(private readonly CalcularIndicador $casoDeUso, private readonly VistaIndicador $vista)
    {
    }

    /** @param array<string,string> $peticion */
    public function mostrar(array $peticion): Respuesta
    {
        $consulta = [
            'reporte' => $peticion['reporte'] ?? 'ocupacion',
            'desde' => $peticion['desde'] ?? '2026-10-01',
            'hasta' => $peticion['hasta'] ?? '2026-10-31',
            'dimension' => $peticion['dimension'] ?? 'sala',
            'rol' => $peticion['rol'] ?? 'administrador',
        ];
        try {
            $filtros = new Filtros($consulta['desde'], $consulta['hasta']);
            $resultado = $this->casoDeUso->ejecutar(
                $consulta['rol'],
                $consulta['reporte'],
                $filtros,
                $peticion['nivel'] ?? 'indicador',
                $consulta['dimension'],
                $peticion['parte'] ?? null,
            );
        } catch (\InvalidArgumentException $e) {
            $resultado = ResultadoConsulta::error(422, $e->getMessage());
        }
        $parte = isset($peticion['parte']) ? ['parte' => $peticion['parte']] : [];
        return new Respuesta($resultado->codigoHttp, $this->vista->html($resultado, $consulta + $parte));
    }
}
