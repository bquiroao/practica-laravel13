<?php

declare(strict_types=1);

namespace Pacientes\Persistencia\Pdo;

use Pacientes\Dominio\Modelo\Identificador;
use Pacientes\Dominio\Puertos\IndiceDuplicados;
use Pacientes\Persistencia\Seguridad\ProteccionDocumentos;

/**
 * Índice de duplicados sobre la tabla de pacientes de TODOS los hospitales (en el HIS:
 * mpi_identifiers.search_hash e identity_match_candidates de la base central).
 */
final class PdoIndiceDuplicados extends RepositorioPdo implements IndiceDuplicados
{
    private const SQL_TITULAR = 'SELECT code FROM pacientes WHERE documento_hash = :hash LIMIT 1';

    private const SQL_PROTEGIDOS = "SELECT code FROM pacientes
        WHERE identidad_protegida AND code = ANY(string_to_array(:codigos, ',')) ORDER BY code";

    private const SQL_COINCIDENCIAS = 'SELECT code FROM pacientes
        WHERE nombre_normalizado = :nombre AND birth_date = :nacimiento ORDER BY code';

    public function __construct(\PDO $pdo, private readonly ProteccionDocumentos $proteccion)
    {
        parent::__construct($pdo);
    }

    public function titularDe(Identificador $documento): ?string
    {
        $codigo = $this->ejecutar(self::SQL_TITULAR, ['hash' => $this->proteccion->hash($documento)])->fetchColumn();

        return $codigo === false ? null : (string) $codigo;
    }

    public function coincidenciasProbables(string $nombreNormalizado, \DateTimeImmutable $nacimiento): array
    {
        $filas = $this->ejecutar(self::SQL_COINCIDENCIAS, ['nombre' => $nombreNormalizado, 'nacimiento' => $nacimiento->format('Y-m-d')])
            ->fetchAll(\PDO::FETCH_COLUMN);

        return array_map('strval', $filas);
    }

    public function protegidos(array $codigos): array
    {
        if ($codigos === []) {
            return [];
        }
        $filas = $this->ejecutar(self::SQL_PROTEGIDOS, ['codigos' => implode(',', $codigos)])->fetchAll(\PDO::FETCH_COLUMN);

        return array_map('strval', $filas);
    }
}
