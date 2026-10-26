<?php

declare(strict_types=1);

namespace Rbac\Pruebas\Dobles;

use Rbac\Aplicacion\CasosDeUso\AsignarRol;
use Rbac\Aplicacion\CasosDeUso\AutorizarOperacion;
use Rbac\Aplicacion\CasosDeUso\ConsultarEventoAuditoria;
use Rbac\Aplicacion\Comandos\ComandoAsignarRol;
use Rbac\Aplicacion\Comandos\ComandoAutorizar;
use Rbac\Persistencia\ConjuntoAdaptadores;
use Rbac\Persistencia\EnMemoria\FabricaEnMemoria;
use Rbac\Persistencia\EnMemoria\RegistroAuditoriaEnMemoria;
use Rbac\Persistencia\EnMemoria\RepositorioMembresiasEnMemoria;
use Rbac\Presentacion\Http\Autenticacion\AutenticadorTokenDemo;
use Rbac\Presentacion\Http\Kernel;
use Rbac\Presentacion\Http\Solicitud;

/**
 * El sistema completo armado con los adaptadores InMemory de producción (datos de
 * database/datos-demo.php) más decoradores que permiten simular fallas, a través
 * de la misma raíz de composición que usa la aplicación real.
 */
final class Escenario
{
    public const H_ORIENTE = 'hosp-oriente';
    public const H_NORTE = 'hosp-norte';
    public const AHORA = '2026-11-15T10:00:00-06:00';

    public RepositorioMembresiasConFallas $membresias;
    public RegistroAuditoriaConFallas $auditoria;
    public RegistroAuditoriaEnMemoria $respaldo;
    public AutorizarOperacion $autorizar;
    public AsignarRol $asignarRol;
    public ConsultarEventoAuditoria $consultar;
    public Kernel $kernel;
    /** @var list<array<string, mixed>> registros de acceso que emite el Kernel (observabilidad) */
    public array $accesos = [];
    /** @var array<string, object> */
    private array $casosDeUso = [];
    private int $contador = 0;

    public function __construct()
    {
        $base = FabricaEnMemoria::crear(self::datosDemo());
        $membresiasReales = $base->membresias;
        $auditoriaReal = $base->auditoria;
        assert($membresiasReales instanceof RepositorioMembresiasEnMemoria && $auditoriaReal instanceof RegistroAuditoriaEnMemoria);

        $this->membresias = new RepositorioMembresiasConFallas($membresiasReales);
        $this->auditoria = new RegistroAuditoriaConFallas($auditoriaReal);
        $this->respaldo = new RegistroAuditoriaEnMemoria();

        $casos = construirCasosDeUso(
            // La transacción sigue siendo la de la fábrica: hace rollback sobre los almacenes reales.
            new ConjuntoAdaptadores($this->membresias, $base->catalogo, $base->usuarios, $this->auditoria, $base->transaccion),
            $this->respaldo,
            fn (): string => sprintf('corr-%04d', ++$this->contador),
            static fn (): \DateTimeImmutable => new \DateTimeImmutable(self::AHORA),
        );
        $this->autorizar = $casos['autorizar'];
        $this->asignarRol = $casos['asignarRol'];
        $this->consultar = $casos['consultar'];
        $this->casosDeUso = $casos;

        $this->kernel = construirKernel($casos, new AutenticadorTokenDemo([
            'token-admin-oriente' => ['admin-01', self::H_ORIENTE],
            'token-admin-norte' => ['admin-02', self::H_NORTE],
            'token-ana' => ['ana', self::H_ORIENTE],
            'token-marta' => ['marta', self::H_ORIENTE],
        ]), function (array $registro): void {
            $this->accesos[] = $registro;
        });
    }

    /** @return array<string, mixed> casos de uso para armar otros kernels en pruebas */
    public function casos(): array
    {
        return $this->casosDeUso;
    }

    /** Caso de uso por nombre (p. ej. 'revocarRol'); falla con un mensaje claro si no existe. */
    public function caso(string $nombre): object
    {
        return $this->casosDeUso[$nombre] ?? throw new \LogicException("El caso de uso «{$nombre}» no existe en la composición");
    }

    /** La tabla de rutas real, construida con los mismos controladores que usa construirKernel(). */
    public function rutas(): array
    {
        return construirRutas($this->casosDeUso);
    }

    public static function comandoRevocar(string $admin, string $destino, string $rol, string $motivo = 'Cambio de servicio: ya no atiende en esta unidad'): object
    {
        return new \Rbac\Aplicacion\Comandos\ComandoRevocarRol($admin, self::H_ORIENTE, self::H_ORIENTE, $destino, $rol, $motivo, '10.20.5.14');
    }

    /** @return array<string, mixed> */
    public static function datosDemo(): array
    {
        return require dirname(__DIR__, 2) . '/database/datos-demo.php';
    }

    public static function comandoAutorizar(string $usuario, string $permiso, string $tenantSolicitud = self::H_ORIENTE): ComandoAutorizar
    {
        return new ComandoAutorizar($usuario, self::H_ORIENTE, $tenantSolicitud, $permiso, 'recurso-demo', '10.20.5.14');
    }

    public static function comandoAsignar(string $admin, string $destino, string $rol, ?string $hasta = null): ComandoAsignarRol
    {
        return new ComandoAsignarRol(
            $admin,
            self::H_ORIENTE,
            self::H_ORIENTE,
            $destino,
            $rol,
            new \DateTimeImmutable('2026-11-15T00:00:00-06:00'),
            $hasta === null ? null : new \DateTimeImmutable($hasta),
            '10.20.5.14',
        );
    }

    /** @param array<string, mixed>|null $cuerpo */
    public static function http(string $metodo, string $ruta, ?string $token, ?string $tenant = self::H_ORIENTE, ?array $cuerpo = null): Solicitud
    {
        $cabeceras = [];
        if ($token !== null) {
            $cabeceras['Authorization'] = 'Bearer ' . $token;
        }
        if ($tenant !== null) {
            $cabeceras['X-Tenant-ID'] = $tenant;
        }

        return new Solicitud($metodo, $ruta, $cabeceras, $cuerpo, '10.20.5.14');
    }
}
