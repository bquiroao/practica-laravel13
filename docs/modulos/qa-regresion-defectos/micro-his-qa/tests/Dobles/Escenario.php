<?php

declare(strict_types=1);

use Qa\Dominio\Modelo\CasoPrueba;
use Qa\Persistencia\Configuracion\PoliticaAccesoEstatica;
use Qa\Presentacion\Http\Autenticacion\AutenticadorTokenDemo;
use Qa\Presentacion\Http\Kernel;

/**
 * Arma el Micro-HIS completo con dobles y los mismos datos que database/datos-demo.sql.
 * Usa la MISMA raíz de composición que producción (construirCasosDeUso, construirKernel).
 */
final class Escenario
{
    public const PNG = "\x89PNG\r\n\x1a\n" . 'captura-de-prueba';

    public readonly RepositorioCasosFalso $casos;
    public readonly RepositorioCampanasFalso $campanas;
    public readonly RepositorioDefectosFalso $defectos;
    public readonly AlmacenFalso $almacen;
    public readonly AuditoriaFalsa $auditoria;
    public readonly TransaccionFalsa $transaccion;
    /** @var array<string, object> */
    public readonly array $uc;
    private static int $claves = 0;

    public function __construct()
    {
        $config = require dirname(__DIR__, 2) . '/config/config.php';
        $this->casos = new RepositorioCasosFalso();
        $this->campanas = new RepositorioCampanasFalso();
        $this->defectos = new RepositorioDefectosFalso();
        $this->almacen = new AlmacenFalso();
        $this->auditoria = new AuditoriaFalsa();
        $this->transaccion = new TransaccionFalsa([$this->casos, $this->campanas, $this->defectos, $this->auditoria]);
        $this->sembrar();
        $this->uc = construirCasosDeUso([
            'acceso' => new PoliticaAccesoEstatica($config['permisos']),
            'casos' => $this->casos,
            'campanas' => $this->campanas,
            'defectos' => $this->defectos,
            'almacen' => $this->almacen,
            'auditoria' => $this->auditoria,
            'transaccion' => $this->transaccion,
            'reloj' => new RelojFijo(),
        ]);
    }

    public function kernel(): Kernel
    {
        $config = require dirname(__DIR__, 2) . '/config/config.php';

        return construirKernel($this->uc, new AutenticadorTokenDemo($config['tokens_demo']));
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

    private function sembrar(): void
    {
        $paso = [['accion' => 'Ejecutar', 'esperado' => 'Se rechaza']];
        $requisitos = ['TC-101' => ['RF-01'], 'TC-102' => ['RF-02'], 'TC-103' => ['RF-03'], 'TC-104' => ['RF-04'], 'TC-105' => ['RF-05'],
            'TC-106' => ['RF-06'], 'TC-107' => ['RF-07'], 'TC-108' => ['RF-07', 'RF-08'], 'TC-109' => ['RF-07'], 'TC-110' => ['RF-09']];
        foreach ($requisitos as $id => $rf) {
            $this->casos->casos["hosp-norte|$id|1"] = new CasoPrueba('hosp-norte', $id, 1, 'ana-qa', "Caso $id", '', $paso, 'ALTA', $rf,
                CasoPrueba::APROBADO, 'lider-qa');
        }
        $this->casos->casos['hosp-norte|TC-111|1'] = new CasoPrueba('hosp-norte', 'TC-111', 1, 'ana-qa', 'Caso TC-111', '', $paso, 'MEDIA', ['RF-06'],
            CasoPrueba::BORRADOR);
        $this->casos->casos['hosp-sur|TC-101|1'] = new CasoPrueba('hosp-sur', 'TC-101', 1, 'qa-sur', 'Caso sur', '', $paso, 'ALTA', ['RF-01'],
            CasoPrueba::APROBADO, 'lider-sur');
        $this->casos->matriz = [
            'hosp-norte|identidad-central' => ['TC-101' => true], 'hosp-norte|mpi' => ['TC-102' => true],
            'hosp-norte|consentimiento' => ['TC-103' => true], 'hosp-norte|sincronizacion' => ['TC-104' => false],
            'hosp-norte|identidad-hospital' => ['TC-105' => false], 'hosp-norte|agenda-camas' => ['TC-106' => true],
            'hosp-norte|expediente' => ['TC-107' => true, 'TC-108' => true, 'TC-109' => false, 'TC-110' => false],
            'hosp-sur|identidad-central' => ['TC-101' => true],
        ];
    }
}
