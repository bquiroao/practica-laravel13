<?php

declare(strict_types=1);

namespace E2e\Aplicacion\CasosDeUso;

use E2e\Aplicacion\Puertos\AlmacenEvidencias;
use E2e\Aplicacion\Puertos\AlmacenNoDisponible;
use E2e\Aplicacion\Puertos\PoliticaAcceso;
use E2e\Aplicacion\Puertos\RegistroAuditoria;
use E2e\Aplicacion\Puertos\Transaccion;
use E2e\Aplicacion\Resultados\Salida;
use E2e\Dominio\Excepciones\ErrorPersistencia;
use E2e\Dominio\Excepciones\ReglaRechazada;
use E2e\Dominio\Modelo\Evidencia;
use E2e\Dominio\Modelo\ReporteResultado;
use E2e\Dominio\Modelo\ResultadoEscenario;
use E2e\Dominio\Puertos\Reloj;
use E2e\Dominio\Puertos\RepositorioEjecuciones;

/**
 * CU-05 (incluye CU-06): el pipeline reporta el resultado de un escenario con sus artefactos. Desde la semana 7 el caso de
 * uso solo ORQUESTA; las reglas están en ReporteResultado y en Ejecucion::preparar(). El orden sigue siendo:
 *   1. idempotencia ANTES que el estado (R-12): un reintento que llega tarde devuelve lo mismo, no un 409;
 *   2. las reglas del dominio ANTES de tocar el almacén (no quedan artefactos huérfanos de un resultado rechazado);
 *   3. el almacén ANTES de la base, fail-closed (R-08): si no confirma, no se registra nada;
 *   4. resultado, evidencias y traza en UNA transacción.
 */
final class ReportarResultado
{
    public const PERMISO = 'e2e.reportar';

    public function __construct(
        private readonly PoliticaAcceso $acceso,
        private readonly RepositorioEjecuciones $ejecuciones,
        private readonly AlmacenEvidencias $almacen,
        private readonly RegistroAuditoria $auditoria,
        private readonly Transaccion $transaccion,
        private readonly Reloj $reloj,
    ) {
    }

    /** @param array<string, mixed> $datos escenario, resultado, intentos, evidencias[{tipo, bytes}] */
    public function ejecutar(string $usuario, string $tenant, int $ejecucionId, string $clave, array $datos): Salida
    {
        if (!$this->acceso->permite($usuario, self::PERMISO, $tenant)) {
            return Salida::denegado();
        }
        try {
            $reporte = ReporteResultado::desde($ejecucionId, $clave, $datos);
            $previo = $this->ejecuciones->resultadoPorClave($tenant, $reporte->clave);
            if ($previo !== null) {
                return $reporte->repite(...$previo) ? new Salida(Salida::OK, 'RESULTADO_YA_REPORTADO', $this->datos($ejecucionId, $previo[1]))
                    : new Salida(Salida::CONFLICTO, 'CLAVE_REUTILIZADA');
            }
            $ejecucion = $this->ejecuciones->porId($tenant, $ejecucionId);
            if ($ejecucion === null) {
                return Salida::noEncontrado();
            }
            $resultado = $ejecucion->preparar($reporte, $this->reloj->ahora())
                ->conEvidencias(array_map(fn (array $a): Evidencia => $this->almacen->guardar($a['tipo'], $a['bytes']), $reporte->artefactos));
            $ejecucion->agregar($resultado);
            $guardado = $this->transaccion->ejecutar(function () use ($tenant, $ejecucionId, $resultado, $usuario): ResultadoEscenario {
                $r = $this->ejecuciones->agregarResultado($tenant, $ejecucionId, $resultado);
                $this->auditoria->registrar('RESULTADO_REPORTADO', $tenant, $usuario, "ejecucion:$ejecucionId/" . $r->escenario);

                return $r;
            });
        } catch (ReglaRechazada $e) {
            return Salida::rechazo($e);
        } catch (AlmacenNoDisponible) {
            return new Salida(Salida::NO_DISPONIBLE, 'EVIDENCIA_NO_DISPONIBLE');
        } catch (ErrorPersistencia) {
            return Salida::persistencia();
        }

        return new Salida(Salida::CREADO, 'RESULTADO_REPORTADO', $this->datos($ejecucionId, $guardado));
    }

    /** @return array<string, mixed> */
    private function datos(int $ejecucion, ResultadoEscenario $r): array
    {
        return ['ejecucion' => $ejecucion, 'escenario' => $r->escenario, 'resultado' => $r->resultado->value, 'intentos' => $r->intentos,
            'evidencias' => array_map(static fn (Evidencia $e): string => $e->sha256, $r->evidencias)];
    }
}
