<?php

declare(strict_types=1);

namespace Auditoria\Aplicacion\CasosDeUso;

use Auditoria\Aplicacion\Comandos\ComandoConsultar;
use Auditoria\Aplicacion\Puertos\Transaccion;
use Auditoria\Aplicacion\Resultados\Resultado;
use Auditoria\Aplicacion\Servicios\AutorizacionLectura;
use Auditoria\Aplicacion\Servicios\CadenaSellada;
use Auditoria\Aplicacion\Servicios\CursorPaginacion;
use Auditoria\Aplicacion\Servicios\ProyeccionMovimientos;
use Auditoria\Dominio\Excepciones\ConsultaInvalida;
use Auditoria\Dominio\Excepciones\ErrorPersistencia;
use Auditoria\Dominio\Filtros\CatalogoFiltros;
use Auditoria\Dominio\Modelo\Alcance;
use Auditoria\Dominio\Modelo\ConsultaMovimientos;
use Auditoria\Dominio\Puertos\Bitacora;

/**
 * CU-02, CU-06 y CU-07. Solo el FLUJO: autorizar → interpretar → buscar y auditar en una transacción → proyectar.
 * Semana 7: el alcance (Alcance), los filtros (CatalogoFiltros), el cursor (CursorPaginacion), el permiso
 * (AutorizacionLectura) y la forma de salida (ProyeccionMovimientos) tienen su propio dueño.
 * Fijos aquí: página de 50 (R-09) y meta-auditoría dentro de la transacción (R-08).
 */
final class ConsultarMovimientos
{
    public const PAGINA = 50;

    public function __construct(
        private readonly CatalogoFiltros $catalogo,
        private readonly Bitacora $bitacora,
        private readonly CadenaSellada $cadena,
        private readonly Transaccion $transaccion,
        private readonly AutorizacionLectura $autorizacion,
        private readonly ProyeccionMovimientos $proyeccion,
    ) {
    }

    public function ejecutar(ComandoConsultar $comando): Resultado
    {
        $alcance = Alcance::desdeTenant($comando->hospital);
        if (!$this->autorizacion->permite($comando->actor, $alcance)) {
            return Resultado::accesoDenegado();
        }
        $parametros = $comando->parametros;
        $cursor = $parametros['cursor'] ?? null;
        unset($parametros['cursor']);
        $despuesDe = $cursor === null ? null : CursorPaginacion::leer($cursor);
        if ($cursor !== null && $despuesDe === null) {
            return Resultado::error(Resultado::INVALIDO, 'DATOS_INVALIDOS', ['campos' => ['cursor' => 'Cursor inválido.']]);
        }
        try {
            $consulta = ConsultaMovimientos::en($alcance, $this->catalogo->interpretar($parametros), $parametros, self::PAGINA + 1, $despuesDe);
        } catch (ConsultaInvalida $e) {
            return Resultado::error(Resultado::INVALIDO, $e->codigo, ['campos' => $e->campos]);
        }

        try {
            return $this->transaccion->ejecutar(function () use ($consulta, $comando, $parametros, $alcance): Resultado {
                $encontrados = $this->bitacora->buscar($consulta);
                $pagina = array_slice($encontrados, 0, self::PAGINA);
                // R-08: sin evidencia de la consulta no se entregan resultados (si falla, se revierte todo).
                $this->cadena->auditarLectura($alcance->hospital, $comando->actor, 'AUDITORIA_CONSULTADA',
                    ['alcance' => $alcance->etiqueta(), 'filtros' => $parametros, 'resultados' => count($pagina)]);
                $ultimo = $pagina === [] ? null : $pagina[count($pagina) - 1];

                return Resultado::ok([
                    'movimientos' => $this->proyeccion->resumenes($pagina),
                    'cursor_siguiente' => count($encontrados) > self::PAGINA && $ultimo !== null ? CursorPaginacion::de($ultimo) : null,
                ]);
            });
        } catch (ErrorPersistencia) {
            return Resultado::noDisponible();
        }
    }
}
