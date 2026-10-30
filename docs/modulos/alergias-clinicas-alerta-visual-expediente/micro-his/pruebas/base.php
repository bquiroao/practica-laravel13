<?php
declare(strict_types=1);

use Alergias\Dominio\ErrorPersistencia;
use Alergias\Persistencia\FabricaConexion;

/** Base de prueba limpia (micro_alergias_test) con los datos ficticios. Sin base, la prueba se omite. */
function baseDePrueba(): PDO
{
    try {
        $pdo = FabricaConexion::desdeEntorno('micro_alergias_test');
    } catch (ErrorPersistencia $e) {
        throw new Omitida('sin PostgreSQL en el puerto 55442 (levante Docker y corra scripts/crear-esquema.php)');
    }
    $sentencias = array_merge(require __DIR__ . '/../scripts/esquema.php', require __DIR__ . '/../scripts/datos-demo.php');
    foreach ($sentencias as $sentencia) {
        $pdo->prepare($sentencia)->execute();
    }
    return $pdo;
}

function contarAlergias(PDO $pdo): int
{
    $sql = $pdo->prepare('SELECT count(*) FROM allergies');
    $sql->execute();
    return (int) $sql->fetchColumn();
}
