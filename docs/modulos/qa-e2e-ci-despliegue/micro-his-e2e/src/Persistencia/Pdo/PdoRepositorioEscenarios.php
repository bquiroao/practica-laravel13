<?php

declare(strict_types=1);

namespace E2e\Persistencia\Pdo;

use E2e\Dominio\Modelo\EscenarioE2E;
use E2e\Dominio\Puertos\RepositorioEscenarios;

final class PdoRepositorioEscenarios extends ConsultasPdo implements RepositorioEscenarios
{
    public function guardar(EscenarioE2E $e): void
    {
        $this->consulta('INSERT INTO escenarios (tenant, escenario_id, titulo, modulo, critico, requisitos, datos, autor) VALUES (?, ?, ?, ?, ?, ?::jsonb, ?, ?)',
            [$e->tenant, $e->id, $e->titulo, $e->modulo, $e->critico ? 'true' : 'false', self::aJson($e->requisitos), $e->datos, $e->autor]);
    }

    public function porId(string $tenant, string $id): ?EscenarioE2E
    {
        $f = $this->consulta('SELECT * FROM escenarios WHERE tenant = ? AND escenario_id = ?', [$tenant, $id])->fetch();

        return $f === false ? null : self::escenario($f);
    }

    public function delTenant(string $tenant): array
    {
        return array_map(self::escenario(...), $this->consulta('SELECT * FROM escenarios WHERE tenant = ? ORDER BY escenario_id', [$tenant])->fetchAll());
    }

    /** @param array<string, mixed> $f */
    private static function escenario(array $f): EscenarioE2E
    {
        return new EscenarioE2E($f['tenant'], $f['escenario_id'], $f['titulo'], $f['modulo'], (bool) $f['critico'], self::json($f['requisitos']), $f['datos'],
            $f['autor']);
    }
}
