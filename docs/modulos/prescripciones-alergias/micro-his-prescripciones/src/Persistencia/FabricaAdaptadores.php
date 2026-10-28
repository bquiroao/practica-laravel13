<?php

declare(strict_types=1);

namespace Prescripciones\Persistencia;

use Prescripciones\Dominio\Modelo\Alergia;
use Prescripciones\Dominio\Modelo\Medicamento;
use Prescripciones\Persistencia\Configuracion\PoliticaAccesoEstatica;
use Prescripciones\Persistencia\Configuracion\RelojSistema;
use Prescripciones\Persistencia\EnMemoria\CatalogoEnMemoria;
use Prescripciones\Persistencia\EnMemoria\FuenteAlergiasEnMemoria;
use Prescripciones\Persistencia\EnMemoria\PacientesEnMemoria;
use Prescripciones\Persistencia\EnMemoria\RegistroAuditoriaEnMemoria;
use Prescripciones\Persistencia\EnMemoria\RepositorioPrescripcionesEnMemoria;
use Prescripciones\Persistencia\EnMemoria\TransaccionEnMemoria;
use Prescripciones\Persistencia\Http\CircuitoInterruptor;
use Prescripciones\Persistencia\Http\HttpFuenteAlergias;
use Prescripciones\Persistencia\Pdo\FabricaConexion;
use Prescripciones\Persistencia\Pdo\PdoCatalogoMedicamentos;
use Prescripciones\Persistencia\Pdo\PdoFuenteAlergias;
use Prescripciones\Persistencia\Pdo\PdoPacientes;
use Prescripciones\Persistencia\Pdo\PdoRegistroAuditoria;
use Prescripciones\Persistencia\Pdo\PdoRepositorioPrescripciones;
use Prescripciones\Persistencia\Pdo\PdoTransaccion;

/**
 * Único punto de decisión del modo de persistencia (config «persistencia»: pdo | memoria).
 * Devuelve un adaptador por cada puerto; el resto de la aplicación no sabe cuál recibió.
 */
final class FabricaAdaptadores
{
    /** @param array<string, mixed> $config @return array<string, object> */
    public static function crear(array $config): array
    {
        $puertos = match ($config['persistencia']) {
            'pdo' => self::pdo(FabricaConexion::crear($config['db']['dsn'], $config['db']['usuario'], $config['db']['clave']), $config),
            'memoria' => self::memoria(require dirname(__DIR__, 2) . '/database/datos-demo.php', $config),
            default => throw new \InvalidArgumentException('persistencia debe ser «pdo» o «memoria».'),
        };
        // Semana 5: el Expediente como servicio aparte (evaluación de la frontera). Por omisión, la base del hospital.
        if (($config['fuente_alergias'] ?? 'base') === 'http') {
            $puertos['alergias'] = new HttpFuenteAlergias($config['expediente_url'], new CircuitoInterruptor(new RelojSistema()));
        }

        return $puertos;
    }

    /** @param array<string, mixed> $config @return array<string, object> */
    public static function pdo(\PDO $pdo, array $config): array
    {
        return [
            'acceso' => new PoliticaAccesoEstatica($config['permisos']),
            'pacientes' => new PdoPacientes($pdo),
            'catalogo' => new PdoCatalogoMedicamentos($pdo),
            'alergias' => new PdoFuenteAlergias($pdo),
            'repositorio' => new PdoRepositorioPrescripciones($pdo),
            'auditoria' => new PdoRegistroAuditoria($pdo),
            'transaccion' => new PdoTransaccion($pdo),
            'reloj' => new RelojSistema(),
        ];
    }

    /**
     * @param array{pacientes: array<string, list<string>>, alergias: array<string, list<list<mixed>>>, medicamentos: list<list<mixed>>, grupos: array<string, string>} $datos
     * @param array<string, mixed> $config
     * @return array<string, object>
     */
    public static function memoria(array $datos, array $config): array
    {
        $repositorio = new RepositorioPrescripcionesEnMemoria();
        $auditoria = new RegistroAuditoriaEnMemoria();

        return [
            'acceso' => new PoliticaAccesoEstatica($config['permisos']),
            'pacientes' => new PacientesEnMemoria($datos['pacientes']),
            'catalogo' => new CatalogoEnMemoria(array_map(static fn (array $m): Medicamento => new Medicamento(...$m), $datos['medicamentos']), $datos['grupos']),
            'alergias' => new FuenteAlergiasEnMemoria(array_map(static fn (array $lista): array => array_map(static fn (array $a): Alergia => new Alergia(...$a), $lista),
                $datos['alergias'])),
            'repositorio' => $repositorio,
            'auditoria' => $auditoria,
            'transaccion' => new TransaccionEnMemoria([$repositorio, $auditoria]),
            'reloj' => new RelojSistema(),
        ];
    }
}
