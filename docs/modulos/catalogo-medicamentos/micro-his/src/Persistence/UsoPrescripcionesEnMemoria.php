<?php
declare(strict_types=1);

namespace Catalogo\Persistence;

use Catalogo\Domain\Modelo\CodigoMedicamento;
use Catalogo\Domain\Puertos\ConsultaUsoPrescripciones;

/** Puerto RN-02 en memoria: lista fija de [hospital, código, referencia] ficticia. */
final class UsoPrescripcionesEnMemoria implements ConsultaUsoPrescripciones
{
    /** @param list<array{0: string, 1: string, 2: string}> $referencias */
    public function __construct(private readonly array $referencias = DatosEjemplo::REFERENCIAS)
    {
    }

    public function fueUsado(string $hospitalId, CodigoMedicamento $codigo): bool
    {
        foreach ($this->referencias as [$hospital, $codigoUsado]) {
            if ($hospital === $hospitalId && $codigoUsado === $codigo->valor) {
                return true;
            }
        }
        return false;
    }
}
