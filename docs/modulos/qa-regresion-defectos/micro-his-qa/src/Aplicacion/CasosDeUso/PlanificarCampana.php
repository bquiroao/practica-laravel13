<?php

declare(strict_types=1);

namespace Qa\Aplicacion\CasosDeUso;

use Qa\Aplicacion\Puertos\PoliticaAcceso;
use Qa\Aplicacion\Puertos\RegistroAuditoria;
use Qa\Aplicacion\Puertos\Transaccion;
use Qa\Aplicacion\Resultados\Salida;
use Qa\Dominio\Excepciones\ErrorPersistencia;
use Qa\Dominio\Excepciones\ReglaRechazada;
use Qa\Dominio\Modelo\Campana;
use Qa\Dominio\Puertos\RepositorioCampanas;
use Qa\Dominio\Puertos\RepositorioCasos;

/** CU-03: el Líder QA arma una campaña con las funcionalidades afectadas y los casos (congela la versión de cada uno). */
final class PlanificarCampana
{
    public const PERMISO = 'qa.planificar';
    public const UMBRAL_POR_DEFECTO = 0.95;

    public function __construct(
        private readonly PoliticaAcceso $acceso,
        private readonly RepositorioCasos $casos,
        private readonly RepositorioCampanas $campanas,
        private readonly RegistroAuditoria $auditoria,
        private readonly Transaccion $transaccion,
    ) {
    }

    /** @param array<string, mixed> $datos nombre, funcionalidades[], casos[], umbral? */
    public function ejecutar(string $usuario, string $tenant, array $datos): Salida
    {
        if (!$this->acceso->permite($usuario, self::PERMISO, $tenant)) {
            return Salida::denegado();
        }
        $ids = array_values(array_unique(array_filter(is_array($datos['casos'] ?? null) ? $datos['casos'] : [], 'is_string')));
        try {
            $encontrados = $ids === [] ? [] : $this->casos->variosPorId($tenant, $ids);
            $faltan = array_values(array_diff($ids, array_keys($encontrados)));
            if ($faltan !== []) {
                throw new ReglaRechazada('CASO_INEXISTENTE', $faltan);   // un caso de otro tenant tampoco «existe»
            }
            $alcance = [];
            foreach ($ids as $id) {
                $alcance[$id] = $encontrados[$id]->version;
            }
            $umbral = $datos['umbral'] ?? self::UMBRAL_POR_DEFECTO;
            $campana = Campana::planificar($tenant, (string) ($datos['nombre'] ?? ''),
                is_array($datos['funcionalidades'] ?? null) ? $datos['funcionalidades'] : [], $alcance,
                is_int($umbral) || is_float($umbral) ? (float) $umbral : -1.0, $usuario);
            $creada = $this->transaccion->ejecutar(function () use ($campana, $usuario, $tenant): Campana {
                $creada = $this->campanas->crear($campana);
                $this->auditoria->registrar('CAMPANA_PLANIFICADA', $tenant, $usuario, 'campana:' . $creada->id);

                return $creada;
            });
        } catch (ReglaRechazada $e) {
            return Salida::rechazo($e);
        } catch (ErrorPersistencia) {
            return Salida::persistencia();
        }

        return new Salida(Salida::CREADO, 'CAMPANA_PLANIFICADA', ['id' => $creada->id, 'estado' => $creada->estado()->value, 'alcance' => $creada->alcance]);
    }
}
