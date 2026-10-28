<?php

declare(strict_types=1);

namespace Auditoria\Persistencia;

use Auditoria\Persistencia\EnMemoria\BitacoraEnMemoria;
use Auditoria\Persistencia\EnMemoria\TransaccionEnMemoria;
use Auditoria\Persistencia\Pdo\FabricaConexion;
use Auditoria\Persistencia\Pdo\PdoBitacora;
use Auditoria\Persistencia\Pdo\PdoTransaccion;

/** Elige el par de adaptadores según AUDITORIA_PERSISTENCIA: «pdo» (PostgreSQL) o «memoria». */
final class FabricaAdaptadores
{
    /** @param array<string, mixed> $config */
    public static function crear(array $config): ConjuntoAdaptadores
    {
        return match ($config['persistencia']) {
            'memoria' => self::enMemoria(),
            'pdo' => self::pdo(FabricaConexion::crear($config['db']['dsn'], $config['db']['usuario'], $config['db']['clave'])),
            default => throw new \InvalidArgumentException('AUDITORIA_PERSISTENCIA debe ser pdo o memoria.'),
        };
    }

    public static function enMemoria(): ConjuntoAdaptadores
    {
        $bitacora = new BitacoraEnMemoria();

        return new ConjuntoAdaptadores($bitacora, new TransaccionEnMemoria([$bitacora]));
    }

    public static function pdo(\PDO $pdo): ConjuntoAdaptadores
    {
        return new ConjuntoAdaptadores(new PdoBitacora($pdo), new PdoTransaccion($pdo));
    }
}
