<?php

declare(strict_types=1);

namespace Auditoria\Dominio\Filtros;

use Auditoria\Dominio\Modelo\Movimiento;

/**
 * Base de los filtros opcionales «campo = valor»: usuario, módulo y acción.
 * Cada subclase solo dice qué parámetro reclama, qué columna usa y de dónde lee el valor.
 */
abstract class FiltroPorCampo implements FiltroMovimiento
{
    abstract protected function parametro(): string;

    abstract protected function columna(): string;

    abstract protected function valorDe(Movimiento $m): string;

    /** Semana 12: el máximo es el mismo que al registrar ese campo (recurso admite 128). */
    protected function maximo(): int
    {
        return 64;
    }

    public function parametros(): array
    {
        return [$this->parametro()];
    }

    public function obligatorio(): bool
    {
        return false;
    }

    public function activo(array $p): bool
    {
        return isset($p[$this->parametro()]);
    }

    public function validar(array $p): array
    {
        $valor = trim($p[$this->parametro()]);

        return $valor === '' || mb_strlen($valor) > $this->maximo() ? [$this->parametro() => "De 1 a {$this->maximo()} caracteres."] : [];
    }

    public function coincide(Movimiento $m, array $p): bool
    {
        return $this->valorDe($m) === trim($p[$this->parametro()]);
    }

    public function sql(array $p): array
    {
        return [$this->columna() . ' = :' . $this->parametro(), [$this->parametro() => trim($p[$this->parametro()])]];
    }
}
