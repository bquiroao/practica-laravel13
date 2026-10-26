<?php

declare(strict_types=1);

namespace Rbac\Persistencia\EnMemoria;

use Rbac\Dominio\Modelo\Membresia;
use Rbac\Persistencia\ConjuntoAdaptadores;

/** Crea los adaptadores InMemory a partir de datos semilla (database/datos-demo.php). */
final class FabricaEnMemoria
{
    /**
     * @param array{
     *     usuarios: array<string, string>,
     *     permisos_por_rol: array<string, list<string>>,
     *     membresias: list<array{0: string, 1: string, 2: string, 3: string, 4: string, 5: ?string}>
     * } $datos
     */
    public static function crear(array $datos): ConjuntoAdaptadores
    {
        $membresias = new RepositorioMembresiasEnMemoria(array_map(
            static fn (array $m): Membresia => new Membresia(
                $m[0],
                $m[1],
                $m[2],
                $m[3],
                new \DateTimeImmutable($m[4]),
                $m[5] === null ? null : new \DateTimeImmutable($m[5]),
            ),
            $datos['membresias'],
        ));
        $auditoria = new RegistroAuditoriaEnMemoria();

        return new ConjuntoAdaptadores(
            $membresias,
            new CatalogoPermisosEnMemoria($datos['permisos_por_rol']),
            new DirectorioUsuariosEnMemoria($datos['usuarios']),
            $auditoria,
            new TransaccionEnMemoria($membresias, $auditoria),
        );
    }
}
