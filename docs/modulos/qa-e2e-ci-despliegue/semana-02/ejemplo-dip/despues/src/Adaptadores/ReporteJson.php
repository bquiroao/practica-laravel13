<?php

declare(strict_types=1);

namespace E2e\Adaptadores;

use E2e\Dominio\Ejecucion;
use E2e\Dominio\FuenteResultados;
use E2e\Dominio\ReglaRechazada;
use E2e\Dominio\Resultado;
use E2e\Dominio\ResultadoEscenario;

/**
 * Otro detalle: el JSON que exporta otro proveedor de CI (estados «success / failed / flaky / skipped»).
 * Existe para demostrar RNF-03: cambiar de proveedor es escribir ESTA clase; la regla de calidad no se toca.
 */
final class ReporteJson implements FuenteResultados
{
    private const ESTADOS = ['success' => Resultado::PASA, 'failed' => Resultado::FALLA, 'flaky' => Resultado::INESTABLE, 'skipped' => Resultado::OMITIDO];

    public function __construct(private readonly string $directorio)
    {
    }

    public function ejecucion(string $id): Ejecucion
    {
        $archivo = $this->directorio . '/' . basename($id) . '.json';
        $datos = is_file($archivo) ? json_decode((string) file_get_contents($archivo), true) : null;
        if (!is_array($datos)) {
            throw new ReglaRechazada('EJECUCION_INEXISTENTE');
        }
        $resultados = array_map(static fn (array $t): ResultadoEscenario => new ResultadoEscenario($t['name'], in_array('critical', $t['labels'], true),
            self::ESTADOS[$t['status']] ?? throw new ReglaRechazada('ESTADO_DESCONOCIDO'), $t['attempts'], array_column($t['artifacts'], 'sha256')),
            $datos['tests']);

        return new Ejecucion($id, $datos['artifact_digest'], $datos['state'] === 'finished', new \DateTimeImmutable($datos['finished_at']), $resultados);
    }
}
