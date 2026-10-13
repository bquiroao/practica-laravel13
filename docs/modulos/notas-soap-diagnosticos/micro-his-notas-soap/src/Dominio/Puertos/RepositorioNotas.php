<?php

declare(strict_types=1);

namespace NotasSoap\Dominio\Puertos;

use NotasSoap\Dominio\Modelo\NotaSoap;

/** Puerto de persistencia de notas (clinical_notes). Lo implementa la capa de Persistencia. */
interface RepositorioNotas
{
    public function guardar(NotaSoap $nota): void;

    public function buscar(string $id): ?NotaSoap;

    public function registrarFirma(NotaSoap $nota): void;

    /** @return list<NotaSoap> notas del expediente ordenadas por fecha de creación y luego por id */
    public function deExpediente(string $expedienteId): array;
}
