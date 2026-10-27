<?php

declare(strict_types=1);

use Prescripciones\Aplicacion\Puertos\FuenteAlergias;
use Prescripciones\Dominio\Modelo\Alergia;
use Prescripciones\Dominio\Modelo\Medicamento;
use Prescripciones\Dominio\Modelo\SolicitudEmision;
use Prescripciones\Persistencia\Configuracion\PoliticaAccesoEstatica;
use Prescripciones\Presentacion\Http\Autenticacion\AutenticadorTokenDemo;
use Prescripciones\Presentacion\Http\Kernel;
use Prescripciones\Presentacion\Http\Solicitud;

/**
 * Arma el Micro-HIS completo con dobles en memoria y los mismos datos que database/datos-demo.sql.
 * Usa la MISMA raíz de composición que producción (construirCasosDeUso, construirKernel).
 */
final class Escenario
{
    public readonly RepositorioEnMemoria $repositorio;
    public readonly AuditoriaEnMemoria $auditoria;
    public readonly TransaccionEnMemoria $transaccion;
    public readonly PacientesEnMemoria $pacientes;
    public readonly FuenteAlergias $fuente;
    /** @var array{previa: \Prescripciones\Aplicacion\CasosDeUso\VerificarAlergiasPrevia, emitir: \Prescripciones\Aplicacion\CasosDeUso\EmitirPrescripcion, consultar: \Prescripciones\Aplicacion\CasosDeUso\ConsultarPrescripcion} */
    public readonly array $casos;
    private static int $claves = 0;

    public function __construct(?FuenteAlergias $fuente = null)
    {
        $config = require dirname(__DIR__, 2) . '/config/config.php';
        $this->repositorio = new RepositorioEnMemoria();
        $this->auditoria = new AuditoriaEnMemoria();
        $this->transaccion = new TransaccionEnMemoria([$this->repositorio, $this->auditoria]);
        $this->pacientes = new PacientesEnMemoria([
            'hosp-norte' => ['P-001', 'P-002', 'P-003', 'P-004', 'P-005', 'P-006', 'P-007'],
            'hosp-sur' => ['P-900'],
        ]);
        $this->fuente = $fuente ?? new FuenteAlergiasEnMemoria(self::alergias());
        $this->casos = construirCasosDeUso([
            'acceso' => new PoliticaAccesoEstatica($config['permisos']),
            'pacientes' => $this->pacientes,
            'catalogo' => self::catalogo(),
            'alergias' => $this->fuente,
            'repositorio' => $this->repositorio,
            'auditoria' => $this->auditoria,
            'transaccion' => $this->transaccion,
            'reloj' => new RelojFijo(),
        ]);
    }

    public function kernel(): Kernel
    {
        $config = require dirname(__DIR__, 2) . '/config/config.php';

        return construirKernel($this->casos, new AutenticadorTokenDemo($config['tokens_demo']));
    }

    public static function catalogo(): CatalogoEnMemoria
    {
        return new CatalogoEnMemoria([
            new Medicamento('AMOX500', 'Amoxicilina 500 mg', 'J01CA04', 'Amoxicilina', 'betalactamicos', 'mg', 3000, ['oral'], true),
            new Medicamento('CEFA500', 'Cefalexina 500 mg', 'J01DB01', 'Cefalexina', 'betalactamicos', 'mg', 4000, ['oral'], true),
            new Medicamento('PARA500', 'Paracetamol 500 mg', 'N02BE01', 'Paracetamol', 'anilidas', 'mg', 4000, ['oral', 'rectal'], true),
            new Medicamento('IBU400', 'Ibuprofeno 400 mg', 'M01AE01', 'Ibuprofeno', 'aines', 'mg', 2400, ['oral'], true),
            new Medicamento('RANI150', 'Ranitidina 150 mg', 'A02BA02', 'Ranitidina', 'antih2', 'mg', 300, ['oral'], false),
        ], ['j01ca04' => 'betalactamicos', 'j01ce01' => 'betalactamicos', 'penicilina' => 'betalactamicos', 'n02ba01' => 'aines', 'm01ae01' => 'aines']);
    }

    /** @return array<string, list<Alergia>> */
    public static function alergias(): array
    {
        return [
            'P-001' => [new Alergia('J01CA04', 'Amoxicilina', 'allergy', 'severe', 'active', 'urticaria generalizada')],
            'P-002' => [new Alergia(null, 'Penicilina', 'allergy', 'moderate', 'active', 'exantema')],
            'P-004' => [new Alergia('N02BA01', 'Ácido acetilsalicílico', 'allergy', 'life_threatening', 'active', 'anafilaxia')],
            'P-005' => [new Alergia('J01CA04', 'Amoxicilina', 'allergy', 'mild', 'resolved', null)],
            'P-006' => [new Alergia(null, 'penicilina', 'unknown', 'unknown', 'active', null)],
            'P-007' => [new Alergia(null, '  AMOXICILINA ', 'allergy', 'moderate', 'active', 'prurito')],
        ];
    }

    /** @return array<string, mixed> */
    public static function item(string $medicamento, float|int $dosis = 500, int $frecuencia = 8, string $via = 'oral', int $dias = 5, string $unidad = 'mg'): array
    {
        return ['medicamento' => $medicamento, 'dosis' => $dosis, 'unidad' => $unidad, 'frecuencia_horas' => $frecuencia, 'via' => $via, 'dias' => $dias];
    }

    /** @param list<array<string, mixed>> $items */
    public static function solicitud(string $paciente, array $items, string $usuario = 'dr-lopez', string $hospital = 'hosp-norte',
        ?string $justificacion = null, bool $confirma = false, ?string $clave = null): SolicitudEmision
    {
        return new SolicitudEmision($usuario, $hospital, $paciente, $clave ?? 'clave-' . ++self::$claves, $items, $justificacion, $confirma);
    }

    /** @param array<string, mixed>|null $cuerpo @param array<string, string> $cabeceras */
    public static function http(string $metodo, string $ruta, ?array $cuerpo = null, string $token = 'token-dr-lopez', ?string $tenant = 'hosp-norte',
        array $cabeceras = []): Solicitud
    {
        $base = ['authorization' => "Bearer $token"] + ($tenant !== null ? ['x-tenant-id' => $tenant] : []);

        return new Solicitud($metodo, $ruta, $cabeceras + $base, $cuerpo === null ? '' : (string) json_encode($cuerpo));
    }
}
