<?php

declare(strict_types=1);

namespace NotasSoap\EnMemoria;

use NotasSoap\Contratos\RepositorioDiagnosticos;
use NotasSoap\Dominio\Diagnostico;

final class RepositorioDiagnosticosEnMemoria implements RepositorioDiagnosticos, Restaurable
{
    /** @var array<string, Diagnostico> */
    private array $diagnosticos = [];
    private int $secuencia = 0;

    public function guardar(Diagnostico $diagnostico): Diagnostico
    {
        $guardado = $diagnostico->conId(sprintf('dx-%03d', ++$this->secuencia));
        $this->diagnosticos[(string) $guardado->id] = $guardado;

        return $guardado;
    }

    public function total(): int
    {
        return count($this->diagnosticos);
    }

    public function instantanea(): array
    {
        return [$this->diagnosticos, $this->secuencia];
    }

    public function restaurar(array $estado): void
    {
        [$this->diagnosticos, $this->secuencia] = $estado;
    }
}
