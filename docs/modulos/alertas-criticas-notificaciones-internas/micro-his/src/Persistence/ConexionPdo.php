<?php
declare(strict_types=1);

namespace AlertasCriticas\Persistence;

use AlertasCriticas\Domain\ErrorPersistenciaException;

final class ConexionPdo
{
    /** PDO pgsql con ERRMODE_EXCEPTION y sentencias preparadas reales (sin emulación). */
    public static function crear(array $config, ?string $base = null): \PDO
    {
        $dsn = sprintf('pgsql:host=%s;port=%d;dbname=%s', $config['host'], $config['puerto'], $base ?? $config['base']);
        try {
            $pdo = new \PDO($dsn, $config['usuario'], $config['clave'], [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            $pdo->prepare('SELECT set_config(\'TimeZone\', :zona, false)')->execute([':zona' => $config['zona']]);
            return $pdo;
        } catch (\PDOException $e) {
            throw new ErrorPersistenciaException('No se pudo conectar a PostgreSQL: ' . $e->getMessage(), 0, $e);
        }
    }
}
