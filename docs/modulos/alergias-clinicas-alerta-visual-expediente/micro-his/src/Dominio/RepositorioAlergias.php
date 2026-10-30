<?php
declare(strict_types=1);

namespace Alergias\Dominio;

/** Interfaz Repository: la define el Dominio, la implementa Persistencia. */
interface RepositorioAlergias
{
    /** RN-03 */
    public function existeActivaEquivalente(string $pacienteId, TipoAlergia $tipo, string $claveAlergeno): bool;

    /** @throws AlergiaDuplicada si la base rechaza el duplicado; @throws ErrorPersistencia */
    public function guardar(Alergia $alergia): void;

    /** @return list<Alergia> solo status = 'active' */
    public function activasDePaciente(string $pacienteId): array;
}
