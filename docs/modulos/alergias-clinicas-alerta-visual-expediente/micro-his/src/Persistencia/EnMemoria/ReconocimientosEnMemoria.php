<?php
declare(strict_types=1);

namespace Alergias\Persistencia\EnMemoria;

use Alergias\Dominio\Reconocimiento;
use Alergias\Dominio\RepositorioReconocimientos;

final class ReconocimientosEnMemoria implements RepositorioReconocimientos
{
    /** @var array<string, Reconocimiento> clave = paciente|usuario|huella (como el UNIQUE de la tabla) */
    private array $reconocimientos = [];

    public function existe(string $pacienteId, string $usuarioId, string $huella): bool
    {
        return isset($this->reconocimientos["{$pacienteId}|{$usuarioId}|{$huella}"]);
    }

    public function guardar(Reconocimiento $r): void
    {
        $this->reconocimientos["{$r->pacienteId}|{$r->usuarioId}|{$r->huella}"] ??= $r;
    }

    public function cantidad(): int
    {
        return count($this->reconocimientos);
    }
}
