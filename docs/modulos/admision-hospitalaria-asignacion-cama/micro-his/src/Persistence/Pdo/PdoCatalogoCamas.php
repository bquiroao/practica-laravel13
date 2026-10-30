<?php
declare(strict_types=1);

namespace Admision\Persistence\Pdo;

use Admision\Domain\Excepciones\ErrorPersistencia;
use Admision\Domain\Modelo\CamaDisponible;
use Admision\Domain\Modelo\TenantId;
use Admision\Domain\Modelo\TipoCama;
use Admision\Domain\Repositorio\CatalogoCamas;

/** Lectura del catálogo simulado (cama_catalogo). Solo SELECT: el catálogo es de otro módulo. */
final class PdoCatalogoCamas implements CatalogoCamas
{
    private const COLUMNAS = 'id, tenant_id, codigo, servicio, tipo, aislamiento, estado_operativo';

    public function __construct(private readonly \PDO $pdo)
    {
    }

    public function buscar(TenantId $tenantId, string $camaId): ?CamaDisponible
    {
        if (!Uuid::esValido($camaId)) {
            return null; // un id mal formado no existe; no se envía a PostgreSQL
        }
        $filas = $this->consultar('SELECT ' . self::COLUMNAS . ' FROM cama_catalogo WHERE tenant_id = :tenant AND id = :id', [
            ':tenant' => $tenantId->valor, ':id' => $camaId,
        ]);
        return $filas === [] ? null : $this->aCama($filas[0]);
    }

    public function listar(TenantId $tenantId): array
    {
        $filas = $this->consultar('SELECT ' . self::COLUMNAS . ' FROM cama_catalogo WHERE tenant_id = :tenant ORDER BY codigo', [
            ':tenant' => $tenantId->valor,
        ]);
        return array_map(fn (array $f) => $this->aCama($f), $filas);
    }

    private function consultar(string $sql, array $parametros): array
    {
        try {
            $sentencia = $this->pdo->prepare($sql);
            $sentencia->execute($parametros);
            return $sentencia->fetchAll();
        } catch (\PDOException $e) {
            throw new ErrorPersistencia('No se pudo consultar el catálogo de camas.', 0, $e);
        }
    }

    private function aCama(array $f): CamaDisponible
    {
        return new CamaDisponible(
            $f['id'], TenantId::desde($f['tenant_id']), $f['codigo'], $f['servicio'],
            TipoCama::from($f['tipo']), (bool) $f['aislamiento'], $f['estado_operativo'] === 'OPERATIVA',
        );
    }
}
