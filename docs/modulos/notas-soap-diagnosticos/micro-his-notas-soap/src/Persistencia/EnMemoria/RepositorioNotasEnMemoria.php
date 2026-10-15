<?php

declare(strict_types=1);

namespace NotasSoap\Persistencia\EnMemoria;

use NotasSoap\Dominio\Excepciones\ErrorPersistencia;
use NotasSoap\Dominio\Excepciones\NotaFirmada;
use NotasSoap\Dominio\Modelo\NotaSoap;
use NotasSoap\Dominio\Puertos\RepositorioNotas;

/**
 * Segundo adaptador del mismo puerto: guarda en un arreglo de PHP.
 * Se comporta igual que PdoRepositorioNotas (lo comprueban las pruebas de contrato REP).
 */
final class RepositorioNotasEnMemoria implements RepositorioNotas, Restaurable
{
    /** @var array<string, NotaSoap> */
    private array $notas = [];

    public function guardar(NotaSoap $nota): void
    {
        if (isset($this->notas[$nota->id])) {
            throw new ErrorPersistencia('Error de base de datos.', '23505'); // mismo SQLSTATE que una llave duplicada
        }
        // Semana 6: igual que el UNIQUE (supersedes_id) de la base: una versión se corrige una sola vez.
        if ($nota->reemplazaA !== null && $this->correccionDe($nota->reemplazaA) !== null) {
            throw new ErrorPersistencia('Error de base de datos.', '23505');
        }
        $this->notas[$nota->id] = $nota;
    }

    public function buscar(string $id): ?NotaSoap
    {
        return $this->notas[$id] ?? null;
    }

    public function registrarFirma(NotaSoap $nota): void
    {
        // Igual que el UPDATE ... WHERE status = 'draft' del adaptador PDO.
        $actual = $this->notas[$nota->id] ?? null;
        if ($actual === null || $actual->estado !== NotaSoap::BORRADOR) {
            throw new NotaFirmada('La nota ya está firmada y no se puede modificar.');
        }
        $this->notas[$nota->id] = $nota;
    }

    public function deExpediente(string $expedienteId): array
    {
        $lista = array_values(array_filter($this->notas, static fn (NotaSoap $n): bool => $n->expedienteId === $expedienteId));
        // Mismo orden que el adaptador PDO: ORDER BY created_at, clinical_note_id
        usort($lista, static fn (NotaSoap $a, NotaSoap $b): int => [$a->creadaEn, $a->id] <=> [$b->creadaEn, $b->id]);

        return $lista;
    }

    public function correccionDe(string $notaId): ?NotaSoap
    {
        foreach ($this->notas as $nota) {
            if ($nota->reemplazaA === $notaId) {
                return $nota;
            }
        }

        return null;
    }

    /** @return list<NotaSoap> */
    public function todas(): array
    {
        return array_values($this->notas);
    }

    public function instantanea(): array
    {
        return $this->notas;
    }

    public function restaurar(array $estado): void
    {
        $this->notas = $estado;
    }
}
