<?php

declare(strict_types=1);

namespace Pacientes\Persistencia\EnMemoria;

use Pacientes\Aplicacion\Excepciones\ClaveIdempotenciaEnUso;
use Pacientes\Aplicacion\Puertos\AlmacenIdempotencia;

final class AlmacenIdempotenciaEnMemoria implements AlmacenIdempotencia, Restaurable
{
    /** @var array<string, array{huella: string, datos: array<string, mixed>}> */
    private array $registros = [];

    public function buscar(string $hospital, string $actor, string $clave): ?array
    {
        return $this->registros[$this->llave($hospital, $actor, $clave)] ?? null;
    }

    public function guardar(string $hospital, string $actor, string $clave, string $huella, array $datos): void
    {
        $llave = $this->llave($hospital, $actor, $clave);
        if (isset($this->registros[$llave])) {
            throw new ClaveIdempotenciaEnUso(); // equivalente a la PRIMARY KEY de PostgreSQL
        }
        $this->registros[$llave] = ['huella' => $huella, 'datos' => $datos];
    }

    public function instantanea(): mixed
    {
        return $this->registros;
    }

    public function restaurar(mixed $instantanea): void
    {
        $this->registros = $instantanea;
    }

    private function llave(string $hospital, string $actor, string $clave): string
    {
        return $hospital . '|' . $actor . '|' . $clave;
    }
}
