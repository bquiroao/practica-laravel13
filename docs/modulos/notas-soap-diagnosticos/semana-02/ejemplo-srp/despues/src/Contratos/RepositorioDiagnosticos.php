<?php

declare(strict_types=1);

namespace NotasSoap\Contratos;

use NotasSoap\Dominio\Diagnostico;

/** Persistencia de diagnósticos (diagnoses). */
interface RepositorioDiagnosticos
{
    public function guardar(Diagnostico $diagnostico): Diagnostico;
}
