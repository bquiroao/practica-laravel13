<?php
declare(strict_types=1);

namespace Lab\Persistence;

use PDO;

/** Crea la conexión PDO a PostgreSQL con errores como excepciones. */
final class Conexion
{
    public static function crear(array $c): PDO
    {
        $dsn = sprintf('pgsql:host=%s;port=%d;dbname=%s', $c['host'], $c['puerto'], $c['base']);
        return new PDO($dsn, $c['usuario'], $c['clave'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
}
