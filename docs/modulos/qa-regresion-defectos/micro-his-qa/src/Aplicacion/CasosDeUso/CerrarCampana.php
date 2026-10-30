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
use Qa\Dominio\Puertos\RepositorioDefectos;

/**
 * CU-10: el Líder QA cierra la campaña y el sistema calcula GO / NO_GO (R-09). Los críticos abiertos se cuentan sobre
 * los casos del alcance, aunque el defecto venga de una campaña anterior: un crítico sin cerrar sigue bloqueando.
 * R-15 (semana 6): los MAYOR abiertos también bloquean si su caso es crítico en la matriz para las funcionalidades
 * de ESTA campaña (el mismo caso puede ser crítico para una funcionalidad y no para otra).
 */
final class CerrarCampana
{
    public const PERMISO = 'qa.cerrar';

    public function __construct(
        private readonly PoliticaAcceso $acceso,
        private readonly RepositorioCasos $casos,
        private readonly RepositorioCampanas $campanas,
        private readonly RepositorioDefectos $defectos,
        private readonly RegistroAuditoria $auditoria,
        private readonly Transaccion $transaccion,
    ) {
    }

    public function ejecutar(string $usuario, string $tenant, int $campanaId): Salida
    {
        if (!$this->acceso->permite($usuario, self::PERMISO, $tenant)) {
            return Salida::denegado();
        }
        try {
            $campana = $this->campanas->porId($tenant, $campanaId);
            if ($campana === null) {
                return Salida::noEncontrado();
            }
            $casos = array_keys($campana->alcance);
            $criticosDeMatriz = array_values(array_intersect($casos, $this->casos->criticosDe($tenant, $campana->funcionalidades)));
            $decision = $campana->cerrar($this->defectos->criticosAbiertos($tenant, $casos), $this->defectos->mayoresAbiertos($tenant, $criticosDeMatriz));
            $this->transaccion->ejecutar(function () use ($campana, $usuario, $tenant, $decision): void {
                $this->campanas->actualizarEstado($campana);
                $this->auditoria->registrar('CAMPANA_CERRADA_' . $decision->valor, $tenant, $usuario, 'campana:' . $campana->id);
            });
        } catch (ReglaRechazada $e) {
            return Salida::rechazo($e);
        } catch (ErrorPersistencia) {
            return Salida::persistencia();
        }

        return new Salida(Salida::OK, 'CAMPANA_CERRADA', ['id' => $campana->id, 'decision' => $decision->valor,
            'tasa_aprobados' => $decision->tasa, 'umbral' => $campana->umbral, 'causas' => $decision->causas]);
    }
}
