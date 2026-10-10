<?php

declare(strict_types=1);

namespace NotasSoap\Pruebas\Dobles;

use NotasSoap\Aplicacion\CasosDeUso\ConsultarNota;
use NotasSoap\Aplicacion\CasosDeUso\FirmarNota;
use NotasSoap\Aplicacion\CasosDeUso\RegistrarNotaConDiagnostico;
use NotasSoap\Aplicacion\Comandos\RegistrarNotaComando;
use NotasSoap\Aplicacion\Integracion\RepositorioNotasConEventos;
use NotasSoap\Aplicacion\Puertos\BandejaSalida;
use NotasSoap\Dominio\Puertos\RepositorioDiagnosticos;
use NotasSoap\Dominio\Puertos\VerificadorAcceso;
use NotasSoap\Persistencia\EnMemoria\AlmacenesEnMemoria;
use NotasSoap\Persistencia\EnMemoria\BandejaSalidaEnMemoria;
use NotasSoap\Persistencia\EnMemoria\RegistroAuditoriaEnMemoria;
use NotasSoap\Persistencia\EnMemoria\RepositorioNotasEnMemoria;
use NotasSoap\Presentacion\Http\AutenticadorTokenDemo;
use NotasSoap\Presentacion\Http\Kernel;
use NotasSoap\Presentacion\Http\Solicitud;

/**
 * Sistema completo con los adaptadores EN MEMORIA de src/ (los mismos del modo demostración)
 * y los datos de config/datos-demo-memoria.php. Uno nuevo por prueba.
 * Se le pueden cambiar piezas para provocar fallas (diagnósticos, acceso o bandeja que fallan).
 */
final class Escenario
{
    public const TOKENS = [
        'token-mendez' => ['dra-mendez', 'hosp-oriente', ['medico']],
        'token-lopez' => ['dr-lopez', 'hosp-oriente', ['medico']],
        'token-ana' => ['enf-ana', 'hosp-oriente', ['enfermeria']],
    ];

    public readonly AlmacenesEnMemoria $almacenes;
    public readonly RepositorioNotasEnMemoria $notas;
    public readonly RegistroAuditoriaEnMemoria $auditoria;
    public readonly BandejaSalidaEnMemoria $bandeja;
    public readonly RegistrarNotaConDiagnostico $registrar;
    public readonly FirmarNota $firmar;
    public readonly ConsultarNota $consultar;
    public readonly Kernel $kernel;

    public function __construct(
        ?RepositorioDiagnosticos $diagnosticos = null,
        ?VerificadorAcceso $acceso = null,
        ?BandejaSalida $bandeja = null,
    ) {
        $this->almacenes = $a = new AlmacenesEnMemoria(require dirname(__DIR__, 2) . '/config/datos-demo-memoria.php');
        $this->notas = $a->notas;
        $this->auditoria = $a->auditoria;
        $this->bandeja = $a->bandeja;
        $ids = new GeneradorSecuencial();
        $reloj = new RelojFijo();
        $notas = new RepositorioNotasConEventos($a->notas, $bandeja ?? $a->bandeja, $ids);
        $diagnosticos ??= $a->diagnosticos;
        $acceso ??= $a->acceso;

        $this->registrar = new RegistrarNotaConDiagnostico($acceso, $a->expedientes, $notas, $diagnosticos, $a->auditoria, $a->transaccion, $ids, $reloj);
        $this->firmar = new FirmarNota($notas, $diagnosticos, $a->auditoria, $a->transaccion, $reloj);
        $this->consultar = new ConsultarNota($notas, $diagnosticos);
        $this->kernel = construirKernel(new AutenticadorTokenDemo(self::TOKENS), $acceso, $a->expedientes, $notas, $diagnosticos, $a->auditoria, $a->transaccion, $ids, $reloj);
    }

    /** Comando válido: la Dra. Méndez registra la nota de Carlos (neumonía). */
    public static function comando(array $cambios = []): RegistrarNotaComando
    {
        $d = array_replace([
            'usuario' => 'dra-mendez', 'hospitalUsuario' => 'hosp-oriente', 'roles' => ['medico'], 'hospital' => 'hosp-oriente',
            'expediente' => 'exp-carlos', 'episodio' => 'epi-100',
            'S' => 'Tos productiva y fiebre de 3 días', 'O' => 'T 38.6 °C, estertores en base derecha',
            'A' => 'Probable neumonía adquirida en la comunidad', 'P' => 'Radiografía de tórax y antibiótico',
            'codigo' => 'J18.9', 'descripcion' => 'Neumonía no especificada', 'tipo' => 'primary',
        ], $cambios);

        return new RegistrarNotaComando($d['usuario'], $d['hospitalUsuario'], $d['roles'], $d['hospital'], $d['expediente'], $d['episodio'],
            $d['S'], $d['O'], $d['A'], $d['P'], $d['codigo'], $d['descripcion'], $d['tipo']);
    }

    /** Petición HTTP válida para POST /api/v1/notas-soap. */
    public static function peticion(array $cuerpo = [], array $cabeceras = [], string $metodo = 'POST', string $ruta = '/api/v1/notas-soap'): Solicitud
    {
        $c = array_replace([
            'medical_record_id' => 'exp-carlos', 'care_episode_id' => 'epi-100',
            'subjetivo' => 'Tos productiva y fiebre de 3 días', 'objetivo' => 'T 38.6 °C, estertores en base derecha',
            'analisis' => 'Probable neumonía adquirida en la comunidad', 'plan' => 'Radiografía de tórax y antibiótico',
            'diagnostico' => ['code' => 'J18.9', 'description' => 'Neumonía no especificada', 'type' => 'primary'],
        ], $cuerpo);

        return new Solicitud($metodo, $ruta, array_replace(['x-tenant-id' => 'hosp-oriente', 'authorization' => 'Bearer token-mendez'], $cabeceras), $c);
    }
}
