<?php

declare(strict_types=1);

namespace NotasSoap\Aplicacion\CasosDeUso;

use NotasSoap\Aplicacion\Comandos\ListarNotasExpedienteComando;
use NotasSoap\Aplicacion\Resultados\ResumenNotaResultado;
use NotasSoap\Dominio\Excepciones\AccesoDenegado;
use NotasSoap\Dominio\Modelo\NotaSoap;
use NotasSoap\Dominio\Puertos\RepositorioDiagnosticos;
use NotasSoap\Dominio\Puertos\RepositorioNotas;

/** Notas de un expediente, de la más antigua a la más reciente, opcionalmente filtradas por estado. */
final class ListarNotasExpediente
{
    public function __construct(
        private readonly RepositorioNotas $notas,
        private readonly RepositorioDiagnosticos $diagnosticos,
    ) {
    }

    /** @return list<ResumenNotaResultado> */
    public function ejecutar(ListarNotasExpedienteComando $c): array
    {
        if ($c->hospitalUsuario !== $c->hospitalSolicitud) {
            throw new AccesoDenegado('El token no corresponde a este hospital.');
        }
        $notas = array_values(array_filter(
            $this->notas->deExpediente($c->expedienteId),
            static fn (NotaSoap $n): bool => $c->estado === null || $n->estado === $c->estado,
        ));

        return array_map(fn (NotaSoap $n): ResumenNotaResultado => ResumenNotaResultado::desde($n, $this->diagnosticos->deNota($n->id)), $notas);
    }
}
