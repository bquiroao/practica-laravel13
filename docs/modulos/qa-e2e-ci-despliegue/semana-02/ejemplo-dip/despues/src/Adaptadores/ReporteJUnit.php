<?php

declare(strict_types=1);

namespace E2e\Adaptadores;

use E2e\Dominio\Ejecucion;
use E2e\Dominio\FuenteResultados;
use E2e\Dominio\ReglaRechazada;
use E2e\Dominio\Resultado;
use E2e\Dominio\ResultadoEscenario;

/**
 * Detalle: el reporte JUnit XML que deja el runner E2E (Playwright, con las anotaciones como propiedades).
 * Traduce SU vocabulario (testcase, failure, skipped, tag @critico, propiedad «intentos») al del dominio.
 * Un archivo por ejecución: <directorio>/<ejecucion>.xml.
 */
final class ReporteJUnit implements FuenteResultados
{
    public function __construct(private readonly string $directorio)
    {
    }

    public function ejecucion(string $id): Ejecucion
    {
        $archivo = $this->directorio . '/' . basename($id) . '.xml';
        $xml = is_file($archivo) ? simplexml_load_file($archivo) : false;
        if ($xml === false) {
            throw new ReglaRechazada('EJECUCION_INEXISTENTE');
        }
        $suite = $xml->testsuite;
        $prop = static fn (\SimpleXMLElement $nodo, string $nombre): array => array_map('strval',
            $nodo->xpath("properties/property[@name='$nombre']/@value") ?: []);
        $resultados = [];
        foreach ($suite->testcase as $caso) {
            $intentos = (int) ($prop($caso, 'intentos')[0] ?? 1);
            $resultado = match (true) {
                isset($caso->skipped) => Resultado::OMITIDO,
                isset($caso->failure) => Resultado::FALLA,
                $intentos > 1 => Resultado::INESTABLE,
                default => Resultado::PASA,
            };
            $resultados[] = new ResultadoEscenario(trim(str_replace('@critico', '', (string) $caso['name'])),
                str_contains((string) $caso['name'], '@critico'), $resultado, $intentos, $prop($caso, 'evidencia'));
        }

        return new Ejecucion($id, $prop($suite, 'digest')[0] ?? '', ($prop($suite, 'estado')[0] ?? '') === 'completada',
            new \DateTimeImmutable((string) $suite['timestamp']), $resultados);
    }
}
