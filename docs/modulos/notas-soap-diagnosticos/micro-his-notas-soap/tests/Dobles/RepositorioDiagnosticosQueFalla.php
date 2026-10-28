<?php

declare(strict_types=1);

namespace NotasSoap\Pruebas\Dobles;

use NotasSoap\Dominio\Excepciones\ErrorPersistencia;
use NotasSoap\Dominio\Modelo\Diagnostico;
use NotasSoap\Dominio\Puertos\RepositorioDiagnosticos;

/** Doble: simula que la base se cae justo al guardar el diagnóstico (después de guardar la nota). */
final class RepositorioDiagnosticosQueFalla implements RepositorioDiagnosticos
{
    public function guardar(Diagnostico $diagnostico): void
    {
        throw new ErrorPersistencia('Error de base de datos.', '08006');
    }

    public function deNota(string $notaId): array
    {
        return [];
    }
}
