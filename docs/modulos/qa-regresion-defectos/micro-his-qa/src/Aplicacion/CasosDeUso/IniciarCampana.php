<?php

declare(strict_types=1);

namespace Qa\Aplicacion\CasosDeUso;

use Qa\Aplicacion\Puertos\PoliticaAcceso;
use Qa\Aplicacion\Puertos\RegistroAuditoria;
use Qa\Aplicacion\Puertos\Transaccion;
use Qa\Aplicacion\Resultados\Salida;
use Qa\Dominio\Excepciones\ErrorPersistencia;
use Qa\Dominio\Excepciones\ReglaRechazada;
use Qa\Dominio\Puertos\RepositorioCampanas;
use Qa\Dominio\Puertos\RepositorioCasos;

/** CU-04: iniciar la campaña sobre un build (R-03). La matriz de regresión dice qué casos críticos no pueden faltar. */
final class IniciarCampana
{
    public const PERMISO = 'qa.planificar';

    public function __construct(
        private readonly PoliticaAcceso $acceso,
        private readonly RepositorioCasos $casos,
        private readonly RepositorioCampanas $campanas,
        private readonly RegistroAuditoria $auditoria,
        private readonly Transaccion $transaccion,
    ) {
    }

    public function ejecutar(string $usuario, string $tenant, int $campanaId, string $build): Salida
    {
        if (!$this->acceso->permite($usuario, self::PERMISO, $tenant)) {
            return Salida::denegado();
        }
        try {
            $campana = $this->campanas->porId($tenant, $campanaId);
            if ($campana === null) {
                return Salida::noEncontrado();
            }
            $criticos = $this->casos->criticosDe($tenant, $campana->funcionalidades);
            $estados = [];
            foreach ($this->casos->variosPorId($tenant, array_keys($campana->alcance)) as $id => $caso) {
                // Si después de planificar apareció otra versión, la congelada ya no es la vigente: no se ejecuta.
                $estados[$id] = $caso->version === $campana->alcance[$id] ? $caso->estado : 'VERSION_CAMBIADA';
            }
            $campana->iniciar(trim($build), $criticos, $estados);
            $this->transaccion->ejecutar(function () use ($campana, $usuario, $tenant): void {
                $this->campanas->actualizarEstado($campana);
                $this->auditoria->registrar('CAMPANA_INICIADA', $tenant, $usuario, 'campana:' . $campana->id);
            });
        } catch (ReglaRechazada $e) {
            return Salida::rechazo($e);
        } catch (ErrorPersistencia) {
            return Salida::persistencia();
        }

        return new Salida(Salida::OK, 'CAMPANA_INICIADA', ['id' => $campana->id, 'estado' => $campana->estado()->value, 'build' => $campana->build()]);
    }
}
