<?php
declare(strict_types=1);

namespace MicroHis\Domain;

/**
 * Repository (semana 4): colección de solo lectura de los datos de ocupación, vista desde el dominio.
 * Vive en Domain porque es el límite que el dominio necesita; los adaptadores PDO e InMemory lo implementan.
 * Toda falla de lectura se informa como DatosNoDisponibles.
 */
interface OcupacionRepository
{
    public function camasHabilitadas(): int;

    /** @return array<string,int> camas ocupadas por sala en el período */
    public function ocupadasPorSala(Filtros $f): array;

    /** @return list<RegistroOrigen> una fila por cama ocupada de la sala */
    public function registrosDeSala(Filtros $f, string $sala): array;
}
