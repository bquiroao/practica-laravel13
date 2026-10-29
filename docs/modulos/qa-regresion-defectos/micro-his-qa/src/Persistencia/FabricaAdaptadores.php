<?php

declare(strict_types=1);

namespace Qa\Persistencia;

use Qa\Dominio\Modelo\CasoPrueba;
use Qa\Persistencia\Archivos\AlmacenEvidenciasArchivos;
use Qa\Persistencia\Configuracion\PoliticaAccesoEstatica;
use Qa\Persistencia\Configuracion\RelojSistema;
use Qa\Persistencia\EnMemoria\AlmacenEvidenciasEnMemoria;
use Qa\Persistencia\EnMemoria\RegistroAuditoriaEnMemoria;
use Qa\Persistencia\EnMemoria\RepositorioCampanasEnMemoria;
use Qa\Persistencia\EnMemoria\RepositorioCasosEnMemoria;
use Qa\Persistencia\EnMemoria\RepositorioDefectosEnMemoria;
use Qa\Persistencia\EnMemoria\TransaccionEnMemoria;
use Qa\Persistencia\Pdo\FabricaConexion;
use Qa\Persistencia\Pdo\PdoRegistroAuditoria;
use Qa\Persistencia\Pdo\PdoRepositorioCampanas;
use Qa\Persistencia\Pdo\PdoRepositorioCasos;
use Qa\Persistencia\Pdo\PdoRepositorioDefectos;
use Qa\Persistencia\Pdo\PdoTransaccion;

/**
 * Elige los adaptadores de cada puerto según la configuración: «pdo» (PostgreSQL + disco) o «memoria» (todo en memoria,
 * con database/datos-demo.php). Los casos de uso reciben los mismos puertos y no saben cuál les tocó.
 */
final class FabricaAdaptadores
{
    /** @return array{acceso: object, casos: object, campanas: object, defectos: object, almacen: object, auditoria: object, transaccion: object, reloj: object} */
    public static function crear(array $config, ?\PDO $pdo = null): array
    {
        return match ($config['persistencia'] ?? 'pdo') {
            'pdo' => self::pdo($config, $pdo ?? FabricaConexion::crear($config['db']['dsn'], $config['db']['usuario'], $config['db']['clave'])),
            'memoria' => self::memoria($config, require dirname(__DIR__, 2) . '/database/datos-demo.php'),
            default => throw new \InvalidArgumentException('persistencia desconocida: use «pdo» o «memoria»'),
        };
    }

    private static function pdo(array $config, \PDO $pdo): array
    {
        return [
            'acceso' => new PoliticaAccesoEstatica($config['permisos']),
            'casos' => new PdoRepositorioCasos($pdo),
            'campanas' => new PdoRepositorioCampanas($pdo),
            'defectos' => new PdoRepositorioDefectos($pdo),
            'almacen' => new AlmacenEvidenciasArchivos($config['evidencias']),
            'auditoria' => new PdoRegistroAuditoria($pdo),
            'transaccion' => new PdoTransaccion($pdo),
            'reloj' => new RelojSistema(),
        ];
    }

    /** @param array{casos: list<array>, matriz: list<array>} $datos */
    public static function memoria(array $config, array $datos): array
    {
        $casos = new RepositorioCasosEnMemoria();
        foreach ($datos['casos'] as [$tenant, $id, $autor, $titulo, $pre, $pasos, $prioridad, $requisitos, $aprobador]) {
            $casos->guardar(new CasoPrueba($tenant, $id, 1, $autor, $titulo, $pre, $pasos, $prioridad, $requisitos,
                $aprobador === null ? CasoPrueba::BORRADOR : CasoPrueba::APROBADO, $aprobador));
        }
        foreach ($datos['matriz'] as [$tenant, $funcionalidad, $caso, $critico]) {
            $casos->marcarEnMatriz($tenant, $funcionalidad, $caso, $critico);
        }
        $campanas = new RepositorioCampanasEnMemoria();
        $defectos = new RepositorioDefectosEnMemoria();
        $auditoria = new RegistroAuditoriaEnMemoria();

        return [
            'acceso' => new PoliticaAccesoEstatica($config['permisos']),
            'casos' => $casos,
            'campanas' => $campanas,
            'defectos' => $defectos,
            'almacen' => new AlmacenEvidenciasEnMemoria(),
            'auditoria' => $auditoria,
            'transaccion' => new TransaccionEnMemoria([$casos, $campanas, $defectos, $auditoria]),
            'reloj' => new RelojSistema(),
        ];
    }
}
