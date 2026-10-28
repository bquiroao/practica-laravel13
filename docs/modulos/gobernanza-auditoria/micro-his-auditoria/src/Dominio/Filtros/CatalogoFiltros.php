<?php

declare(strict_types=1);

namespace Auditoria\Dominio\Filtros;

use Auditoria\Dominio\Excepciones\ConsultaInvalida;

/**
 * Semana 7: interpreta los parámetros de una consulta contra los filtros disponibles (OCP, semana 2).
 * Antes lo hacía el caso de uso. Un parámetro que ningún filtro reclama se rechaza, y un filtro
 * obligatorio (el rango de fechas, R-06) se valida aunque no venga.
 */
final class CatalogoFiltros
{
    /** @var list<FiltroMovimiento> */
    private readonly array $filtros;

    /** @param iterable<FiltroMovimiento> $filtros */
    public function __construct(iterable $filtros)
    {
        $this->filtros = [...$filtros];
    }

    /**
     * @param array<string, string> $parametros sin el cursor
     * @return list<FiltroMovimiento> los filtros que se aplican
     * @throws ConsultaInvalida
     */
    public function interpretar(array $parametros): array
    {
        $reclamados = [];
        foreach ($this->filtros as $filtro) {
            $reclamados = [...$reclamados, ...$filtro->parametros()];
        }
        foreach (array_keys($parametros) as $nombre) {
            if (!in_array($nombre, $reclamados, true)) {
                throw new ConsultaInvalida(ConsultaInvalida::FILTRO_NO_SOPORTADO, [$nombre => 'Filtro no soportado.']);
            }
        }
        $activos = [];
        foreach ($this->filtros as $filtro) {
            if (!$filtro->activo($parametros) && !$filtro->obligatorio()) {
                continue;
            }
            $errores = $filtro->validar($parametros);
            if ($errores !== []) {
                throw new ConsultaInvalida(ConsultaInvalida::DATOS_INVALIDOS, $errores);
            }
            $activos[] = $filtro;
        }

        return $activos;
    }
}
