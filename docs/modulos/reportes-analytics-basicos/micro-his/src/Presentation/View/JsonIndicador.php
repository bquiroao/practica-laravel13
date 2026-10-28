<?php
declare(strict_types=1);

namespace MicroHis\Presentation\View;

use MicroHis\Application\ResultadoConsulta;

/** Vista JSON del contrato API v1: mismo resultado que la vista HTML, otro formato. */
final class JsonIndicador
{
    private const CODIGOS = [403 => 'SIN_PERMISO', 404 => 'NO_EXISTE', 422 => 'VALIDACION', 503 => 'DATOS_NO_DISPONIBLES'];

    public function datos(ResultadoConsulta $r, string $correlacion): array
    {
        if ($r->estado === 'error') {
            return ['error' => ['codigo' => self::CODIGOS[$r->codigoHttp] ?? 'ERROR', 'mensaje' => $r->mensaje, 'correlationId' => $correlacion]];
        }
        if ($r->estado === 'sin_datos') {
            return ['estado' => 'sin_datos', 'mensaje' => $r->mensaje, 'datos' => null];
        }
        $i = $r->indicador;
        $datos = [
            'indicador' => ['nombre' => $i->nombre, 'valor' => $i->valor, 'unidad' => $i->unidad, 'numerador' => $i->numerador],
            'trazabilidad' => $i->trazabilidad,
        ];
        if ($r->desglose !== null) {
            $datos['desglose'] = ['dimension' => $r->desglose->dimension, 'partes' => $r->desglose->partes, 'total' => $r->desglose->total()];
        }
        if ($r->registros !== null) {
            $datos['registros'] = array_map(static fn ($reg) => get_object_vars($reg), $r->registros);
        }
        return ['estado' => 'exito', 'datos' => $datos];
    }
}
