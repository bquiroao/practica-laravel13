<?php

declare(strict_types=1);

namespace E2e\Aplicacion\CasosDeUso;

use E2e\Aplicacion\Puertos\PoliticaAcceso;
use E2e\Aplicacion\Puertos\RegistroAuditoria;
use E2e\Aplicacion\Puertos\Transaccion;
use E2e\Aplicacion\Resultados\Salida;
use E2e\Dominio\Excepciones\ErrorPersistencia;
use E2e\Dominio\Excepciones\ReglaRechazada;
use E2e\Dominio\Puertos\Reloj;
use E2e\Dominio\Puertos\RepositorioEjecuciones;

/** CU-07: el pipeline completa la ejecución (todos los escenarios con resultado) o la aborta con un motivo. */
final class CerrarEjecucion
{
    public const PERMISO = 'e2e.ejecutar';

    public function __construct(
        private readonly PoliticaAcceso $acceso,
        private readonly RepositorioEjecuciones $ejecuciones,
        private readonly RegistroAuditoria $auditoria,
        private readonly Transaccion $transaccion,
        private readonly Reloj $reloj,
    ) {
    }

    public function ejecutar(string $usuario, string $tenant, int $id, ?string $motivoAborto = null): Salida
    {
        if (!$this->acceso->permite($usuario, self::PERMISO, $tenant)) {
            return Salida::denegado();
        }
        try {
            $e = $this->ejecuciones->porId($tenant, $id);
            if ($e === null) {
                return Salida::noEncontrado();
            }
            $motivoAborto === null ? $e->completar($this->reloj->ahora()) : $e->abortar($motivoAborto, $this->reloj->ahora());
            $this->transaccion->ejecutar(function () use ($e, $usuario, $tenant): void {
                $this->ejecuciones->actualizarEstado($e);
                $this->auditoria->registrar('EJECUCION_' . $e->estado(), $tenant, $usuario, 'ejecucion:' . $e->id);
            });
        } catch (ReglaRechazada $ex) {
            return Salida::rechazo($ex);
        } catch (ErrorPersistencia) {
            return Salida::persistencia();
        }

        return new Salida(Salida::OK, 'EJECUCION_' . $e->estado(), ['id' => $e->id, 'estado' => $e->estado()]);
    }
}
