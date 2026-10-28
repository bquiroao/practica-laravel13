<?php
declare(strict_types=1);

namespace MicroHis\Persistence;

use MicroHis\Domain\DatosNoDisponibles;

/** Abre PDO con la configuración de config/app.ini, solo cuando se necesita (conexión perezosa). */
final class ConexionPdo
{
    private ?\PDO $pdo = null;

    public function __construct(private readonly string $archivoIni, private readonly string $base = 'principal')
    {
    }

    public function pdo(): \PDO
    {
        if ($this->pdo !== null) {
            return $this->pdo;
        }
        $config = parse_ini_file($this->archivoIni, true);
        $datos = $config[$this->base] ?? throw new DatosNoDisponibles("No existe la sección [{$this->base}] en la configuración");
        try {
            return $this->pdo = new \PDO($datos['dsn'], $datos['usuario'], $datos['clave'], [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (\PDOException $e) {
            throw new DatosNoDisponibles('No se pudo conectar a la base de datos', 0, $e);
        }
    }
}
