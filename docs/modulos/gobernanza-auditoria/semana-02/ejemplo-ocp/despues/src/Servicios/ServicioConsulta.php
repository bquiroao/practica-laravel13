<?php

declare(strict_types=1);

namespace Auditoria\Servicios;

use Auditoria\Contratos\Bitacora;
use Auditoria\Contratos\FiltroMovimiento;
use Auditoria\Contratos\PoliticaAcceso;
use Auditoria\Contratos\Reloj;
use Auditoria\Contratos\Transaccion;
use Auditoria\Dominio\ConsultaMovimientos;
use Auditoria\Dominio\ErrorPersistencia;
use Auditoria\Dominio\Movimiento;
use Auditoria\Dominio\Resultado;

/**
 * CU-02, CU-03, CU-06 y CU-07. Cerrado a modificación: aplica los filtros que recibe
 * sin conocerlos. Fijos aquí: permiso y alcance (R-05), meta-auditoría (R-08) y página (R-09).
 */
final class ServicioConsulta
{
    public const PAGINA = 50;

    /** @param iterable<FiltroMovimiento> $filtros */
    public function __construct(
        private readonly iterable $filtros,
        private readonly Bitacora $bitacora,
        private readonly Transaccion $transaccion,
        private readonly PoliticaAcceso $acceso,
        private readonly Reloj $reloj,
        private readonly ServicioRegistro $registro,
    ) {
    }

    /** @param array<string, string> $parametros */
    public function consultar(string $actor, ?string $hospitalToken, string $alcance, array $parametros): Resultado
    {
        $red = $alcance === 'red';
        if (!$this->acceso->permite($actor, $red ? '*' : $hospitalToken, $red ? 'auditoria.consultar_red' : 'auditoria.consultar')) {
            return Resultado::error('prohibido', 'ACCESO_DENEGADO');
        }
        $reclamados = [];
        foreach ($this->filtros as $filtro) {
            $reclamados = [...$reclamados, ...$filtro->parametros()];
        }
        foreach (array_keys($parametros) as $nombre) {
            if (!in_array($nombre, $reclamados, true)) {
                return Resultado::error('invalido', 'FILTRO_NO_SOPORTADO', ['campos' => [$nombre => 'Filtro no soportado.']]);
            }
        }
        $activos = [];
        foreach ($this->filtros as $filtro) {
            if (!$filtro->activo($parametros) && !$filtro->obligatorio()) {
                continue;
            }
            $errores = $filtro->validar($parametros);
            if ($errores !== []) {
                return Resultado::error('invalido', 'DATOS_INVALIDOS', ['campos' => $errores]);
            }
            $activos[] = $filtro;
        }
        $consulta = new ConsultaMovimientos($red ? null : $hospitalToken, $red, $activos, $parametros, self::PAGINA + 1);

        try {
            return $this->transaccion->ejecutar(function () use ($consulta, $actor, $alcance, $parametros): Resultado {
                $encontrados = $this->bitacora->buscar($consulta);
                $pagina = array_slice($encontrados, 0, self::PAGINA);
                // R-08: sin evidencia de la consulta, no se entregan resultados (si falla, se revierte todo).
                $this->auditar($consulta->hospital, $actor, ['alcance' => $alcance, 'filtros' => $parametros, 'resultados' => count($pagina)]);

                return Resultado::ok([
                    'ids' => array_map(static fn (Movimiento $m): int => $m->id, $pagina),
                    'hay_mas' => count($encontrados) > self::PAGINA,
                ]);
            });
        } catch (ErrorPersistencia) {
            return Resultado::noDisponible();
        }
    }

    /** CU-03: el mismo 404 si no existe o si está fuera del alcance. */
    public function detalle(string $actor, ?string $hospitalToken, string $alcance, int $id): Resultado
    {
        $red = $alcance === 'red';
        if (!$this->acceso->permite($actor, $red ? '*' : $hospitalToken, $red ? 'auditoria.consultar_red' : 'auditoria.consultar')) {
            return Resultado::error('prohibido', 'ACCESO_DENEGADO');
        }

        try {
            return $this->transaccion->ejecutar(function () use ($actor, $hospitalToken, $red, $alcance, $id): Resultado {
                $movimiento = $this->bitacora->porId($id);
                if ($movimiento === null || (!$red && $movimiento->hospital !== $hospitalToken)) {
                    return Resultado::error('no_encontrado', 'MOVIMIENTO_NO_ENCONTRADO');
                }
                $this->auditar($red ? null : $hospitalToken, $actor, ['alcance' => $alcance, 'detalle' => $id]);

                return Resultado::ok(['movimiento' => $movimiento->contenido() + ['id' => $movimiento->id,
                    'secuencia' => $movimiento->secuencia, 'sello' => $movimiento->sello]]);
            });
        } catch (ErrorPersistencia) {
            return Resultado::noDisponible();
        }
    }

    private function auditar(?string $hospital, string $actor, array $detalle): void
    {
        $this->registro->anexar(Movimiento::delSistema('consulta-' . $this->bitacora->siguienteId(), $hospital, $actor,
            'AUDITORIA_CONSULTADA', $this->reloj->ahora(), $detalle));
    }
}
