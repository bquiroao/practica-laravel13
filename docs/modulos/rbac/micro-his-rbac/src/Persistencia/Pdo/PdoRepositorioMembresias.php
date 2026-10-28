<?php

declare(strict_types=1);

namespace Rbac\Persistencia\Pdo;

use Rbac\Dominio\Excepciones\ErrorPersistencia;
use Rbac\Dominio\Excepciones\MembresiaDuplicada;
use Rbac\Dominio\Modelo\Membresia;
use Rbac\Dominio\Puertos\RepositorioMembresias;

final class PdoRepositorioMembresias extends RepositorioPdo implements RepositorioMembresias
{
    private const VIOLACION_UNICIDAD = '23505';

    public function membresiasDe(string $usuarioId, string $hospitalId): array
    {
        $filas = $this->ejecutar(
            'SELECT usuario_id, hospital_id, rol_codigo, estado, valido_desde, valido_hasta
               FROM membresias
              WHERE usuario_id = :usuario AND hospital_id = :hospital',
            ['usuario' => $usuarioId, 'hospital' => $hospitalId],
        )->fetchAll(\PDO::FETCH_ASSOC);

        return array_map(static fn (array $f): Membresia => new Membresia(
            (string) $f['usuario_id'],
            (string) $f['hospital_id'],
            (string) $f['rol_codigo'],
            (string) $f['estado'],
            new \DateTimeImmutable((string) $f['valido_desde']),
            $f['valido_hasta'] === null ? null : new \DateTimeImmutable((string) $f['valido_hasta']),
        ), $filas);
    }

    public function existeActiva(string $usuarioId, string $hospitalId, string $rol): bool
    {
        return $this->ejecutar(
            "SELECT 1
               FROM membresias
              WHERE usuario_id = :usuario AND hospital_id = :hospital AND rol_codigo = :rol
                AND estado = 'active' AND valido_hasta IS NULL
              LIMIT 1",
            ['usuario' => $usuarioId, 'hospital' => $hospitalId, 'rol' => $rol],
        )->fetchColumn() !== false;
    }

    public function guardar(Membresia $membresia): void
    {
        try {
            $this->ejecutar(
                'INSERT INTO membresias (usuario_id, hospital_id, rol_codigo, estado, valido_desde, valido_hasta)
                 VALUES (:usuario, :hospital, :rol, :estado, :desde, :hasta)',
                [
                    'usuario' => $membresia->usuarioId,
                    'hospital' => $membresia->hospitalId,
                    'rol' => $membresia->rol,
                    'estado' => $membresia->estado,
                    'desde' => $membresia->validoDesde->format(DATE_ATOM),
                    'hasta' => $membresia->validoHasta?->format(DATE_ATOM),
                ],
            );
        } catch (ErrorPersistencia $e) {
            if ($e->sqlState === self::VIOLACION_UNICIDAD) {
                throw new MembresiaDuplicada('Ya existe una membresía activa igual', $e->sqlState, $e);
            }
            throw $e;
        }
    }

    public function revocar(string $usuarioId, string $hospitalId, string $rol, \DateTimeImmutable $momento): int
    {
        return $this->ejecutar(
            "UPDATE membresias
                SET estado = 'revoked'
              WHERE usuario_id = :usuario AND hospital_id = :hospital AND rol_codigo = :rol
                AND estado = 'active' AND (valido_hasta IS NULL OR valido_hasta > :momento)",
            ['usuario' => $usuarioId, 'hospital' => $hospitalId, 'rol' => $rol, 'momento' => $momento->format(DATE_ATOM)],
        )->rowCount();
    }
}
