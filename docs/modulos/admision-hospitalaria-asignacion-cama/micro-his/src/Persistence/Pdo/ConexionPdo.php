<?php
declare(strict_types=1);

namespace Admision\Persistence\Pdo;

/**
 * Crea la conexión PDO a PostgreSQL. Valores por defecto = docker-compose.yml (uso local, datos ficticios).
 * Se pueden cambiar con ADMISION_DB_HOST, ADMISION_DB_PORT, ADMISION_DB_NAME, ADMISION_DB_USER y ADMISION_DB_PASS.
 */
final class ConexionPdo
{
    public static function desdeEntorno(?string $base = null): \PDO
    {
        $host = getenv('ADMISION_DB_HOST') ?: '127.0.0.1';
        $puerto = getenv('ADMISION_DB_PORT') ?: '55443';
        $base ??= getenv('ADMISION_DB_NAME') ?: 'micro_admision';
        $pdo = new \PDO(
            "pgsql:host={$host};port={$puerto};dbname={$base}",
            getenv('ADMISION_DB_USER') ?: 'admision',
            getenv('ADMISION_DB_PASS') ?: 'admision_local',
            [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES => false,
            ],
        );
        $pdo->prepare("SELECT set_config('TimeZone', :zona, false)")->execute([':zona' => 'UTC']);
        return $pdo;
    }
}
