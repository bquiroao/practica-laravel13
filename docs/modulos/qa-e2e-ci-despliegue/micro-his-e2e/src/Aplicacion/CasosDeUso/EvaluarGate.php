<?php

declare(strict_types=1);

namespace E2e\Aplicacion\CasosDeUso;

use E2e\Aplicacion\Puertos\AlmacenEvidencias;
use E2e\Aplicacion\Puertos\PoliticaAcceso;
use E2e\Aplicacion\Puertos\RegistroAuditoria;
use E2e\Aplicacion\Puertos\Transaccion;
use E2e\Aplicacion\Resultados\Salida;
use E2e\Dominio\Excepciones\ErrorPersistencia;
use E2e\Dominio\Excepciones\ReglaRechazada;
use E2e\Dominio\Modelo\Decision;
use E2e\Dominio\Modelo\Ejecucion;
use E2e\Dominio\Modelo\PoliticaGate;
use E2e\Dominio\Modelo\QualityGate;
use E2e\Dominio\Modelo\Version;
use E2e\Dominio\Puertos\Reloj;
use E2e\Dominio\Puertos\RepositorioEjecuciones;
use E2e\Dominio\Puertos\RepositorioEvaluaciones;
use E2e\Dominio\Puertos\RepositorioVersiones;

/** CU-09: el Responsable de release (o el pipeline) evalúa el gate sobre una ejecución COMPLETADA (R-03, R-09). */
final class EvaluarGate
{
    public const PERMISO = 'e2e.evaluar';

    public function __construct(
        private readonly PoliticaAcceso $acceso,
        private readonly RepositorioVersiones $versiones,
        private readonly RepositorioEjecuciones $ejecuciones,
        private readonly RepositorioEvaluaciones $evaluaciones,
        private readonly AlmacenEvidencias $almacen,
        private readonly RegistroAuditoria $auditoria,
        private readonly Transaccion $transaccion,
        private readonly Reloj $reloj,
        private readonly PoliticaGate $politica,
    ) {
    }

    public function ejecutar(string $usuario, string $tenant, string $numero, int $ejecucionId): Salida
    {
        if (!$this->acceso->permite($usuario, self::PERMISO, $tenant)) {
            return Salida::denegado();
        }
        try {
            $v = $this->versiones->porNumero($tenant, $numero);
            $e = $this->ejecuciones->porId($tenant, $ejecucionId);
            if ($v === null || $e === null) {
                return Salida::noEncontrado();
            }
            $d = $this->calcular($e, $v, $usuario);
            $v->aplicar($d);
            $this->transaccion->ejecutar(function () use ($v, $d, $usuario, $tenant): void {
                $this->evaluaciones->guardar($d);
                $this->versiones->actualizar($v);
                $this->auditoria->registrar('GATE_' . $d->valor, $tenant, $usuario, 'version:' . $v->numero);
            });
        } catch (ReglaRechazada $ex) {
            return Salida::rechazo($ex);
        } catch (ErrorPersistencia) {
            return Salida::persistencia();
        }

        return $this->salida('GATE_EVALUADO', $v, $e, $d);
    }

    /**
     * Semana 12 (cambio B-01, hallazgo H-09 de la semana 9): lo que el gate decidiría AHORA, sin guardar nada. Usa la misma
     * función pura que el registro, así que la vista previa y la decisión no pueden divergir por código; solo por tiempo
     * (antigüedad, política), y ese caso lo muestra la consola al registrar.
     */
    public function previsualizar(string $usuario, string $tenant, string $numero, int $ejecucionId): Salida
    {
        if (!$this->acceso->permite($usuario, self::PERMISO, $tenant)) {
            return Salida::denegado();
        }
        try {
            $v = $this->versiones->porNumero($tenant, $numero);
            $e = $this->ejecuciones->porId($tenant, $ejecucionId);
            if ($v === null || $e === null) {
                return Salida::noEncontrado();
            }
            $d = $this->calcular($e, $v, $usuario);
        } catch (ReglaRechazada $ex) {
            return Salida::rechazo($ex);
        } catch (ErrorPersistencia) {
            return Salida::persistencia();
        }

        return $this->salida('GATE_PREVISTO', $v, $e, $d);
    }

    private function calcular(Ejecucion $e, Version $v, string $usuario): Decision
    {
        return QualityGate::evaluar($this->politica, $e, $v, $this->reloj->ahora(), fn (string $sha): bool => $this->almacen->confirmada($sha), $usuario);
    }

    private function salida(string $codigo, Version $v, Ejecucion $e, Decision $d): Salida
    {
        return new Salida(Salida::OK, $codigo, ['version' => $v->numero, 'ejecucion' => $e->id, 'decision' => $d->valor, 'reglas' => $d->reglas,
            'causas' => $d->causas, 'digest' => $d->digest, 'politica' => $d->politica]);
    }
}
