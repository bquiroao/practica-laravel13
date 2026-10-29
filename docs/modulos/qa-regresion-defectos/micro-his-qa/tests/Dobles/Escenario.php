<?php

declare(strict_types=1);

use Qa\Dominio\Modelo\Defecto;
use Qa\Dominio\Modelo\EstadoDefecto;
use Qa\Dominio\Modelo\Severidad;
use Qa\Persistencia\EnMemoria\AlmacenEvidenciasEnMemoria;
use Qa\Persistencia\EnMemoria\RegistroAuditoriaEnMemoria;
use Qa\Persistencia\EnMemoria\RepositorioCampanasEnMemoria;
use Qa\Persistencia\EnMemoria\RepositorioDefectosEnMemoria;
use Qa\Persistencia\EnMemoria\TransaccionEnMemoria;
use Qa\Persistencia\FabricaAdaptadores;
use Qa\Presentacion\Http\Autenticacion\AutenticadorTokenDemo;
use Qa\Presentacion\Http\Kernel;

/**
 * Arma el Micro-HIS completo con los adaptadores EN MEMORIA de producción (FabricaAdaptadores::memoria, con
 * database/datos-demo.php) y, encima, decoradores que pueden fallar. Usa la MISMA raíz de composición que producción.
 */
final class Escenario
{
    public const PNG = "\x89PNG\r\n\x1a\n" . 'captura-de-prueba';

    public readonly CasosConFalla $casos;
    public readonly CampanasConFalla $campanas;
    public readonly DefectosConFalla $defectos;
    public readonly AuditoriaConFalla $auditoria;
    public readonly AlmacenEvidenciasEnMemoria $almacen;
    public readonly TransaccionEnMemoria $transaccion;
    /** @var array<string, object> */
    public readonly array $uc;
    private static int $claves = 0;

    public function __construct()
    {
        $config = require dirname(__DIR__, 2) . '/config/config.php';
        $p = FabricaAdaptadores::memoria($config, require dirname(__DIR__, 2) . '/database/datos-demo.php');
        $this->casos = new CasosConFalla($p['casos']);
        $this->campanas = new CampanasConFalla($p['campanas']);
        $this->defectos = new DefectosConFalla($p['defectos']);
        $this->auditoria = new AuditoriaConFalla($p['auditoria']);
        $this->almacen = $p['almacen'];
        $this->transaccion = $p['transaccion'];
        $this->uc = construirCasosDeUso(['casos' => $this->casos, 'campanas' => $this->campanas, 'defectos' => $this->defectos,
            'auditoria' => $this->auditoria, 'reloj' => new RelojFijo()] + $p);
    }

    public function kernel(): Kernel
    {
        $config = require dirname(__DIR__, 2) . '/config/config.php';

        return construirKernel($this->uc, new AutenticadorTokenDemo($config['tokens_demo']));
    }

    /** Lo guardado de verdad (sin los decoradores). */
    public function intentos(): array
    {
        return $this->memCampanas()->todosLosIntentos();
    }

    public function memCampanas(): RepositorioCampanasEnMemoria
    {
        return $this->campanas->real;
    }

    public function memDefectos(): RepositorioDefectosEnMemoria
    {
        return $this->defectos->real;
    }

    public function memAuditoria(): RegistroAuditoriaEnMemoria
    {
        return $this->auditoria->real;
    }

    /** Un defecto ya clasificado en un estado dado (para preparar una re-ejecución). */
    public function sembrarDefecto(int $campana, string $caso, EstadoDefecto $estado): Defecto
    {
        return $this->memDefectos()->crear(new Defecto(null, 'hosp-norte', $campana, $caso, 'Sembrado', ['paso'], Severidad::MAYOR, true,
            'tito-tester', $estado, Severidad::MAYOR, 'ALTA'));
    }

    /** Planifica e inicia una campaña sobre «expediente» con esos casos; devuelve su id. */
    public function campanaEnCurso(array $casos = ['TC-107', 'TC-108', 'TC-109'], array $funcionalidades = ['expediente'], float $umbral = 0.95): int
    {
        $p = $this->uc['planificar']->ejecutar('lider-qa', 'hosp-norte', ['nombre' => 'Regresión H4', 'funcionalidades' => $funcionalidades,
            'casos' => $casos, 'umbral' => $umbral]);
        $i = $this->uc['iniciar']->ejecutar('lider-qa', 'hosp-norte', $p->datos['id'], '1.4.0+abc1234');
        if ($i->codigo !== 'CAMPANA_INICIADA') {
            throw new \RuntimeException('no se pudo iniciar la campaña de prueba: ' . $i->codigo);
        }

        return $p->datos['id'];
    }

    /** Registra un resultado como el tester; las FALLA llevan captura y defecto salvo que se indique otra cosa. */
    public function registrar(int $campana, string $caso, string $resultado, array $extra = [], string $tester = 'tito-tester', ?string $clave = null): \Qa\Aplicacion\Resultados\Salida
    {
        $datos = ['caso' => $caso, 'resultado' => $resultado];
        if (in_array($resultado, ['FALLA', 'BLOQUEADO'], true)) {
            $datos['evidencias'] = [['tipo' => 'captura', 'bytes' => self::PNG . $caso]];
        }
        if ($resultado === 'FALLA') {
            $datos['defecto'] = ['titulo' => "Falla en $caso", 'pasos' => ['Ejecutar el caso'], 'severidad' => 'MAYOR'];
        }

        return $this->uc['registrar']->ejecutar($tester, 'hosp-norte', $campana, $clave ?? self::clave(), array_merge($datos, $extra));
    }

    public static function clave(): string
    {
        return sprintf('prueba-%06d', ++self::$claves);
    }
}
