<?php
declare(strict_types=1);

namespace Catalogo\Application;

use Catalogo\Domain\Modelo\FormaFarmaceutica;
use Catalogo\Domain\Modelo\UnidadConcentracion;
use Catalogo\Domain\Modelo\ViaAdministracion;

/** Listas cerradas para formularios y clientes, sin que Presentation conozca el dominio. */
final class OpcionesCatalogo
{
    /** @return array{formas: list<string>, vias: list<string>, unidades: list<string>} */
    public static function todas(): array
    {
        $valores = fn (array $casos) => array_map(fn (\BackedEnum $c) => (string) $c->value, $casos);
        return [
            'formas' => $valores(FormaFarmaceutica::cases()),
            'vias' => $valores(ViaAdministracion::cases()),
            'unidades' => $valores(UnidadConcentracion::cases()),
        ];
    }
}
