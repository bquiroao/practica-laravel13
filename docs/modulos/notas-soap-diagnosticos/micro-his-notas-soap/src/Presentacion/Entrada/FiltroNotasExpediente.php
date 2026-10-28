<?php

declare(strict_types=1);

namespace NotasSoap\Presentacion\Entrada;

use NotasSoap\Aplicacion\Comandos\ListarNotasExpedienteComando;
use NotasSoap\Presentacion\Http\Identidad;
use NotasSoap\Presentacion\Http\Solicitud;

/** Modelo de entrada del listado: valida el filtro ?estado= (draft o signed) y arma el comando. */
final class FiltroNotasExpediente
{
    private const ESTADOS = ['draft', 'signed'];

    public static function comando(Solicitud $s, Identidad $quien, string $hospital, string $expedienteId): ListarNotasExpedienteComando
    {
        $estado = $s->parametro('estado');
        if ($estado !== null && !in_array($estado, self::ESTADOS, true)) {
            throw new CuerpoInvalido('El parámetro estado solo admite draft o signed.');
        }

        return new ListarNotasExpedienteComando($quien->hospitalId, $hospital, $expedienteId, $estado);
    }
}
