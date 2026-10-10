<?php

declare(strict_types=1);

namespace NotasSoap\Pruebas\Dobles;

use NotasSoap\Dominio\Excepciones\ErrorPersistencia;
use NotasSoap\Dominio\Modelo\Diagnostico;
use NotasSoap\Dominio\Puertos\RepositorioDiagnosticos;

final class RepositorioDiagnosticosEnMemoria implements RepositorioDiagnosticos, Restaurable
{
    /** @var array<string, Diagnostico> */
    public array $diagnosticos = [];
    public bool $fallar = false;

    public function guardar(Diagnostico $diagnostico): void
    {
        if ($this->fallar) {
            throw new ErrorPersistencia('Error de base de datos.', '08006');
        }
        $this->diagnosticos[$diagnostico->id] = $diagnostico;
    }

    public function deNota(string $notaId): array
    {
        return array_values(array_filter($this->diagnosticos, static fn (Diagnostico $d): bool => $d->notaId === $notaId));
    }

    public function instantanea(): array
    {
        return $this->diagnosticos;
    }

    public function restaurar(array $estado): void
    {
        $this->diagnosticos = $estado;
    }
}
