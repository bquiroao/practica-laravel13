<?php

declare(strict_types=1);

namespace Rbac\Pruebas\Dobles;

use Rbac\Aplicacion\CasosDeUso\AsignarRol;
use Rbac\Aplicacion\CasosDeUso\AutorizarOperacion;
use Rbac\Aplicacion\CasosDeUso\ConsultarEventoAuditoria;
use Rbac\Aplicacion\Comandos\ComandoAsignarRol;
use Rbac\Aplicacion\Comandos\ComandoAutorizar;
use Rbac\Dominio\Modelo\Membresia;
use Rbac\Presentacion\Http\Autenticacion\AutenticadorTokenDemo;
use Rbac\Presentacion\Http\Kernel;
use Rbac\Presentacion\Http\Solicitud;

/**
 * Datos ficticios + el sistema completo armado con dobles en memoria
 * a través de la misma raíz de composición que usa la aplicación real.
 */
final class Escenario
{
    public const H_ORIENTE = 'hosp-oriente';
    public const H_NORTE = 'hosp-norte';
    public const AHORA = '2026-11-15T10:00:00-06:00';

    public RepositorioMembresiasEnMemoria $membresias;
    public RegistroAuditoriaEnMemoria $auditoria;
    public RegistroAuditoriaEnMemoria $respaldo;
    public AutorizarOperacion $autorizar;
    public AsignarRol $asignarRol;
    public ConsultarEventoAuditoria $consultar;
    public Kernel $kernel;
    private int $contador = 0;

    public function __construct()
    {
        $f = static fn (string $fecha): \DateTimeImmutable => new \DateTimeImmutable($fecha);

        $this->membresias = new RepositorioMembresiasEnMemoria([
            new Membresia('admin-01', self::H_ORIENTE, 'admin_hospital', 'active', $f('2026-01-01T00:00:00-06:00')),
            new Membresia('admin-02', self::H_NORTE, 'admin_hospital', 'active', $f('2026-01-01T00:00:00-06:00')),
            new Membresia('ana', self::H_ORIENTE, 'enfermeria', 'active', $f('2026-01-01T00:00:00-06:00')),
            new Membresia('marta', self::H_ORIENTE, 'medico', 'active', $f('2026-02-01T00:00:00-06:00')),
            new Membresia('pedro', self::H_ORIENTE, 'medico', 'active', $f('2026-01-01T00:00:00-06:00'), $f('2026-06-30T00:00:00-06:00')),
            new Membresia('lucia', self::H_ORIENTE, 'enfermeria', 'revoked', $f('2026-01-01T00:00:00-06:00')),
        ]);
        $catalogo = new CatalogoPermisosEnMemoria([
            'admin_hospital' => ['roles.asignar', 'auditoria.consultar'],
            'medico' => ['prescripciones.crear', 'signos_vitales.registrar', 'expediente.leer'],
            'enfermeria' => ['signos_vitales.registrar', 'expediente.leer'],
            'laboratorio' => ['resultados.registrar'],
        ]);
        $usuarios = new DirectorioUsuariosEnMemoria([
            'admin-01' => 'active', 'admin-02' => 'active', 'ana' => 'active', 'marta' => 'active',
            'pedro' => 'active', 'lucia' => 'active', 'nuevo-01' => 'active', 'jorge' => 'inactive',
        ]);
        $this->auditoria = new RegistroAuditoriaEnMemoria();
        $this->respaldo = new RegistroAuditoriaEnMemoria();

        $casos = construirCasosDeUso(
            $this->membresias,
            $catalogo,
            $usuarios,
            $this->auditoria,
            $this->respaldo,
            new TransaccionEnMemoria($this->membresias),
            fn (): string => sprintf('corr-%04d', ++$this->contador),
            static fn (): \DateTimeImmutable => new \DateTimeImmutable(self::AHORA),
        );
        $this->autorizar = $casos['autorizar'];
        $this->asignarRol = $casos['asignarRol'];
        $this->consultar = $casos['consultar'];

        $this->kernel = construirKernel($casos, new AutenticadorTokenDemo([
            'token-admin-oriente' => ['admin-01', self::H_ORIENTE],
            'token-admin-norte' => ['admin-02', self::H_NORTE],
            'token-ana' => ['ana', self::H_ORIENTE],
            'token-marta' => ['marta', self::H_ORIENTE],
        ]));
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
