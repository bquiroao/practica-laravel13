<?php

declare(strict_types=1);

namespace E2e\Persistencia\Pdo;

use E2e\Dominio\Modelo\Decision;
use E2e\Dominio\Puertos\RepositorioEvaluaciones;

final class PdoRepositorioEvaluaciones extends ConsultasPdo implements RepositorioEvaluaciones
{
    public function guardar(Decision $d): void
    {
        $this->consulta('INSERT INTO evaluaciones (tenant, version, ejecucion_id, decision, reglas, causas, digest, politica, evaluada_en, evaluada_por)
            VALUES (?, ?, ?, ?, ?::jsonb, ?::jsonb, ?, ?, ?, ?)', [$d->tenant, $d->version, $d->ejecucion, $d->valor, self::aJson($d->reglas),
            self::aJson($d->causas), $d->digest, $d->politica, $d->evaluadaEn->format(DATE_ATOM), $d->evaluadaPor]);
    }

    public function deVersion(string $tenant, string $version): array
    {
        return array_map(static fn (array $f): Decision => new Decision($f['decision'], array_map('boolval', self::json($f['reglas'])), self::json($f['causas']),
            $f['tenant'], $f['version'], (int) $f['ejecucion_id'], $f['digest'], $f['politica'], self::hora($f['evaluada_en']), $f['evaluada_por']),
            $this->consulta('SELECT * FROM evaluaciones WHERE tenant = ? AND version = ? ORDER BY id', [$tenant, $version])->fetchAll());
    }
}
