<?php

declare(strict_types=1);

namespace Pacientes\Dominio;

/**
 * Documento oficial que identifica a UNA persona (DPI, pasaporte...).
 *
 * Contrato que toda implementación debe cumplir (LSP):
 *  - claveBusqueda() nunca es vacía.
 *  - El mismo documento, aunque se escriba distinto (espacios, guiones, minúsculas),
 *    produce la misma clave.
 *  - Documentos de personas distintas producen claves distintas.
 *  - enmascarado() nunca contiene el valor completo; muestra a lo sumo los 4 últimos caracteres.
 *
 * Un paciente SIN documento no tiene Identificador (null): no se inventa uno «vacío».
 */
interface Identificador
{
    /** Tipo de documento en mayúsculas, por ejemplo DPI o PASAPORTE. */
    public function tipo(): string;

    /** Quién lo emite, por ejemplo GTM-RENAP o el país del pasaporte. */
    public function emisor(): string;

    /** Hash para buscar y verificar unicidad sin descifrar el valor (search_hash). */
    public function claveBusqueda(): string;

    /** Valor para mostrar en listas, respuestas y auditoría. */
    public function enmascarado(): string;
}
