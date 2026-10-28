<?php
declare(strict_types=1);

namespace Lab\Presentation\Api;

/** RN-01 en el prototipo: tokens ficticios en lugar del JWT y del módulo RBAC reales. */
final class Autorizacion
{
    public const GESTIONAR = 'lab.catalogo.gestionar';
    public const CONSULTAR = 'lab.catalogo.consultar';

    private const TOKENS = [
        'tok-jefe' => ['usuario' => 'jefe.lab', 'rol' => 'jefe_laboratorio', 'tenant' => 'HOSP-01'],
        'tok-laboratorista' => ['usuario' => 'lab.ana', 'rol' => 'laboratorista', 'tenant' => 'HOSP-01'],
        'tok-medico' => ['usuario' => 'dr.perez', 'rol' => 'medico', 'tenant' => 'HOSP-01'],
        'tok-ordenes' => ['usuario' => 'svc.ordenes', 'rol' => 'modulo_ordenes', 'tenant' => 'HOSP-01'],
    ];

    private const PERMISOS = [
        'jefe_laboratorio' => [self::GESTIONAR, self::CONSULTAR],
        'laboratorista' => [self::CONSULTAR],
        'medico' => [self::CONSULTAR],
        'modulo_ordenes' => [self::CONSULTAR],
    ];

    /** @return array{usuario:string, rol:string, tenant:string} */
    public static function exigir(?string $cabecera, string $permiso): array
    {
        $token = preg_match('/^Bearer\s+(\S+)$/', (string) $cabecera, $m) === 1 ? $m[1] : '';
        $identidad = self::TOKENS[$token] ?? null;
        if ($identidad === null) {
            throw new ErrorApi(401, 'NO_AUTENTICADO', 'Falta el token o no es válido.');
        }
        if (!in_array($permiso, self::PERMISOS[$identidad['rol']], true)) {
            throw new ErrorApi(403, 'SIN_PERMISO', "El rol {$identidad['rol']} no tiene el permiso $permiso.");
        }
        return $identidad;
    }
}
