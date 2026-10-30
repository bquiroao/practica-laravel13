<?php

declare(strict_types=1);

namespace Qa\Persistencia\Pdo;

use Qa\Dominio\Excepciones\ReglaRechazada;
use Qa\Dominio\Modelo\CasoPrueba;
use Qa\Dominio\Puertos\RepositorioCasos;

final class PdoRepositorioCasos extends ConsultasPdo implements RepositorioCasos
{
    private const COLUMNAS = "c.tenant, c.caso_id, c.version, c.autor, c.titulo, c.precondiciones, c.pasos, c.prioridad, c.estado, c.aprobador,
        (SELECT coalesce(json_agg(r.requisito ORDER BY r.requisito), '[]')::text FROM caso_requisitos r
          WHERE r.tenant = c.tenant AND r.caso_id = c.caso_id AND r.version = c.version) AS requisitos";

    public function guardar(CasoPrueba $caso): void
    {
        if ($this->consulta('SELECT 1 FROM casos_prueba WHERE tenant = ? AND caso_id = ? AND version = ?', [$caso->tenant, $caso->id, $caso->version])->fetchColumn()) {
            throw new ReglaRechazada('CASO_EXISTENTE');   // mismo contrato que el adaptador en memoria (CON-02)
        }
        $this->consulta('INSERT INTO casos_prueba (tenant, caso_id, version, autor, titulo, precondiciones, pasos, prioridad, estado, aprobador)
            VALUES (?, ?, ?, ?, ?, ?, ?::jsonb, ?, ?, ?)', [$caso->tenant, $caso->id, $caso->version, $caso->autor, $caso->titulo,
            $caso->precondiciones, json_encode($caso->pasos, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), $caso->prioridad, $caso->estado, $caso->aprobador]);
        foreach ($caso->requisitos as $requisito) {
            $this->consulta('INSERT INTO caso_requisitos (tenant, caso_id, version, requisito) VALUES (?, ?, ?, ?)',
                [$caso->tenant, $caso->id, $caso->version, $requisito]);
        }
    }

    public function aprobar(CasoPrueba $caso): void
    {
        $filas = $this->consulta("UPDATE casos_prueba SET estado = 'APROBADO', aprobador = ?
            WHERE tenant = ? AND caso_id = ? AND version = ? AND estado = 'BORRADOR'", [$caso->aprobador, $caso->tenant, $caso->id, $caso->version])->rowCount();
        if ($filas !== 1) {
            throw new ReglaRechazada('ESTADO_NO_PERMITE_APROBAR');   // otra persona la aprobó entre la lectura y esta escritura
        }
    }

    public function porId(string $tenant, string $id): ?CasoPrueba
    {
        $fila = $this->consulta('SELECT ' . self::COLUMNAS . ' FROM casos_prueba c WHERE c.tenant = ? AND c.caso_id = ?
            ORDER BY c.version DESC LIMIT 1', [$tenant, $id])->fetch();

        return $fila === false ? null : self::caso($fila);
    }

    public function variosPorId(string $tenant, array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $casos = [];
        $filas = $this->consulta('SELECT DISTINCT ON (c.caso_id) ' . self::COLUMNAS . ' FROM casos_prueba c
            WHERE c.tenant = ? AND c.caso_id IN (' . self::enlaces($ids) . ') ORDER BY c.caso_id, c.version DESC', [$tenant, ...array_values($ids)]);
        foreach ($filas as $fila) {
            $casos[$fila['caso_id']] = self::caso($fila);
        }

        return $casos;
    }

    public function criticosDe(string $tenant, array $funcionalidades): array
    {
        if ($funcionalidades === []) {
            return [];
        }

        return array_map('strval', $this->consulta('SELECT DISTINCT caso_id FROM matriz_regresion
            WHERE tenant = ? AND critico AND funcionalidad IN (' . self::enlaces($funcionalidades) . ') ORDER BY caso_id',
            [$tenant, ...array_values($funcionalidades)])->fetchAll(\PDO::FETCH_COLUMN));
    }

    public function queCubren(string $tenant, string $requisito): array
    {
        $filas = $this->consulta('SELECT ' . self::COLUMNAS . ' FROM casos_prueba c JOIN caso_requisitos q
            ON q.tenant = c.tenant AND q.caso_id = c.caso_id AND q.version = c.version
            WHERE c.tenant = ? AND q.requisito = ? ORDER BY c.caso_id, c.version', [$tenant, $requisito]);

        return array_map(self::caso(...), $filas->fetchAll());
    }

    /** @param array<string, mixed> $f */
    private static function caso(array $f): CasoPrueba
    {
        return new CasoPrueba($f['tenant'], $f['caso_id'], (int) $f['version'], $f['autor'], $f['titulo'], $f['precondiciones'],
            self::json($f['pasos']), $f['prioridad'], self::json($f['requisitos']), $f['estado'], $f['aprobador']);
    }
}
