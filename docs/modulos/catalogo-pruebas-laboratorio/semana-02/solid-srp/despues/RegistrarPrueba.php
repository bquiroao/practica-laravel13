<?php
declare(strict_types=1);

namespace Despues;

/** Responsabilidad única: coordinar el caso de uso CU-01. No valida, no decide versiones, no guarda ni formatea. */
final class RegistrarPrueba
{
    public function __construct(private readonly RepositorioPruebas $repositorio)
    {
    }

    /** @return array la versión guardada; el texto para la pantalla lo arma la capa de presentación */
    public function ejecutar(array $d, string $hoy): array
    {
        $errores = Requisitos::errores($d, $hoy);
        if ($errores !== []) {
            throw new \InvalidArgumentException(implode(', ', $errores));
        }
        [$version, $cierre] = PoliticaVersionado::decidir($this->repositorio->ultimaVersion($d['codigo']), $d['vigente_desde']);
        $nueva = [
            'code' => $d['codigo'], 'version' => $version, 'min_volume_ml' => $d['volumen_minimo_ml'],
            'valid_from' => $d['vigente_desde'], 'valid_to' => null,
        ];
        $this->repositorio->guardar($nueva, $cierre);
        return $nueva;
    }
}
