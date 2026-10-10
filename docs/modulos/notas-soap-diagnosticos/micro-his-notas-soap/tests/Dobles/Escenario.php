<?php

declare(strict_types=1);

namespace NotasSoap\Pruebas\Dobles;

use NotasSoap\Aplicacion\CasosDeUso\ConsultarNota;
use NotasSoap\Aplicacion\CasosDeUso\FirmarNota;
use NotasSoap\Aplicacion\CasosDeUso\RegistrarNotaConDiagnostico;
use NotasSoap\Aplicacion\Comandos\RegistrarNotaComando;
use NotasSoap\Presentacion\Http\AutenticadorTokenDemo;
use NotasSoap\Presentacion\Http\Kernel;
use NotasSoap\Presentacion\Http\Solicitud;

/** Arma un sistema completo con dobles en memoria y datos ficticios. Uno nuevo por prueba. */
final class Escenario
{
    public const TOKENS = [
        'token-mendez' => ['dra-mendez', 'hosp-oriente', ['medico']],
        'token-lopez' => ['dr-lopez', 'hosp-oriente', ['medico']],
        'token-ana' => ['enf-ana', 'hosp-oriente', ['enfermeria']],
    ];

    public readonly RepositorioNotasEnMemoria $notas;
    public readonly RepositorioDiagnosticosEnMemoria $diagnosticos;
    public readonly RegistroAuditoriaEnMemoria $auditoria;
    public readonly VerificadorAccesoEnMemoria $acceso;
    public readonly RegistrarNotaConDiagnostico $registrar;
    public readonly FirmarNota $firmar;
    public readonly ConsultarNota $consultar;
    public readonly Kernel $kernel;

    public function __construct()
    {
        $this->notas = new RepositorioNotasEnMemoria();
        $this->diagnosticos = new RepositorioDiagnosticosEnMemoria();
        $this->auditoria = new RegistroAuditoriaEnMemoria();
        $this->acceso = new VerificadorAccesoEnMemoria(['epi-100' => ['dra-mendez'], 'epi-200' => ['dra-mendez']]);
        $expedientes = new RepositorioExpedientesEnMemoria(
            ['exp-carlos' => ['paciente' => 'pac-carlos', 'abierto' => true], 'exp-rosa' => ['paciente' => 'pac-rosa', 'abierto' => false]],
            ['epi-100' => 'pac-carlos', 'epi-200' => 'pac-rosa'],
        );
        $tx = new TransaccionEnMemoria([$this->notas, $this->diagnosticos, $this->auditoria]);
        $ids = new GeneradorSecuencial();
        $reloj = new RelojFijo();

        $this->registrar = new RegistrarNotaConDiagnostico($this->acceso, $expedientes, $this->notas, $this->diagnosticos, $this->auditoria, $tx, $ids, $reloj);
        $this->firmar = new FirmarNota($this->notas, $this->auditoria, $tx, $reloj);
        $this->consultar = new ConsultarNota($this->notas, $this->diagnosticos);
        $this->kernel = construirKernel(new AutenticadorTokenDemo(self::TOKENS), $this->acceso, $expedientes, $this->notas, $this->diagnosticos, $this->auditoria, $tx, $ids, $reloj);
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
