<?php

declare(strict_types=1);

namespace Qa\Dominio\Evidencia;

/**
 * Resultado de una suite automática en formato JUnit XML (PHPUnit lo genera con --log-junit). Agrega resumen():
 * es una EXTENSIÓN del contrato, no un cambio; quien no la conoce lo usa como cualquier otra evidencia.
 */
final class ResultadoJUnit extends EvidenciaInmutable
{
    /** @var array{pruebas: int, fallas: int, errores: int} */
    private readonly array $resumen;

    public function __construct(string $xml, Clasificacion $clasificacion = Clasificacion::GENERAL)
    {
        $anterior = libxml_use_internal_errors(true);
        $doc = simplexml_load_string($xml);
        libxml_use_internal_errors($anterior);
        $raiz = $doc === false ? null : ($doc->getName() === 'testsuites' ? ($doc->testsuite[0] ?? $doc) : $doc);
        if ($raiz === null || !isset($raiz['tests'])) {
            throw new EvidenciaInvalida('TIPO_NO_PERMITIDO');
        }
        $this->resumen = ['pruebas' => (int) $raiz['tests'], 'fallas' => (int) ($raiz['failures'] ?? 0), 'errores' => (int) ($raiz['errors'] ?? 0)];
        parent::__construct($xml, $clasificacion);
    }

    public function tipo(): string
    {
        return 'junit';
    }

    /** @return array{pruebas: int, fallas: int, errores: int} */
    public function resumen(): array
    {
        return $this->resumen;
    }
}
