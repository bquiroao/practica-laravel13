<?php

declare(strict_types=1);

namespace NotasSoap\EnMemoria;

use NotasSoap\Contratos\RepositorioDiagnosticos;
use NotasSoap\Dominio\Diagnostico;

/** Doble de prueba: simula que la base de datos rechaza el INSERT del diagnóstico. */
final class RepositorioDiagnosticosQueFalla implements RepositorioDiagnosticos, Restaurable
{
    public function guardar(Diagnostico $diagnostico): Diagnostico
    {
        throw new \RuntimeException('SQLSTATE[08006]: conexión perdida');
    }

    public function instantanea(): array
    {
        return [];
    }

    public function restaurar(array $estado): void
    {
    }
}
