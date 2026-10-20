<?php
declare(strict_types=1);

namespace MicroHis\Domain;

/**
 * Puerto del dominio: qué datos necesita la ocupación, sin decir de dónde salen.
 * Persistence lo implementa con PDO (inversión de dependencias).
 * Toda falla de lectura se informa como DatosNoDisponibles.
 */
interface LectorOcupacion
{
    public function camasHabilitadas(): int;

    /** @return array<string,int> camas ocupadas por sala en el período */
    public function ocupadasPorSala(Filtros $f): array;

    /** @return list<RegistroOrigen> una fila por cama ocupada de la sala */
    public function registrosDeSala(Filtros $f, string $sala): array;
}
