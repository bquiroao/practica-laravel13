<?php

declare(strict_types=1);

use E2e\Aplicacion\Puertos\RegistroAuditoria;
use E2e\Aplicacion\Resultados\Salida;
use E2e\Dominio\Excepciones\ErrorPersistencia;
use E2e\Dominio\Modelo\Ejecucion;
use E2e\Dominio\Modelo\ResultadoEscenario;
use E2e\Dominio\Puertos\RepositorioEjecuciones;
use E2e\Persistencia\Configuracion\RelojFijo;
use E2e\Persistencia\EnMemoria\AlmacenEvidenciasEnMemoria;
use E2e\Persistencia\EnMemoria\RegistroAuditoriaEnMemoria;
use E2e\Persistencia\EnMemoria\RepositorioEjecucionesEnMemoria;
use E2e\Persistencia\FabricaAdaptadores;
use E2e\Presentacion\Http\Kernel;

/** Decorador de ejecuciones que puede fallar al leer o al escribir (simula una base caída). */
final class EjecucionesConFalla implements RepositorioEjecuciones
{
    public bool $caidaAlLeer = false;
    public bool $caidaAlEscribir = false;

    public function __construct(public readonly RepositorioEjecucionesEnMemoria $real)
    {
    }

    private function leer(): void
    {
        if ($this->caidaAlLeer) {
            throw new ErrorPersistencia('base caída (doble)');
        }
    }

    private function escribir(): void
    {
        if ($this->caidaAlEscribir) {
            throw new ErrorPersistencia('base caída (doble)');
        }
    }

    public function crear(Ejecucion $e): Ejecucion
    {
        $this->escribir();

        return $this->real->crear($e);
    }

    public function actualizarEstado(Ejecucion $e): void
    {
        $this->escribir();
        $this->real->actualizarEstado($e);
    }

    public function agregarResultado(string $tenant, int $ejecucion, ResultadoEscenario $r): ResultadoEscenario
    {
        $this->escribir();

        return $this->real->agregarResultado($tenant, $ejecucion, $r);
    }

    public function porId(string $tenant, int $id): ?Ejecucion
    {
        $this->leer();

        return $this->real->porId($tenant, $id);
    }

    public function resultadoPorClave(string $tenant, string $clave): ?array
    {
        $this->leer();

        return $this->real->resultadoPorClave($tenant, $clave);
    }

    public function deVersion(string $tenant, string $version): array
    {
        $this->leer();

        return $this->real->deVersion($tenant, $version);
    }
}

/** Auditoría que puede fallar: prueba que la transacción deshace lo escrito antes. */
final class AuditoriaConFalla implements RegistroAuditoria
{
    public bool $caida = false;

    public function __construct(public readonly RegistroAuditoriaEnMemoria $real)
    {
    }

    public function registrar(string $accion, string $tenant, string $usuario, string $recurso): void
    {
        if ($this->caida) {
            throw new ErrorPersistencia('auditoría caída (doble)');
        }
        $this->real->registrar($accion, $tenant, $usuario, $recurso);
    }
}

/**
 * Arma el Micro-HIS con los adaptadores EN MEMORIA de producción (FabricaAdaptadores::memoria con datos-demo.php),
 * un reloj fijo y decoradores que pueden fallar. Usa la MISMA raíz de composición que producción.
 */
final class Escenario
{
    public const DIGEST = 'sha256:4f1c0a7b2d9e8f6a5b3c1d0e9f8a7b6c5d4e3f2a1b0c9d8e7f6a5b4c3d2e1f00';
    public const TRAZA = "traza playwright · paciente sintético SINT-0042\n";

    public readonly EjecucionesConFalla $ejecuciones;
    public readonly AuditoriaConFalla $auditoria;
    public readonly AlmacenEvidenciasEnMemoria $almacen;
    public readonly RelojFijo $reloj;
    /** @var array<string, object> */
    public readonly array $p;
    /** @var array<string, object> */
    public readonly array $uc;
    private static int $claves = 0;

    public function __construct(array $politica = [])
    {
        $config = require dirname(__DIR__, 2) . '/config/config.php';
        $p = FabricaAdaptadores::memoria($config, require dirname(__DIR__, 2) . '/database/datos-demo.php');
        $this->ejecuciones = new EjecucionesConFalla($p['ejecuciones']);
        $this->auditoria = new AuditoriaConFalla($p['auditoria']);
        $this->almacen = $p['almacen'];
        $this->reloj = new RelojFijo(new DateTimeImmutable('2026-10-29T14:00:00Z'));
        $this->p = ['ejecuciones' => $this->ejecuciones, 'auditoria' => $this->auditoria, 'reloj' => $this->reloj] + $p;
        $this->uc = construirCasosDeUso($this->p, $politica + $config['politica_gate']);
    }

    /** @var list<string> líneas del registro de acceso */
    public array $accesos = [];

    public function kernel(bool $registroQueFalla = false): Kernel
    {
        $config = require dirname(__DIR__, 2) . '/config/config.php';

        return construirKernel($this->uc, $config['tokens_demo'], $registroQueFalla ? static fn () => throw new RuntimeException('disco lleno')
            : function (string $l): void { $this->accesos[] = $l; });
    }

    public function auditoriaReal(): RegistroAuditoriaEnMemoria
    {
        return $this->auditoria->real;
    }

    /** Registra la versión 2.4.0 con el plan de todo el catálogo de hosp-norte e inicia una ejecución; devuelve su id. */
    public function ejecucionEnCurso(array $plan = ['E2E-101', 'E2E-102', 'E2E-103', 'E2E-104', 'E2E-105', 'E2E-106'], string $numero = '2.4.0',
        string $digest = self::DIGEST): int
    {
        $this->uc['version']->ejecutar('pipeline-ci', 'hosp-norte', ['numero' => $numero, 'commit' => 'c56f169', 'digest' => $digest, 'modulos' => ['admision']]);
        $p = $this->uc['plan']->ejecutar('ing-qa', 'hosp-norte', $numero, $plan);
        $i = $this->uc['iniciar']->ejecutar('pipeline-ci', 'hosp-norte', $numero, $digest, 'staging');
        if ($i->codigo !== 'EJECUCION_INICIADA') {
            throw new RuntimeException("no se pudo iniciar la ejecución de prueba: {$p->codigo} / {$i->codigo}");
        }

        return $i->datos['id'];
    }

    /** El pipeline reporta un resultado; FALLA lleva una traza salvo que se pida otra cosa. */
    public function reportar(int $ejecucion, string $escenario, string $resultado = 'PASA', int $intentos = 1, ?array $evidencias = null, ?string $clave = null): Salida
    {
        $evidencias ??= $resultado === 'FALLA' || $intentos > 1 ? [['tipo' => 'traza', 'bytes' => self::TRAZA . $escenario]] : [];

        return $this->uc['reportar']->ejecutar('pipeline-ci', 'hosp-norte', $ejecucion, $clave ?? self::clave(),
            ['escenario' => $escenario, 'resultado' => $resultado, 'intentos' => $intentos, 'evidencias' => $evidencias]);
    }

    /** Reporta PASA para todos los escenarios del plan que no se indiquen y completa la ejecución. */
    public function completar(int $ejecucion, array $distintos = []): void
    {
        foreach (['E2E-101', 'E2E-102', 'E2E-103', 'E2E-104', 'E2E-105', 'E2E-106'] as $esc) {
            [$res, $int] = $distintos[$esc] ?? ['PASA', 1];
            $this->reportar($ejecucion, $esc, $res, $int);
        }
        $c = $this->uc['cerrar']->ejecutar('pipeline-ci', 'hosp-norte', $ejecucion);
        if ($c->codigo !== 'EJECUCION_COMPLETADA') {
            throw new RuntimeException('no se pudo completar: ' . $c->codigo);
        }
    }

    public static function clave(): string
    {
        return sprintf('prueba-%06d', ++self::$claves);
    }
}
