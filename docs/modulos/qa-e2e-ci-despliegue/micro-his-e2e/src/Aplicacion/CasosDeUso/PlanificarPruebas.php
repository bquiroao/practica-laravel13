<?php

declare(strict_types=1);

namespace E2e\Aplicacion\CasosDeUso;

use E2e\Aplicacion\Puertos\PoliticaAcceso;
use E2e\Aplicacion\Puertos\RegistroAuditoria;
use E2e\Aplicacion\Puertos\Transaccion;
use E2e\Aplicacion\Resultados\Salida;
use E2e\Dominio\Excepciones\ErrorPersistencia;
use E2e\Dominio\Excepciones\ReglaRechazada;
use E2e\Dominio\Puertos\RepositorioEscenarios;
use E2e\Dominio\Puertos\RepositorioVersiones;

/** CU-03: el plan de la versión con todos los críticos y los escenarios de los módulos afectados (R-02). */
final class PlanificarPruebas
{
    public const PERMISO = 'e2e.planificar';

    public function __construct(
        private readonly PoliticaAcceso $acceso,
        private readonly RepositorioEscenarios $escenarios,
        private readonly RepositorioVersiones $versiones,
        private readonly RegistroAuditoria $auditoria,
        private readonly Transaccion $transaccion,
    ) {
    }

    /** @param list<mixed> $propuesta ids de escenarios */
    public function ejecutar(string $usuario, string $tenant, string $numero, array $propuesta): Salida
    {
        if (!$this->acceso->permite($usuario, self::PERMISO, $tenant)) {
            return Salida::denegado();
        }
        try {
            $v = $this->versiones->porNumero($tenant, $numero);
            if ($v === null) {
                return Salida::noEncontrado();
            }
            $v->planificar($this->escenarios->delTenant($tenant), $propuesta);
            $this->transaccion->ejecutar(function () use ($v, $usuario, $tenant): void {
                $this->versiones->actualizar($v);
                $this->auditoria->registrar('PLAN_DEFINIDO', $tenant, $usuario, 'version:' . $v->numero);
            });
        } catch (ReglaRechazada $e) {
            return Salida::rechazo($e);
        } catch (ErrorPersistencia) {
            return Salida::persistencia();
        }

        return new Salida(Salida::OK, 'PLAN_DEFINIDO', ['version' => $v->numero, 'plan' => $v->plan()]);
    }
}
