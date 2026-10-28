<?php

declare(strict_types=1);

namespace Pacientes\Dominio\Modelo;

/**
 * Documento oficial que identifica a UNA persona (contrato LSP de la semana 2).
 *
 *  - canonico() nunca es vacío; el mismo documento escrito distinto da el mismo canónico;
 *    documentos distintos dan canónicos distintos.
 *  - enmascarado() nunca contiene el número completo (a lo sumo los 4 últimos caracteres).
 *
 * El hash de búsqueda y el cifrado los calcula Persistencia con claves que el dominio no
 * conoce. Un paciente sin documento tiene identificador null.
 */
interface Identificador
{
    public function tipo(): string;

    public function emisor(): string;

    public function numero(): string;

    /** «EMISOR|TIPO|NUMERO» normalizado. */
    public function canonico(): string;

    public function enmascarado(): string;
}
