<?php

declare(strict_types=1);

namespace NotasSoap\Pruebas\Dobles;

use NotasSoap\Aplicacion\CasosDeUso\ConsultarNota;
use NotasSoap\Aplicacion\CasosDeUso\FirmarNota;
use NotasSoap\Aplicacion\CasosDeUso\ListarNotasExpediente;
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
use NotasSoap\Presentacion\Http\Autorizacion\PermisosPorRol;
use NotasSoap\Presentacion\Http\Kernel;
use NotasSoap\Presentacion\Http\Observabilidad\RegistroAcceso;
use NotasSoap\Presentacion\Http\Observabilidad\RegistroAccesoEnMemoria;
use NotasSoap\Presentacion\Http\Solicitud;

/**
 * Sistema completo con los adaptadores EN MEMORIA de src/ (los mismos del modo demostración)
 * y los datos de config/datos-demo-memoria.php. Uno nuevo por prueba.
 * Se le pueden cambiar piezas para provocar fallas (diagnósticos, acceso, bandeja o registro de acceso).
 */
final class Escenario
{
    public const TOKENS = [
        'token-mendez' => ['dra-mendez', 'hosp-oriente', ['medico']],
        'token-lopez' => ['dr-lopez', 'hosp-oriente', ['medico']],
        'token-ana' => ['enf-ana', 'hosp-oriente', ['enfermeria']],
    ];
    public const PERMISOS = [
        'medico' => ['notas_soap.registrar', 'notas_soap.firmar', 'notas_soap.consultar', 'notas_soap.corregir'],
        'enfermeria' => ['notas_soap.consultar'],
    ];

    public readonly AlmacenesEnMemoria $almacenes;
    public readonly RepositorioNotasEnMemoria $notas;
    public readonly RegistroAuditoriaEnMemoria $auditoria;
    public readonly BandejaSalidaEnMemoria $bandeja;
    public readonly RegistroAcceso $registroAcceso;
    public readonly RegistrarNotaConDiagnostico $registrar;
    public readonly FirmarNota $firmar;
    public readonly ConsultarNota $consultar;
    public readonly ListarNotasExpediente $listar;
    public readonly Kernel $kernel;

    public function __construct(
        ?RepositorioDiagnosticos $diagnosticos = null,
        ?VerificadorAcceso $acceso = null,
        ?BandejaSalida $bandeja = null,
        ?RegistroAcceso $registroAcceso = null,
    ) {
        $this->almacenes = $al = new AlmacenesEnMemoria(require dirname(__DIR__, 2) . '/config/datos-demo-memoria.php');
        $this->notas = $al->notas;
        $this->auditoria = $al->auditoria;
        $this->bandeja = $al->bandeja;
        $this->registroAcceso = $registroAcceso ?? new RegistroAccesoEnMemoria();
        $ids = new GeneradorSecuencial();
        $reloj = new RelojFijo();

        $a = $al->conjunto();
        $a = $a->conNotas(new RepositorioNotasConEventos($a->notas, $bandeja ?? $al->bandeja, $ids));
        $a = $diagnosticos === null ? $a : $a->conDiagnosticos($diagnosticos);
        $a = $acceso === null ? $a : $a->conAcceso($acceso);

        $this->registrar = new RegistrarNotaConDiagnostico($a->acceso, $a->expedientes, $a->notas, $a->diagnosticos, $a->auditoria, $a->transaccion, $ids, $reloj, $a->idempotencia);
        $this->firmar = new FirmarNota($a->notas, $a->diagnosticos, $a->auditoria, $a->transaccion, $reloj);
        $this->consultar = new ConsultarNota($a->notas, $a->diagnosticos);
        $this->listar = new ListarNotasExpediente($a->notas, $a->diagnosticos);
        $this->kernel = construirKernel(new AutenticadorTokenDemo(self::TOKENS), $a, $ids, $reloj, new PermisosPorRol(self::PERMISOS), $this->registroAcceso);
    }

    /** Configuración mínima para crearKernelEnMemoria(). */
    public static function config(): array
    {
        return ['tokens_demo' => self::TOKENS, 'permisos_por_rol' => self::PERMISOS];
    }

    /** Comando válido: la Dra. Méndez registra la nota de Carlos (neumonía). */
    public static function comando(array $cambios = []): RegistrarNotaComando
    {
        $d = array_replace([
            'usuario' => 'dra-mendez', 'hospitalUsuario' => 'hosp-oriente', 'roles' => ['medico'], 'hospital' => 'hosp-oriente',
            'expediente' => 'exp-carlos', 'episodio' => 'epi-100',
            'S' => 'Tos productiva y fiebre de 3 días', 'O' => 'T 38.6 °C, estertores en base derecha',
            'A' => 'Probable neumonía adquirida en la comunidad', 'P' => 'Radiografía de tórax y antibiótico',
            'codigo' => 'J18.9', 'descripcion' => 'Neumonía no especificada', 'tipo' => 'primary', 'clave' => null,
        ], $cambios);

        return new RegistrarNotaComando($d['usuario'], $d['hospitalUsuario'], $d['roles'], $d['hospital'], $d['expediente'], $d['episodio'],
            $d['S'], $d['O'], $d['A'], $d['P'], $d['codigo'], $d['descripcion'], $d['tipo'], $d['clave']);
    }

    /** Petición HTTP válida para POST /api/v1/notas-soap. */
    public static function peticion(array $cuerpo = [], array $cabeceras = [], string $metodo = 'POST', string $ruta = '/api/v1/notas-soap', array $consulta = []): Solicitud
    {
        $c = array_replace([
            'medical_record_id' => 'exp-carlos', 'care_episode_id' => 'epi-100',
            'subjetivo' => 'Tos productiva y fiebre de 3 días', 'objetivo' => 'T 38.6 °C, estertores en base derecha',
            'analisis' => 'Probable neumonía adquirida en la comunidad', 'plan' => 'Radiografía de tórax y antibiótico',
            'diagnostico' => ['code' => 'J18.9', 'description' => 'Neumonía no especificada', 'type' => 'primary'],
        ], $cuerpo);

        return new Solicitud($metodo, $ruta, array_replace(['x-tenant-id' => 'hosp-oriente', 'authorization' => 'Bearer token-mendez'], $cabeceras), $c, $consulta);
    }

    /**
     * Semana 6: petición válida para POST /api/v1/notas-soap/{id}/correcciones.
     * El expediente y el episodio NO van en el cuerpo: se heredan de la nota que se corrige.
     */
    public static function peticionCorreccion(string $notaId, array $cuerpo = [], array $cabeceras = []): Solicitud
    {
        $c = array_replace([
            'correction_reason' => 'Se anotó la temperatura equivocada: era 37.6 °C, no 38.6 °C',
            'subjetivo' => 'Tos productiva y fiebre de 3 días', 'objetivo' => 'T 37.6 °C, estertores en base derecha',
            'analisis' => 'Probable neumonía adquirida en la comunidad', 'plan' => 'Radiografía de tórax y antibiótico',
            'diagnostico' => ['code' => 'J18.9', 'description' => 'Neumonía no especificada', 'type' => 'primary'],
        ], $cuerpo);

        return new Solicitud('POST', "/api/v1/notas-soap/$notaId/correcciones",
            array_replace(['x-tenant-id' => 'hosp-oriente', 'authorization' => 'Bearer token-mendez'], $cabeceras), $c, []);
    }

    /** Registra y firma una nota de la Dra. Méndez por HTTP; devuelve su id. */
    public static function notaFirmada(Kernel $k): string
    {
        $id = $k->manejar(self::peticion())->cuerpo['clinical_note_id'];
        $k->manejar(self::peticion([], [], 'POST', "/api/v1/notas-soap/$id/firma"));

        return $id;
    }
}
