<?php

declare(strict_types=1);

namespace E2e\Persistencia\Pdo;

use E2e\Dominio\Modelo\Version;
use E2e\Dominio\Puertos\RepositorioVersiones;

final class PdoRepositorioVersiones extends ConsultasPdo implements RepositorioVersiones
{
    public function crear(Version $v): void
    {
        $this->consulta('INSERT INTO versiones (tenant, numero, commit_sha, digest, modulos, registrada_por, estado, plan, plan_congelado)
            VALUES (?, ?, ?, ?, ?::jsonb, ?, ?, ?::jsonb, ?)', [$v->tenant, $v->numero, $v->commit, $v->digest, self::aJson($v->modulos), $v->registradaPor,
            $v->estado(), $v->plan() === null ? null : self::aJson($v->plan()), $v->planCongelado() ? 'true' : 'false']);
    }

    public function actualizar(Version $v): void
    {
        $this->consulta('UPDATE versiones SET estado = ?, plan = ?::jsonb, plan_congelado = ? WHERE tenant = ? AND numero = ?',
            [$v->estado(), $v->plan() === null ? null : self::aJson($v->plan()), $v->planCongelado() ? 'true' : 'false', $v->tenant, $v->numero]);
    }

    public function porNumero(string $tenant, string $numero): ?Version
    {
        $f = $this->consulta('SELECT * FROM versiones WHERE tenant = ? AND numero = ?', [$tenant, $numero])->fetch();

        return $f === false ? null : self::version($f);
    }

    public function porDigest(string $tenant, string $digest): ?Version
    {
        $f = $this->consulta('SELECT * FROM versiones WHERE tenant = ? AND digest = ?', [$tenant, $digest])->fetch();

        return $f === false ? null : self::version($f);
    }

    /** @param array<string, mixed> $f */
    private static function version(array $f): Version
    {
        return new Version($f['tenant'], $f['numero'], $f['commit_sha'], $f['digest'], self::json($f['modulos']), $f['registrada_por'], $f['estado'],
            $f['plan'] === null ? null : array_map('boolval', self::json($f['plan'])), (bool) $f['plan_congelado']);
    }
}
