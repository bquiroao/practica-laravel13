<?php

declare(strict_types=1);

namespace Prescripciones\Adaptadores\EnMemoria;

use Prescripciones\Aplicacion\Puertos\RegistroAuditoria;

final class AuditoriaEnMemoria implements RegistroAuditoria
{
    /** @var list<array{accion: string, hospital: string, usuario: string, recurso: string, resultado: string}> */
    public array $movimientos = [];

    public function registrar(string $accion, string $hospital, string $usuario, string $recurso, string $resultado): void
    {
        $this->movimientos[] = compact('accion', 'hospital', 'usuario', 'recurso', 'resultado');
    }

    /** @return list<string> */
    public function acciones(): array
    {
        return array_column($this->movimientos, 'accion');
    }
}
