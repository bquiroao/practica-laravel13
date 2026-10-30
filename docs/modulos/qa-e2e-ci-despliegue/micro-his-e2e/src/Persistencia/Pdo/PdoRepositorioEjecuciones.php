<?php

declare(strict_types=1);

namespace E2e\Persistencia\Pdo;

use E2e\Dominio\Excepciones\ReglaRechazada;
use E2e\Dominio\Modelo\Ejecucion;
use E2e\Dominio\Modelo\Evidencia;
use E2e\Dominio\Modelo\Resultado;
use E2e\Dominio\Modelo\ResultadoEscenario;
use E2e\Dominio\Puertos\RepositorioEjecuciones;

final class PdoRepositorioEjecuciones extends ConsultasPdo implements RepositorioEjecuciones
{
    public function crear(Ejecucion $e): Ejecucion
    {
        $id = (int) $this->consulta('INSERT INTO ejecuciones (tenant, version, digest, entorno, plan, iniciada_en) VALUES (?, ?, ?, ?, ?::jsonb, ?) RETURNING id',
            [$e->tenant, $e->version, $e->digest, $e->entorno, self::aJson($e->plan), $e->iniciadaEn->format(DATE_ATOM)])->fetchColumn();

        return $e->conId($id);
    }

    public function actualizarEstado(Ejecucion $e): void
    {
        // Condicionado al estado anterior: dos cierres simultáneos no dejan dos estados.
        $filas = $this->consulta("UPDATE ejecuciones SET estado = ?, terminada_en = ?, motivo = ? WHERE tenant = ? AND id = ? AND estado = 'EN_CURSO'",
            [$e->estado(), $e->terminadaEn()?->format(DATE_ATOM), $e->motivo(), $e->tenant, $e->id])->rowCount();
        if ($filas !== 1) {
            throw new ReglaRechazada('ESTADO_NO_PERMITE_CERRAR');
        }
    }

    public function agregarResultado(string $tenant, int $ejecucion, ResultadoEscenario $r): ResultadoEscenario
    {
        $id = (int) $this->consulta('INSERT INTO resultados (tenant, ejecucion_id, escenario_id, critico, resultado, intentos, clave, huella, reportado_en)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?) RETURNING id', [$tenant, $ejecucion, $r->escenario, $r->critico ? 'true' : 'false', $r->resultado->value,
            $r->intentos, $r->clave, $r->huella, $r->reportadoEn->format(DATE_ATOM)])->fetchColumn();
        foreach ($r->evidencias as $ev) {
            $this->consulta('INSERT INTO evidencias (resultado_id, tipo, sha256, tamano, ubicacion) VALUES (?, ?, ?, ?, ?)',
                [$id, $ev->tipo, $ev->sha256, $ev->tamano, $ev->ubicacion]);
        }

        return $r->conId($id);
    }

    public function porId(string $tenant, int $id): ?Ejecucion
    {
        $f = $this->consulta('SELECT * FROM ejecuciones WHERE tenant = ? AND id = ?', [$tenant, $id])->fetch();

        return $f === false ? null : $this->ejecucion($f);
    }

    public function resultadoPorClave(string $tenant, string $clave): ?array
    {
        $f = $this->consulta('SELECT * FROM resultados WHERE tenant = ? AND clave = ?', [$tenant, $clave])->fetch();

        return $f === false ? null : [(int) $f['ejecucion_id'], $this->resultados([$f])[0]];
    }

    public function deVersion(string $tenant, string $version): array
    {
        return array_map($this->ejecucion(...), $this->consulta('SELECT * FROM ejecuciones WHERE tenant = ? AND version = ? ORDER BY id', [$tenant, $version])->fetchAll());
    }

    /** R-12: la clave repetida que llega por una carrera (dos reintentos simultáneos) choca con el UNIQUE. */
    protected function traducir(\PDOException $e): \Exception
    {
        return $e->getCode() === '23505' && str_contains($e->getMessage(), 'clave') ? new ReglaRechazada('CLAVE_REUTILIZADA') : parent::traducir($e);
    }

    /** @param array<string, mixed> $f */
    private function ejecucion(array $f): Ejecucion
    {
        $filas = $this->consulta('SELECT * FROM resultados WHERE ejecucion_id = ? ORDER BY id', [(int) $f['id']])->fetchAll();

        return new Ejecucion((int) $f['id'], $f['tenant'], $f['version'], $f['digest'], $f['entorno'], array_map('boolval', self::json($f['plan'])),
            self::hora($f['iniciada_en']), $f['estado'], $f['terminada_en'] === null ? null : self::hora($f['terminada_en']), $f['motivo'], $this->resultados($filas));
    }

    /**
     * @param list<array<string, mixed>> $filas
     * @return list<ResultadoEscenario>
     */
    private function resultados(array $filas): array
    {
        if ($filas === []) {
            return [];
        }
        $ids = array_map(static fn (array $f): int => (int) $f['id'], $filas);
        $evidencias = [];
        foreach ($this->consulta('SELECT * FROM evidencias WHERE resultado_id IN (' . implode(', ', array_fill(0, count($ids), '?')) . ') ORDER BY id', $ids) as $e) {
            $evidencias[(int) $e['resultado_id']][] = new Evidencia($e['tipo'], $e['sha256'], (int) $e['tamano'], $e['ubicacion']);
        }

        return array_map(static fn (array $f): ResultadoEscenario => new ResultadoEscenario((int) $f['id'], $f['escenario_id'], (bool) $f['critico'],
            Resultado::from($f['resultado']), (int) $f['intentos'], $evidencias[(int) $f['id']] ?? [], $f['clave'], $f['huella'], self::hora($f['reportado_en'])), $filas);
    }
}
