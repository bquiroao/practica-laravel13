<?php

declare(strict_types=1);

use Prescripciones\Aplicacion\Puertos\FuenteAlergias;
use Prescripciones\Dominio\Modelo\Alergia;
use Prescripciones\Dominio\Modelo\SolicitudEmision;
use Prescripciones\Persistencia\EnMemoria\CatalogoEnMemoria;
use Prescripciones\Persistencia\FabricaAdaptadores;
use Prescripciones\Presentacion\Http\Autenticacion\AutenticadorTokenDemo;
use Prescripciones\Presentacion\Http\Kernel;
use Prescripciones\Presentacion\Http\Solicitud;

/**
 * Arma el Micro-HIS completo con los adaptadores InMemory DE PRODUCCIÓN (FabricaAdaptadores::memoria y
 * database/datos-demo.php) más decoradores que inyectan fallas. Usa la MISMA raíz de composición que producción.
 */
final class Escenario
{
    public readonly RepositorioConFallas $repositorio;
    public readonly AuditoriaConFallas $auditoria;
    public readonly TransaccionConFallas $transaccion;
    public readonly PacientesConFallas $pacientes;
    public readonly FuenteAlergias $fuente;
    /** @var array{previa: \Prescripciones\Aplicacion\CasosDeUso\VerificarAlergiasPrevia, emitir: \Prescripciones\Aplicacion\CasosDeUso\EmitirPrescripcion, consultar: \Prescripciones\Aplicacion\CasosDeUso\ConsultarPrescripcion} */
    public readonly array $casos;
    private static int $claves = 0;

    public function __construct(?FuenteAlergias $fuente = null)
    {
        $p = FabricaAdaptadores::memoria(self::datos(), self::config());
        $this->repositorio = new RepositorioConFallas($p['repositorio']);
        $this->auditoria = new AuditoriaConFallas($p['auditoria']);
        $this->transaccion = new TransaccionConFallas($p['transaccion']);
        $this->pacientes = new PacientesConFallas($p['pacientes']);
        $this->fuente = $fuente ?? new FuenteAlergiasContada($p['alergias']);
        $this->casos = construirCasosDeUso([
            'acceso' => $p['acceso'],
            'pacientes' => $this->pacientes,
            'catalogo' => $p['catalogo'],
            'alergias' => $this->fuente,
            'repositorio' => $this->repositorio,
            'auditoria' => $this->auditoria,
            'transaccion' => $this->transaccion,
            'reloj' => new RelojFijo(),
        ]);
    }

    public function kernel(): Kernel
    {
        return construirKernel($this->casos, new AutenticadorTokenDemo(self::config()['tokens_demo']));
    }

    /** @return array<string, mixed> */
    public static function config(): array
    {
        return require dirname(__DIR__, 2) . '/config/config.php';
    }

    /** @return array<string, mixed> */
    public static function datos(): array
    {
        return require dirname(__DIR__, 2) . '/database/datos-demo.php';
    }

    public static function catalogo(): CatalogoEnMemoria
    {
        return FabricaAdaptadores::memoria(self::datos(), self::config())['catalogo'];
    }

    /** @return array<string, list<Alergia>> */
    public static function alergias(): array
    {
        return array_map(static fn (array $lista): array => array_map(static fn (array $a): Alergia => new Alergia(...$a), $lista), self::datos()['alergias']);
    }

    public static function fuenteContada(): FuenteAlergiasContada
    {
        return new FuenteAlergiasContada(FabricaAdaptadores::memoria(self::datos(), self::config())['alergias']);
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
