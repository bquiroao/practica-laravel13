<?php
declare(strict_types=1);

namespace MicroHis\Application;

use MicroHis\Domain\DatosNoDisponibles;
use MicroHis\Domain\Filtros;
use MicroHis\Domain\ReportRegistry;
use MicroHis\Domain\ReporteNoExiste;
use MicroHis\Domain\SinDatos;

/** Caso de uso: cálculo y visualización trazable de un indicador, en sus tres niveles. */
final class CalcularIndicador
{
    private const NIVELES = ['indicador', 'desglose', 'registros'];

    public function __construct(private readonly ReportRegistry $reportes, private readonly PoliticaPermisos $politica)
    {
    }

    public function ejecutar(
        string $rol,
        string $tipo,
        Filtros $filtros,
        string $nivel = 'indicador',
        ?string $dimension = null,
        ?string $parte = null,
    ): ResultadoConsulta {
        try {
            if (!in_array($nivel, self::NIVELES, true)) {
                throw new \InvalidArgumentException("Nivel «{$nivel}» no válido: use indicador, desglose o registros");
            }
            $this->politica->exigir($rol, $tipo);
            $reporte = $this->reportes->obtener($tipo);
            $indicador = $reporte->calcular($filtros);
            $dimension ??= $reporte->dimensiones()[0];
            $desglose = $nivel === 'indicador' ? null : $reporte->desglosar($filtros, $dimension);
            $registros = null;
            if ($nivel === 'registros') {
                $registros = $reporte->registros($filtros, $dimension, $parte ?? throw new \InvalidArgumentException('Indique la parte del desglose a rastrear'));
            }
            return ResultadoConsulta::exito($indicador, $desglose, $registros);
        } catch (AccesoDenegado $e) {
            return ResultadoConsulta::error(403, $e->getMessage());
        } catch (ReporteNoExiste $e) {
            return ResultadoConsulta::error(404, $e->getMessage());
        } catch (SinDatos $e) {
            return ResultadoConsulta::sinDatos($e->getMessage());
        } catch (\InvalidArgumentException $e) {
            return ResultadoConsulta::error(422, $e->getMessage());
        } catch (DatosNoDisponibles) {
            // El detalle técnico (SQLSTATE, host, puerto) nunca llega al usuario.
            return ResultadoConsulta::error(503, 'No se pudieron obtener los datos en este momento. Intente de nuevo en unos minutos.');
        }
    }
}
