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
use E2e\Dominio\Modelo\ResultadoEscenario;
use E2e\Dominio\Puertos\Reloj;
use E2e\Dominio\Puertos\RepositorioEjecuciones;

/**
 * CU-05 (incluye CU-06): el pipeline reporta el resultado de un escenario con sus artefactos. El orden importa:
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
        $artefactos = is_array($datos['evidencias'] ?? null) ? $datos['evidencias'] : [];
        $huella = hash('sha256', json_encode([$ejecucionId, $datos['escenario'] ?? null, $datos['resultado'] ?? null, $datos['intentos'] ?? null,
            array_map(static fn ($a): array => [is_array($a) ? ($a['tipo'] ?? null) : null, is_array($a) ? hash('sha256', (string) ($a['bytes'] ?? '')) : null], $artefactos)],
            JSON_THROW_ON_ERROR));
        try {
            if (!preg_match('/^[A-Za-z0-9._-]{8,80}$/', $clave)) {
                throw new ReglaRechazada('DATOS_INVALIDOS', ['Idempotency-Key']);
            }
            $previo = $this->ejecuciones->resultadoPorClave($tenant, $clave);
            if ($previo !== null) {
                return $previo[0] === $ejecucionId && $previo[1]->huella === $huella
                    ? new Salida(Salida::OK, 'RESULTADO_YA_REPORTADO', $this->datos($ejecucionId, $previo[1]))
                    : new Salida(Salida::CONFLICTO, 'CLAVE_REUTILIZADA');
            }
            $ejecucion = $this->ejecuciones->porId($tenant, $ejecucionId);
            if ($ejecucion === null) {
                return Salida::noEncontrado();
            }
            $escenario = (string) ($datos['escenario'] ?? '');
            $sinEvidencias = ResultadoEscenario::desde($escenario, $ejecucion->critico($escenario), (string) ($datos['resultado'] ?? ''),
                is_int($datos['intentos'] ?? null) ? $datos['intentos'] : -1, [], $clave, $huella, $this->reloj->ahora());
            (clone $ejecucion)->agregar($sinEvidencias);                                    // estado y duplicados, sin escribir nada
            foreach ($artefactos as $a) {
                Evidencia::validar(is_array($a) ? (string) ($a['tipo'] ?? '') : '', is_array($a) ? (string) ($a['bytes'] ?? '') : '');
            }
            $evidencias = array_map(fn (array $a): Evidencia => $this->almacen->guardar((string) $a['tipo'], (string) $a['bytes']), $artefactos);
            $resultado = new ResultadoEscenario(null, $sinEvidencias->escenario, $sinEvidencias->critico, $sinEvidencias->resultado,
                $sinEvidencias->intentos, $evidencias, $clave, $huella, $sinEvidencias->reportadoEn);
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
