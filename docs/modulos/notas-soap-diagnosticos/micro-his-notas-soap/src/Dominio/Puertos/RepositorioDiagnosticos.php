<?php

declare(strict_types=1);

namespace NotasSoap\Dominio\Puertos;

use NotasSoap\Dominio\Modelo\Diagnostico;

/** Puerto de persistencia de diagnósticos (diagnoses). */
interface RepositorioDiagnosticos
{
    public function guardar(Diagnostico $diagnostico): void;

    /** @return list<Diagnostico> */
    public function deNota(string $notaId): array;
}
