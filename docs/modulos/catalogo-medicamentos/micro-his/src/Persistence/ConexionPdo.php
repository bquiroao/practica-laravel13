<?php
declare(strict_types=1);

namespace Catalogo\Persistence;

use Catalogo\Domain\Errores\ErrorPersistencia;

/** Crea la conexión PDO pgsql con excepciones y sentencias preparadas reales. */
final class ConexionPdo
{
    /** @param array{host: string, puerto: int, usuario: string, clave: string} $bd */
    public static function crear(array $bd, string $base): \PDO
    {
        $dsn = sprintf('pgsql:host=%s;port=%d;dbname=%s;connect_timeout=3', $bd['host'], $bd['puerto'], $base);
        try {
            $pdo = new \PDO($dsn, $bd['usuario'], $bd['clave'], [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_EMULATE_PREPARES => false,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            ]);
            $pdo->exec("SET client_encoding TO 'UTF8'");
            return $pdo;
        } catch (\PDOException $e) {
            throw new ErrorPersistencia($e);
        }
    }
}
