<?php

declare(strict_types=1);

namespace NotasSoap\Dominio\Puertos;

use NotasSoap\Dominio\Modelo\NotaSoap;

/** Puerto de persistencia de notas (clinical_notes). Lo implementa la capa de Persistencia. */
interface RepositorioNotas
{
    /**
     * Guarda una nota nueva (también una corrección, con supersedes_id y correction_reason).
     * Una versión solo puede tener UNA corrección: la segunda es un error de integridad (SQLSTATE clase 23).
     */
    public function guardar(NotaSoap $nota): void;

    public function buscar(string $id): ?NotaSoap;

    public function registrarFirma(NotaSoap $nota): void;

    /** @return list<NotaSoap> notas del expediente ordenadas por fecha de creación y luego por id */
    public function deExpediente(string $expedienteId): array;

    /** Semana 6: la nota que corrige a $notaId (la que tiene supersedes_id = $notaId), si existe. */
    public function correccionDe(string $notaId): ?NotaSoap;
}
