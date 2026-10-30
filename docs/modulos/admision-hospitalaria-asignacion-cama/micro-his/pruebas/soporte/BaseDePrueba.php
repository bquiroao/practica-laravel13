<?php
declare(strict_types=1);

namespace Pruebas;

use Admision\Persistence\Pdo\ConexionPdo;
use Admision\Persistence\Pdo\EsquemaMicroHis;

/** Base de prueba micro_admision_test: se recrea una vez por ejecución con los datos ficticios. */
final class BaseDePrueba
{
    private static bool $preparada = false;

    public static function conexion(): \PDO
    {
        try {
            $pdo = ConexionPdo::desdeEntorno('micro_admision_test');
        } catch (\PDOException $e) {
            throw new PruebaOmitida('sin base micro_admision_test en el puerto 55443 (docker compose up -d y scripts/crear-esquema.php --base=micro_admision_test)');
        }
        if (!self::$preparada) {
            EsquemaMicroHis::crear($pdo, true);
            EsquemaMicroHis::sembrar($pdo, Datos::crudos());
            self::$preparada = true;
        }
        return $pdo;
    }

    /** Borra solo los datos propios del módulo entre pruebas (TRUNCATE no dispara el trigger de RN-05). */
    public static function limpiar(\PDO $pdo): void
    {
        $pdo->prepare('TRUNCATE evento_admision, asignacion_cama, admision')->execute();
    }

    /** Conexión cuyo search_path no tiene tablas: provoca un error real de PostgreSQL. */
    public static function conexionRota(): \PDO
    {
        $pdo = self::conexion();
        $pdo->prepare("SELECT set_config('search_path', :ruta, false)")->execute([':ruta' => 'esquema_inexistente']);
        return $pdo;
    }
}
