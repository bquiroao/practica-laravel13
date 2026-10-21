<?php

declare(strict_types=1);

namespace Pacientes\Pruebas\Dobles;

use Pacientes\Aplicacion\Comandos\ComandoActualizar;
use Pacientes\Aplicacion\Comandos\ComandoRegistrar;
use Pacientes\Aplicacion\Resultados\Resultado;
use Pacientes\Persistencia\ConjuntoAdaptadores;
use Pacientes\Persistencia\Configuracion\PoliticaAccesoEstatica;
use Pacientes\Persistencia\EnMemoria\IndiceDuplicadosEnMemoria;
use Pacientes\Persistencia\EnMemoria\RegistroAuditoriaEnMemoria;
use Pacientes\Persistencia\EnMemoria\RepositorioPacientesEnMemoria;
use Pacientes\Persistencia\EnMemoria\TransaccionEnMemoria;
use Pacientes\Presentacion\Http\Autenticacion\AutenticadorTokenDemo;
use Pacientes\Presentacion\Http\Solicitud;

/**
 * Arma el módulo completo con los adaptadores InMemory de producción, envueltos en
 * decoradores que permiten simular fallas. Datos de prueba ficticios.
 */
final class Escenario
{
    public const H_ORIENTE = 'hosp-oriente';
    public const H_NORTE = 'hosp-norte';
    public const ADMISION = 'admision-01';
    public const CLINICO = 'clinico-01';
    public const SUPERVISOR = 'supervisor-01';
    public const ADMISION_NORTE = 'admision-02';
    public const DPI_ANA = '2547896320101';
    public const DPI_LUIS = '1876543210901';
    public const DPI_NUEVO = '4455667780105';

    public static function crear(): object
    {
        $almacen = new RepositorioPacientesEnMemoria();
        $eventos = new RegistroAuditoriaEnMemoria();
        $repositorio = new RepositorioPacientesConFallas($almacen);
        $bitacora = new RegistroAuditoriaConFallas($eventos);
        $adaptadores = new ConjuntoAdaptadores($repositorio, $repositorio, new IndiceDuplicadosEnMemoria($almacen), $bitacora,
            new TransaccionEnMemoria([$almacen, $eventos]));
        $config = self::config();
        $casos = \construirCasosDeUso($adaptadores, new PoliticaAccesoEstatica($config['permisos']), new RelojFijo());

        return (object) [
            'casos' => $casos,
            'repositorio' => $repositorio,
            'bitacora' => $bitacora,
            'kernel' => \construirKernel($casos, new AutenticadorTokenDemo($config['tokens_demo'])),
        ];
    }

    /** @return array<string, mixed> */
    public static function config(): array
    {
        return require dirname(__DIR__, 2) . '/config/config.php';
    }

    /** @return array<string, mixed> */
    public static function datosAna(array $cambios = []): array
    {
        return array_merge([
            'nombres' => 'Ana Lucía', 'apellidos' => 'López Pérez', 'fecha_nacimiento' => '1985-03-14', 'sexo' => 'female',
            'telefono' => '+502 5555 1234', 'tipo_documento' => 'DPI', 'numero_documento' => self::DPI_ANA,
        ], $cambios);
    }

    /** @return array<string, mixed> */
    public static function datosLuis(): array
    {
        return ['nombres' => 'Luis Fernando', 'apellidos' => 'García Ruiz', 'fecha_nacimiento' => '1978-11-02', 'sexo' => 'male',
            'tipo_documento' => 'DPI', 'numero_documento' => self::DPI_LUIS];
    }

    public static function registrar(object $e, array $datos, string $actor = self::ADMISION, string $hospital = self::H_ORIENTE,
        bool $confirma = false, ?string $motivo = null): Resultado
    {
        return $e->casos['registrar']->ejecutar(new ComandoRegistrar($actor, $hospital, $datos, $confirma, $motivo));
    }

    public static function actualizar(object $e, string $codigo, int $version, array $datos, ?string $motivo = null,
        string $actor = self::ADMISION): Resultado
    {
        return $e->casos['actualizar']->ejecutar(new ComandoActualizar($actor, self::H_ORIENTE, $codigo, $version, $datos, $motivo));
    }

    /** JSON de la API a partir del formulario plano. */
    public static function json(array $datos, array $extra = []): array
    {
        return [
            'nombres' => $datos['nombres'] ?? null,
            'apellidos' => $datos['apellidos'] ?? null,
            'fecha_nacimiento' => $datos['fecha_nacimiento'] ?? null,
            'sexo' => $datos['sexo'] ?? null,
            'telefono' => $datos['telefono'] ?? null,
            'documento' => isset($datos['tipo_documento'])
                ? ['tipo' => $datos['tipo_documento'], 'numero' => $datos['numero_documento'], 'pais' => $datos['pais_documento'] ?? null]
                : null,
        ] + $extra;
    }

    /**
     * @param array<string, mixed>|null $cuerpo
     * @param array<string, string> $consulta
     * @param array<string, string> $cabeceras
     */
    public static function http(string $metodo, string $ruta, ?string $token = 'token-admision-oriente', ?array $cuerpo = null,
        array $consulta = [], array $cabeceras = [], string $hospital = self::H_ORIENTE): Solicitud
    {
        $base = ['X-Tenant-ID' => $hospital];
        if ($token !== null) {
            $base['Authorization'] = 'Bearer ' . $token;
        }

        return new Solicitud($metodo, $ruta, $cabeceras + $base, $consulta, $cuerpo);
    }
}
