<?php

declare(strict_types=1);

namespace Auditoria\Pruebas\Dobles;

use Auditoria\Aplicacion\Comandos\ComandoConsultar;
use Auditoria\Aplicacion\Comandos\ComandoRegistrar;
use Auditoria\Aplicacion\Resultados\Resultado;
use Auditoria\Persistencia\Configuracion\PoliticaAccesoEstatica;
use Auditoria\Persistencia\EnMemoria\BitacoraEnMemoria;
use Auditoria\Persistencia\EnMemoria\TransaccionEnMemoria;
use Auditoria\Presentacion\Http\Autenticacion\AutenticadorTokenDemo;
use Auditoria\Presentacion\Http\Solicitud;

/**
 * Arma el módulo completo (casos de uso + kernel) con la configuración real y dobles de
 * persistencia. Datos ficticios. Los event_id del sistema son deterministas: sis-1, sis-2…
 */
final class Escenario
{
    public const H_ORIENTE = 'hosp-oriente';
    public const H_NORTE = 'hosp-norte';

    public static function crear(): object
    {
        $config = self::config();
        // Semana 4: los adaptadores InMemory DE PRODUCCIÓN, envueltos en dobles que simulan fallas y espían.
        $real = new BitacoraEnMemoria();
        $bitacora = new BitacoraConFallas($real);
        $transaccion = new TransaccionEspia(new TransaccionEnMemoria([$real]));
        $contador = 0;
        $casos = \construirCasosDeUso($config, $bitacora, $transaccion, new PoliticaAccesoEstatica($config['permisos']), new RelojFijo(),
            static function () use (&$contador): string {
                return 'sis-' . ++$contador;
            });

        return (object) [
            'bitacora' => $bitacora,
            'transaccion' => $transaccion,
            'casos' => $casos,
            'kernel' => \construirKernel($casos, new AutenticadorTokenDemo($config['tokens_demo'])),
        ];
    }

    /** @return array<string, mixed> */
    public static function config(): array
    {
        return require dirname(__DIR__, 2) . '/config/config.php';
    }

    /** Movimiento de Pacientes en hosp-oriente, con cambios. */
    public static function mov(array $cambios = []): array
    {
        return array_merge([
            'event_id' => 'ev-1', 'hospital' => self::H_ORIENTE, 'usuario' => 'u-ana', 'modulo' => 'pacientes',
            'accion' => 'PACIENTE_REGISTRADO', 'ocurrido_en' => '2026-10-21T09:15:00-06:00', 'resultado' => 'exito',
            'recurso' => 'paciente:P-000001', 'correlation_id' => 'corr-0001', 'detalle' => ['codigo' => 'P-000001'],
        ], $cambios);
    }

    public static function registrar(object $e, array $datos, string $emisor = 'svc-pacientes', ?string $hospital = self::H_ORIENTE): Resultado
    {
        return $e->casos['registrar']->ejecutar(new ComandoRegistrar($emisor, $hospital, $datos));
    }

    public static function consultar(object $e, array $parametros, string $actor = 'auditor-oriente', string $hospital = self::H_ORIENTE): Resultado
    {
        return $e->casos['consultar']->ejecutar(new ComandoConsultar($actor, $hospital, $parametros));
    }

    /** @return list<int> ids de una consulta exitosa */
    public static function ids(Resultado $r): array
    {
        return array_map(static fn (array $m): int => $m['id'], $r->datos['movimientos'] ?? []);
    }

    /**
     * @param array<string, mixed>|null $cuerpo
     * @param array<string, string> $consulta
     */
    public static function http(string $metodo, string $ruta, ?string $token, string $hospital = self::H_ORIENTE, ?array $cuerpo = null,
        array $consulta = []): Solicitud
    {
        $cabeceras = $hospital === '' ? [] : ['X-Tenant-ID' => $hospital];
        if ($token !== null) {
            $cabeceras['Authorization'] = 'Bearer ' . $token;
        }

        return new Solicitud($metodo, $ruta, $cabeceras, $consulta, $cuerpo);
    }
}
