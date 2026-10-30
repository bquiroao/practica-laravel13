<?php

declare(strict_types=1);

namespace E2e\Persistencia;

use E2e\Dominio\Modelo\EscenarioE2E;
use E2e\Persistencia\Archivos\AlmacenEvidenciasEnDisco;
use E2e\Persistencia\Configuracion\PoliticaAccesoEstatica;
use E2e\Persistencia\Configuracion\RelojSistema;
use E2e\Persistencia\EnMemoria\AlmacenEvidenciasEnMemoria;
use E2e\Persistencia\EnMemoria\RegistroAuditoriaEnMemoria;
use E2e\Persistencia\EnMemoria\RepositorioDesplieguesEnMemoria;
use E2e\Persistencia\EnMemoria\RepositorioEjecucionesEnMemoria;
use E2e\Persistencia\EnMemoria\RepositorioEscenariosEnMemoria;
use E2e\Persistencia\EnMemoria\RepositorioEvaluacionesEnMemoria;
use E2e\Persistencia\EnMemoria\RepositorioVersionesEnMemoria;
use E2e\Persistencia\EnMemoria\TransaccionEnMemoria;
use E2e\Persistencia\Pdo\FabricaConexion;
use E2e\Persistencia\Pdo\PdoRegistroAuditoria;
use E2e\Persistencia\Pdo\PdoRepositorioDespliegues;
use E2e\Persistencia\Pdo\PdoRepositorioEjecuciones;
use E2e\Persistencia\Pdo\PdoRepositorioEscenarios;
use E2e\Persistencia\Pdo\PdoRepositorioEvaluaciones;
use E2e\Persistencia\Pdo\PdoRepositorioVersiones;
use E2e\Persistencia\Pdo\PdoTransaccion;

/** Elige los adaptadores de cada puerto: «pdo» (PostgreSQL + disco) o «memoria». Los casos de uso no saben cuál les tocó. */
final class FabricaAdaptadores
{
    /** @return array<string, object> */
    public static function crear(array $config, ?\PDO $pdo = null): array
    {
        return match ($config['persistencia'] ?? 'pdo') {
            'pdo' => self::pdo($config, $pdo ?? FabricaConexion::crear($config['db']['dsn'], $config['db']['usuario'], $config['db']['clave'])),
            'memoria' => self::memoria($config, require dirname(__DIR__, 2) . '/database/datos-demo.php'),
            default => throw new \InvalidArgumentException('persistencia desconocida: use «pdo» o «memoria»'),
        };
    }

    /** @return array<string, object> */
    private static function pdo(array $config, \PDO $pdo): array
    {
        return [
            'acceso' => new PoliticaAccesoEstatica($config['permisos']),
            'escenarios' => new PdoRepositorioEscenarios($pdo),
            'versiones' => new PdoRepositorioVersiones($pdo),
            'ejecuciones' => new PdoRepositorioEjecuciones($pdo),
            'evaluaciones' => new PdoRepositorioEvaluaciones($pdo),
            'despliegues' => new PdoRepositorioDespliegues($pdo),
            'almacen' => new AlmacenEvidenciasEnDisco($config['evidencias']),
            'auditoria' => new PdoRegistroAuditoria($pdo),
            'transaccion' => new PdoTransaccion($pdo),
            'reloj' => new RelojSistema(),
        ];
    }

    /**
     * @param array{escenarios: list<array>} $datos
     * @return array<string, object>
     */
    public static function memoria(array $config, array $datos): array
    {
        $escenarios = new RepositorioEscenariosEnMemoria();
        foreach ($datos['escenarios'] as [$tenant, $id, $titulo, $modulo, $critico, $requisitos, $conjunto]) {
            $escenarios->guardar(new EscenarioE2E($tenant, $id, $titulo, $modulo, $critico, $requisitos, $conjunto, 'carga-inicial'));
        }
        $versiones = new RepositorioVersionesEnMemoria();
        $ejecuciones = new RepositorioEjecucionesEnMemoria();
        $evaluaciones = new RepositorioEvaluacionesEnMemoria();
        $despliegues = new RepositorioDesplieguesEnMemoria();
        $auditoria = new RegistroAuditoriaEnMemoria();

        return [
            'acceso' => new PoliticaAccesoEstatica($config['permisos']),
            'escenarios' => $escenarios,
            'versiones' => $versiones,
            'ejecuciones' => $ejecuciones,
            'evaluaciones' => $evaluaciones,
            'despliegues' => $despliegues,
            'almacen' => new AlmacenEvidenciasEnMemoria(),
            'auditoria' => $auditoria,
            'transaccion' => new TransaccionEnMemoria([$escenarios, $versiones, $ejecuciones, $evaluaciones, $despliegues, $auditoria]),
            'reloj' => new RelojSistema(),
        ];
    }
}
