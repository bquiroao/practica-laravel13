<?php
declare(strict_types=1);

use Catalogo\Persistence\ConexionPdo;
use Catalogo\Persistence\DatosEjemplo;
use Catalogo\Persistence\GeneradorUuidV4;
use Catalogo\Persistence\RepositorioMedicamentosPdo;

/** Base micro_medicamentos_test: se vacía y se recarga con los datos ficticios en cada prueba. */
final class BaseDePrueba
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $config = require __DIR__ . '/../../config.php';
            self::$pdo = ConexionPdo::crear($config['bd'], $config['bd']['base_prueba']);
        }
        return self::$pdo;
    }

    /** Deja la base con los datos de ejemplo y devuelve el repositorio PDO sobre ella. */
    public static function reiniciada(): RepositorioMedicamentosPdo
    {
        $pdo = self::pdo();
        $pdo->exec('TRUNCATE medicamentos, referencias_prescripcion');
        $repo = new RepositorioMedicamentosPdo($pdo);
        DatosEjemplo::cargar($repo, new GeneradorUuidV4());
        $st = $pdo->prepare('INSERT INTO referencias_prescripcion VALUES (:h, :c, :p)');
        foreach (DatosEjemplo::REFERENCIAS as [$h, $c, $p]) {
            $st->execute(['h' => $h, 'c' => $c, 'p' => $p]);
        }
        return $repo;
    }
}
