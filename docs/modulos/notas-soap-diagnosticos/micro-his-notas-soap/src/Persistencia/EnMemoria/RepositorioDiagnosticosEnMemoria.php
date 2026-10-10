<?php

declare(strict_types=1);

namespace NotasSoap\Persistencia\EnMemoria;

use NotasSoap\Dominio\Excepciones\ErrorPersistencia;
use NotasSoap\Dominio\Modelo\Diagnostico;
use NotasSoap\Dominio\Puertos\RepositorioDiagnosticos;

final class RepositorioDiagnosticosEnMemoria implements RepositorioDiagnosticos, Restaurable
{
    /** @var array<string, Diagnostico> */
    private array $diagnosticos = [];

    /** Recibe el almacén de notas para imitar la llave foránea diagnoses.clinical_note_id. */
    public function __construct(private readonly RepositorioNotasEnMemoria $notas)
    {
    }

    public function guardar(Diagnostico $diagnostico): void
    {
        if (isset($this->diagnosticos[$diagnostico->id])) {
            throw new ErrorPersistencia('Error de base de datos.', '23505');
        }
        if ($this->notas->buscar($diagnostico->notaId) === null) {
            throw new ErrorPersistencia('Error de base de datos.', '23503'); // violación de llave foránea
        }
        $this->diagnosticos[$diagnostico->id] = $diagnostico;
    }

    public function deNota(string $notaId): array
    {
        $lista = array_values(array_filter($this->diagnosticos, static fn (Diagnostico $d): bool => $d->notaId === $notaId));
        usort($lista, static fn (Diagnostico $a, Diagnostico $b): int => strcmp($a->id, $b->id)); // ORDER BY diagnosis_id

        return $lista;
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
