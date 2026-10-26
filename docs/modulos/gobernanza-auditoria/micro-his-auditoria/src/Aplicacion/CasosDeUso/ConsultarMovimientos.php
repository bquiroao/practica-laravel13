<?php

declare(strict_types=1);

namespace Auditoria\Aplicacion\CasosDeUso;

use Auditoria\Aplicacion\Comandos\ComandoConsultar;
use Auditoria\Aplicacion\Puertos\PoliticaAcceso;
use Auditoria\Aplicacion\Puertos\Transaccion;
use Auditoria\Aplicacion\Resultados\Resultado;
use Auditoria\Aplicacion\Servicios\CadenaSellada;
use Auditoria\Dominio\Excepciones\ErrorPersistencia;
use Auditoria\Dominio\Filtros\FiltroMovimiento;
use Auditoria\Dominio\Modelo\ConsultaMovimientos;
use Auditoria\Dominio\Modelo\Movimiento;
use Auditoria\Dominio\Puertos\Bitacora;

/**
 * CU-02, CU-06 y CU-07. Aplica los filtros que recibe sin conocerlos (OCP, semana 2).
 * Fijos aquí: permiso y alcance (R-05), cursor y página de 50 (R-09) y meta-auditoría (R-08).
 */
final class ConsultarMovimientos
{
    public const PAGINA = 50;
    public const PERMISO = 'auditoria.consultar';
    public const PERMISO_RED = 'auditoria.consultar_red';

    /** @param iterable<FiltroMovimiento> $filtros */
    public function __construct(
        private readonly iterable $filtros,
        private readonly Bitacora $bitacora,
        private readonly CadenaSellada $cadena,
        private readonly Transaccion $transaccion,
        private readonly PoliticaAcceso $acceso,
    ) {
    }

    public function ejecutar(ComandoConsultar $comando): Resultado
    {
        $red = $comando->hospital === PoliticaAcceso::RED;
        if (!$this->acceso->permite($comando->actor, $comando->hospital, $red ? ConsultarMovimientos::PERMISO_RED : ConsultarMovimientos::PERMISO)) {
            return Resultado::accesoDenegado();
        }
        $parametros = $comando->parametros;
        $cursor = $parametros['cursor'] ?? null;
        unset($parametros['cursor']);
        $despuesDe = $cursor === null ? null : self::leerCursor($cursor);
        if ($cursor !== null && $despuesDe === null) {
            return Resultado::error(Resultado::INVALIDO, 'DATOS_INVALIDOS', ['campos' => ['cursor' => 'Cursor inválido.']]);
        }

        $reclamados = [];
        foreach ($this->filtros as $filtro) {
            $reclamados = [...$reclamados, ...$filtro->parametros()];
        }
        foreach (array_keys($parametros) as $nombre) {
            if (!in_array($nombre, $reclamados, true)) {
                return Resultado::error(Resultado::INVALIDO, 'FILTRO_NO_SOPORTADO', ['campos' => [$nombre => 'Filtro no soportado.']]);
            }
        }
        $activos = [];
        foreach ($this->filtros as $filtro) {
            if (!$filtro->activo($parametros) && !$filtro->obligatorio()) {
                continue;
            }
            $errores = $filtro->validar($parametros);
            if ($errores !== []) {
                return Resultado::error(Resultado::INVALIDO, 'DATOS_INVALIDOS', ['campos' => $errores]);
            }
            $activos[] = $filtro;
        }
        $consulta = new ConsultaMovimientos($red ? null : $comando->hospital, $red, $activos, $parametros, self::PAGINA + 1, $despuesDe);

        try {
            return $this->transaccion->ejecutar(function () use ($consulta, $comando, $parametros, $red): Resultado {
                $encontrados = $this->bitacora->buscar($consulta);
                $pagina = array_slice($encontrados, 0, self::PAGINA);
                // R-08: sin evidencia de la consulta no se entregan resultados (si falla, se revierte todo).
                $this->cadena->auditarLectura($consulta->hospital, $comando->actor, 'AUDITORIA_CONSULTADA',
                    ['alcance' => $red ? 'red' : 'hospital', 'filtros' => $parametros, 'resultados' => count($pagina)]);
                $ultimo = $pagina === [] ? null : $pagina[count($pagina) - 1];

                return Resultado::ok([
                    'movimientos' => array_map(static fn (Movimiento $m): array => $m->resumen(), $pagina),
                    'cursor_siguiente' => count($encontrados) > self::PAGINA && $ultimo !== null ? self::cursorDe($ultimo) : null,
                ]);
            });
        } catch (ErrorPersistencia) {
            return Resultado::noDisponible();
        }
    }

    private static function cursorDe(Movimiento $m): string
    {
        return rtrim(strtr(base64_encode($m->ocurridoEn->format('Y-m-d\TH:i:s\Z') . '|' . $m->id), '+/', '-_'), '=');
    }

    /** @return array{0: \DateTimeImmutable, 1: int}|null */
    private static function leerCursor(string $cursor): ?array
    {
        $texto = base64_decode(strtr($cursor, '-_', '+/'), true);
        if ($texto === false || preg_match('/^(\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z)\|(\d{1,18})$/', $texto, $m) !== 1) {
            return null;
        }
        $fecha = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s\Z', $m[1], new \DateTimeZone('UTC'));

        return $fecha === false ? null : [$fecha, (int) $m[2]];
    }
}
