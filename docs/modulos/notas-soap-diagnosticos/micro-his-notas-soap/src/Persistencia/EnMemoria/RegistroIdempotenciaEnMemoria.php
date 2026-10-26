<?php

declare(strict_types=1);

namespace NotasSoap\Persistencia\EnMemoria;

use NotasSoap\Aplicacion\Puertos\RegistroIdempotencia;
use NotasSoap\Dominio\Excepciones\ErrorPersistencia;

final class RegistroIdempotenciaEnMemoria implements RegistroIdempotencia, Restaurable
{
    /** @var array<string, array{huella: string, nota_id: string}> */
    private array $claves = [];

    public function buscar(string $hospitalId, string $usuarioId, string $clave): ?array
    {
        return $this->claves[self::llave($hospitalId, $usuarioId, $clave)] ?? null;
    }

    public function guardar(string $hospitalId, string $usuarioId, string $clave, string $huella, string $notaId): void
    {
        $llave = self::llave($hospitalId, $usuarioId, $clave);
        if (isset($this->claves[$llave])) {
            throw new ErrorPersistencia('Error de base de datos.', '23505'); // igual que la llave primaria en PDO
        }
        $this->claves[$llave] = ['huella' => $huella, 'nota_id' => $notaId];
    }

    public function instantanea(): array
    {
        return $this->claves;
    }

    public function restaurar(array $estado): void
    {
        $this->claves = $estado;
    }

    private static function llave(string $hospitalId, string $usuarioId, string $clave): string
    {
        return $hospitalId . "\0" . $usuarioId . "\0" . $clave;
    }
}
