<?php
declare(strict_types=1);

namespace Pruebas;

use Admision\Application\ContextoUsuario;
use Admision\Application\SolicitudAdmision;
use Admision\Domain\Elegibilidad\CriterioAislamiento;
use Admision\Domain\Elegibilidad\CriterioCamaOperativa;
use Admision\Domain\Elegibilidad\CriterioElegibilidadCama;
use Admision\Domain\Elegibilidad\CriterioServicio;
use Admision\Domain\Elegibilidad\CriterioTipoCama;
use Admision\Domain\Elegibilidad\EvaluadorElegibilidad;
use Admision\Domain\Modelo\CamaDisponible;
use Admision\Domain\Modelo\Rol;
use Admision\Domain\Modelo\TenantId;
use Admision\Domain\Modelo\TipoCama;

/** Acceso a los datos ficticios de datos-ficticios.php para las pruebas. */
final class Datos
{
    public const B201 = 'c0000000-0000-4000-8000-000000000201';
    public const B202 = 'c0000000-0000-4000-8000-000000000202';
    public const B203 = 'c0000000-0000-4000-8000-000000000203';
    public const B210 = 'c0000000-0000-4000-8000-000000000210';
    public const B201_HOSP02 = 'c0000000-0000-4000-8000-000000002201';

    public static function crudos(): array
    {
        return require __DIR__ . '/../../datos-ficticios.php';
    }

    /** @return list<CamaDisponible> */
    public static function camas(): array
    {
        return array_map(
            fn (array $c) => new CamaDisponible($c[0], TenantId::desde($c[1]), $c[2], $c[3], TipoCama::from($c[4]), $c[5], $c[6]),
            self::crudos()['camas'],
        );
    }

    public static function cama(string $codigo, string $hospital = 'HOSP-01'): CamaDisponible
    {
        foreach (self::camas() as $cama) {
            if ($cama->codigo === $codigo && $cama->tenantId->valor === $hospital) {
                return $cama;
            }
        }
        throw new \LogicException("No hay cama ficticia {$codigo} en {$hospital}");
    }

    /** @return list<CriterioElegibilidadCama> los mismos criterios que arma bootstrap.php */
    public static function criterios(): array
    {
        return [new CriterioCamaOperativa(), new CriterioServicio(), new CriterioTipoCama(), new CriterioAislamiento()];
    }

    public static function evaluador(): EvaluadorElegibilidad
    {
        return new EvaluadorElegibilidad(...self::criterios());
    }

    public static function contexto(string $usuario, string $solicitud = 'sol-prueba-0001'): ContextoUsuario
    {
        [$rol, $hospital] = self::crudos()['usuarios'][$usuario];
        return new ContextoUsuario($usuario, Rol::from($rol), TenantId::desde($hospital), $solicitud);
    }

    public static function solicitud(string $paciente = 'PAC-00451', string $camaId = self::B201, bool $aislamiento = false): SolicitudAdmision
    {
        return new SolicitudAdmision($paciente, 'MEDICINA_INTERNA', 'GENERAL', $aislamiento, 'EMERGENCIA', $camaId);
    }
}
