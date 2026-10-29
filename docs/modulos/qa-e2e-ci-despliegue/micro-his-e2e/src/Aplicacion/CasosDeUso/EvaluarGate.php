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
use E2e\Dominio\Modelo\PoliticaGate;
use E2e\Dominio\Modelo\QualityGate;
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
            $d = QualityGate::evaluar($this->politica, $e, $v, $this->reloj->ahora(), fn (string $sha): bool => $this->almacen->confirmada($sha), $usuario);
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

        return new Salida(Salida::OK, 'GATE_EVALUADO', ['version' => $v->numero, 'ejecucion' => $e->id, 'decision' => $d->valor, 'reglas' => $d->reglas,
            'causas' => $d->causas, 'digest' => $d->digest, 'politica' => $d->politica]);
    }
}
