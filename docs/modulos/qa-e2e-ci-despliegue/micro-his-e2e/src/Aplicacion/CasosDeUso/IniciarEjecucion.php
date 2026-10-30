<?php

declare(strict_types=1);

namespace E2e\Aplicacion\CasosDeUso;

use E2e\Aplicacion\Puertos\PoliticaAcceso;
use E2e\Aplicacion\Puertos\RegistroAuditoria;
use E2e\Aplicacion\Puertos\Transaccion;
use E2e\Aplicacion\Resultados\Salida;
use E2e\Dominio\Excepciones\ErrorPersistencia;
use E2e\Dominio\Excepciones\ReglaRechazada;
use E2e\Dominio\Modelo\Ejecucion;
use E2e\Dominio\Puertos\Reloj;
use E2e\Dominio\Puertos\RepositorioEjecuciones;
use E2e\Dominio\Puertos\RepositorioVersiones;

/** CU-04: el pipeline inicia una ejecución sobre el digest de la versión; el plan se congela (R-02, R-03). */
final class IniciarEjecucion
{
    public const PERMISO = 'e2e.ejecutar';

    public function __construct(
        private readonly PoliticaAcceso $acceso,
        private readonly RepositorioVersiones $versiones,
        private readonly RepositorioEjecuciones $ejecuciones,
        private readonly RegistroAuditoria $auditoria,
        private readonly Transaccion $transaccion,
        private readonly Reloj $reloj,
    ) {
    }

    public function ejecutar(string $usuario, string $tenant, string $numero, string $digest, string $entorno): Salida
    {
        if (!$this->acceso->permite($usuario, self::PERMISO, $tenant)) {
            return Salida::denegado();
        }
        try {
            $v = $this->versiones->porNumero($tenant, $numero);
            if ($v === null) {
                return Salida::noEncontrado();
            }
            $nueva = Ejecucion::iniciar($v, $digest, $entorno, $this->reloj->ahora());
            $e = $this->transaccion->ejecutar(function () use ($v, $nueva, $usuario, $tenant): Ejecucion {
                $this->versiones->actualizar($v);
                $e = $this->ejecuciones->crear($nueva);
                $this->auditoria->registrar('EJECUCION_INICIADA', $tenant, $usuario, 'ejecucion:' . $e->id);

                return $e;
            });
        } catch (ReglaRechazada $ex) {
            return Salida::rechazo($ex);
        } catch (ErrorPersistencia) {
            return Salida::persistencia();
        }

        return new Salida(Salida::CREADO, 'EJECUCION_INICIADA', ['id' => $e->id, 'version' => $e->version, 'digest' => $e->digest, 'escenarios' => count($e->plan)]);
    }
}
