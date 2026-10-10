<?php

declare(strict_types=1);

namespace NotasSoap\Aplicacion\CasosDeUso;

use NotasSoap\Aplicacion\Comandos\ConsultarNotaComando;
use NotasSoap\Aplicacion\Resultados\NotaResultado;
use NotasSoap\Dominio\Excepciones\AccesoDenegado;
use NotasSoap\Dominio\Excepciones\NotaNoEncontrada;
use NotasSoap\Dominio\Puertos\RepositorioDiagnosticos;
use NotasSoap\Dominio\Puertos\RepositorioNotas;

/** Lectura de una nota con sus diagnósticos (base de CU-04). */
final class ConsultarNota
{
    public function __construct(
        private readonly RepositorioNotas $notas,
        private readonly RepositorioDiagnosticos $diagnosticos,
    ) {
    }

    public function ejecutar(ConsultarNotaComando $c): NotaResultado
    {
        if ($c->hospitalUsuario !== $c->hospitalSolicitud) {
            throw new AccesoDenegado('El token no corresponde a este hospital.');
        }
        $nota = $this->notas->buscar($c->notaId) ?? throw new NotaNoEncontrada('La nota no existe.');
        $dx = array_map(static fn ($d): array => [
            'id' => $d->id, 'code' => $d->codigo, 'description' => $d->descripcion, 'type' => $d->tipo->value,
        ], $this->diagnosticos->deNota($nota->id));

        return new NotaResultado($nota->id, $nota->autorId, $nota->estado, $nota->version, $nota->firmadaEn?->format(DATE_ATOM), $dx);
    }
}
