<?php

declare(strict_types=1);

namespace Qa\Persistencia\Pdo;

use Qa\Dominio\Modelo\Defecto;
use Qa\Dominio\Modelo\EstadoDefecto;
use Qa\Dominio\Modelo\Severidad;
use Qa\Dominio\Puertos\RepositorioDefectos;

final class PdoRepositorioDefectos extends ConsultasPdo implements RepositorioDefectos
{
    public function crear(Defecto $defecto): Defecto
    {
        $id = (int) $this->consulta('INSERT INTO defectos (tenant, campana_id, caso_id, titulo, pasos, severidad_propuesta, con_evidencia,
            reportado_por, estado, severidad, prioridad) VALUES (?, ?, ?, ?, ?::jsonb, ?, ?, ?, ?, ?, ?) RETURNING id', [$defecto->tenant,
            $defecto->campanaId, $defecto->casoId, $defecto->titulo, json_encode($defecto->pasos, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            $defecto->propuesta->value, $defecto->conEvidencia ? 'true' : 'false', $defecto->reportadoPor, $defecto->estado()->value,
            $defecto->severidad()?->value, $defecto->prioridad()])->fetchColumn();
        $this->historial($defecto->tenant, $id, ["REPORTADO {$defecto->propuesta->value} por {$defecto->reportadoPor}", ...$defecto->historialNuevo()]);

        return $defecto->conId($id);
    }

    public function actualizar(Defecto $defecto): void
    {
        $this->consulta('UPDATE defectos SET estado = ?, severidad = ?, prioridad = ? WHERE tenant = ? AND id = ?',
            [$defecto->estado()->value, $defecto->severidad()?->value, $defecto->prioridad(), $defecto->tenant, $defecto->id]);
        $this->historial($defecto->tenant, (int) $defecto->id, $defecto->historialNuevo());
    }

    public function porId(string $tenant, int $id): ?Defecto
    {
        $fila = $this->consulta('SELECT * FROM defectos WHERE tenant = ? AND id = ?', [$tenant, $id])->fetch();

        return $fila === false ? null : self::defecto($fila);
    }

    public function deCampana(string $tenant, int $campanaId): array
    {
        return array_map(self::defecto(...), $this->consulta('SELECT * FROM defectos WHERE tenant = ? AND campana_id = ? ORDER BY id',
            [$tenant, $campanaId])->fetchAll());
    }

    public function criticosAbiertos(string $tenant, array $casos): int
    {
        if ($casos === []) {
            return 0;
        }

        return (int) $this->consulta("SELECT count(*) FROM defectos WHERE tenant = ? AND severidad = 'CRITICA' AND estado <> 'CERRADO'
            AND caso_id IN (" . self::enlaces($casos) . ')', [$tenant, ...array_values($casos)])->fetchColumn();
    }

    /** @param list<string> $eventos */
    private function historial(string $tenant, int $id, array $eventos): void
    {
        foreach ($eventos as $evento) {
            $this->consulta('INSERT INTO defecto_historial (tenant, defecto_id, evento) VALUES (?, ?, ?)', [$tenant, $id, $evento]);
        }
    }

    /** @param array<string, mixed> $f */
    private static function defecto(array $f): Defecto
    {
        return new Defecto((int) $f['id'], $f['tenant'], (int) $f['campana_id'], $f['caso_id'], $f['titulo'], self::json($f['pasos']),
            Severidad::from($f['severidad_propuesta']), (bool) $f['con_evidencia'], $f['reportado_por'], EstadoDefecto::from($f['estado']),
            $f['severidad'] === null ? null : Severidad::from($f['severidad']), $f['prioridad']);
    }
}
