<?php

declare(strict_types=1);

namespace NotasSoap\Persistencia\Pdo;

use NotasSoap\Dominio\Excepciones\ErrorPersistencia;
use PDO;

/** Crea la conexión PDO con errores por excepción y sentencias preparadas reales (sin emulación). */
final class FabricaConexion
{
    public static function crear(string $dsn, string $usuario, string $clave): PDO
    {
        try {
            return new PDO($dsn, $usuario, $clave, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (\PDOException $e) {
            throw new ErrorPersistencia('No hay conexión con la base de datos.', RepositorioPdo::sqlState($e), $e);
        }
    }
}
