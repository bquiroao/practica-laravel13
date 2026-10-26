<?php

declare(strict_types=1);

namespace NotasSoap\Aplicacion\Integracion;

use NotasSoap\Aplicacion\Puertos\BandejaSalida;
use NotasSoap\Aplicacion\Puertos\GeneradorId;
use NotasSoap\Dominio\Eventos\EventoClinico;
use NotasSoap\Dominio\Modelo\NotaSoap;
use NotasSoap\Dominio\Puertos\RepositorioNotas;

/**
 * Decorador: envuelve CUALQUIER RepositorioNotas (PDO o en memoria) y, al firmar, deja el evento
 * NotaSoapFirmada en la bandeja de salida. Los casos de uso no cambian ni se enteran.
 */
final class RepositorioNotasConEventos implements RepositorioNotas
{
    public function __construct(
        private readonly RepositorioNotas $interno,
        private readonly BandejaSalida $bandeja,
        private readonly GeneradorId $ids,
    ) {
    }

    public function guardar(NotaSoap $nota): void
    {
        $this->interno->guardar($nota);
    }

    public function buscar(string $id): ?NotaSoap
    {
        return $this->interno->buscar($id);
    }

    public function deExpediente(string $expedienteId): array
    {
        return $this->interno->deExpediente($expedienteId);
    }

    public function correccionDe(string $notaId): ?NotaSoap
    {
        return $this->interno->correccionDe($notaId);
    }

    public function registrarFirma(NotaSoap $nota): void
    {
        $this->interno->registrarFirma($nota);
        $this->bandeja->agregar(EventoClinico::notaFirmada($this->ids->nuevo(), $nota));
    }
}
