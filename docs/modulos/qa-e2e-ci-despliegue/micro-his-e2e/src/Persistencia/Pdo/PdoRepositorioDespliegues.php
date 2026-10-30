<?php

declare(strict_types=1);

namespace E2e\Persistencia\Pdo;

use E2e\Dominio\Modelo\Despliegue;
use E2e\Dominio\Puertos\RepositorioDespliegues;

final class PdoRepositorioDespliegues extends ConsultasPdo implements RepositorioDespliegues
{
    public function registrar(Despliegue $d): Despliegue
    {
        $id = (int) $this->consulta('INSERT INTO despliegues (tenant, version, digest, entorno, guia, operador, estado, revertido_a, en)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?) RETURNING id', [$d->tenant, $d->version, $d->digest, $d->entorno, $d->guia, $d->operador, $d->estado, $d->revertidoA,
            $d->en->format(DATE_ATOM)])->fetchColumn();

        return $d->conId($id);
    }

    public function deVersion(string $tenant, string $version): array
    {
        return array_map(self::despliegue(...), $this->consulta('SELECT * FROM despliegues WHERE tenant = ? AND version = ? ORDER BY id', [$tenant, $version])->fetchAll());
    }

    public function vigente(string $tenant, string $entorno): ?Despliegue
    {
        $f = $this->consulta("SELECT * FROM despliegues WHERE tenant = ? AND entorno = ? AND estado = 'DESPLEGADO' ORDER BY id DESC LIMIT 1", [$tenant, $entorno])->fetch();

        return $f === false ? null : self::despliegue($f);
    }

    /** @param array<string, mixed> $f */
    private static function despliegue(array $f): Despliegue
    {
        return new Despliegue((int) $f['id'], $f['tenant'], $f['version'], $f['digest'], $f['entorno'], $f['guia'], $f['operador'], $f['estado'], $f['revertido_a'],
            self::hora($f['en']));
    }
}
