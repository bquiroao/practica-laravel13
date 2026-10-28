<?php

declare(strict_types=1);

namespace Prescripciones\Aplicacion\Puertos;

use Prescripciones\Dominio\Modelo\Alergia;
use Prescripciones\Dominio\Modelo\Medicamento;

interface CatalogoMedicamentos
{
    public function buscar(string $codigo): ?Medicamento;

    /** Grupo farmacológico del alérgeno (p. ej. penicilina → betalactámicos), o null si no se conoce. */
    public function grupoDelAlergeno(Alergia $alergia): ?string;
}
