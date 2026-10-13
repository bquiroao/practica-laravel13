<?php

declare(strict_types=1);

/*
 * Semana 7 — pruebas de caracterización del refactor.
 * Fijan el comportamiento observable ANTES de mover responsabilidades, para que el
 * refactor no cambie nada (la tarea pide refactorizar «sin ampliar el módulo»).
 */

use Rbac\Dominio\Excepciones\ErrorPersistencia;
use Rbac\Dominio\Modelo\Membresia;
use Rbac\Dominio\Puertos\DirectorioUsuarios;
use Rbac\Dominio\Puertos\RepositorioMembresias;
use Rbac\Persistencia\ConjuntoAdaptadores;
use Rbac\Persistencia\EnMemoria\FabricaEnMemoria;
use Rbac\Persistencia\EnMemoria\RegistroAuditoriaEnMemoria;
use Rbac\Persistencia\EnMemoria\RepositorioMembresiasEnMemoria;
use Rbac\Pruebas\Dobles\Escenario;

/** Casos de uso sobre InMemory, reemplazando un adaptador para simular una falla de lectura puntual. */
function casosConAdaptador(?DirectorioUsuarios $usuarios = null, ?Closure $membresias = null): array
{
    $base = FabricaEnMemoria::crear(Escenario::datosDemo());
    $repo = $base->membresias;
    assert($repo instanceof RepositorioMembresiasEnMemoria);

    return [construirCasosDeUso(
        new ConjuntoAdaptadores($membresias ? $membresias($repo) : $repo, $base->catalogo, $usuarios ?? $base->usuarios, $base->auditoria, $base->transaccion),
        new RegistroAuditoriaEnMemoria(),
        static fn (): string => 'corr-ref',
        static fn (): DateTimeImmutable => new DateTimeImmutable(Escenario::AHORA),
    ), $repo, $base->auditoria];
}

prueba('REF-01', 'Asignar con el directorio de usuarios caído: 500 ERROR_PERSISTENCIA y ninguna membresía nueva', function (): void {
    $caido = new class () implements DirectorioUsuarios {
        public function estadoDe(string $usuarioId): ?string
        {
            throw new ErrorPersistencia('directorio caído', '08006');
        }
    };
    [$casos, $repo] = casosConAdaptador(usuarios: $caido);
    $r = $casos['asignarRol']->ejecutar(Escenario::comandoAsignar('admin-01', 'nuevo-01', 'laboratorio'));
    igual([500, 'ERROR_PERSISTENCIA'], [$r->codigoHttp, $r->codigo], 'resultado');
    afirmar(!$repo->existeActiva('nuevo-01', Escenario::H_ORIENTE, 'laboratorio'), 'no debía crearse la membresía');
});

prueba('REF-02', 'Revocar cuando la regla no puede leer las membresías del destino: 500 ERROR_PERSISTENCIA y nada revocado', function (): void {
    // Falla solo al leer las membresías del usuario destino; la autorización del admin sí funciona.
    $parcial = static fn (RepositorioMembresiasEnMemoria $real): RepositorioMembresias => new class ($real) implements RepositorioMembresias {
        public function __construct(private readonly RepositorioMembresiasEnMemoria $real)
        {
        }

        public function membresiasDe(string $usuarioId, string $hospitalId): array
        {
            if ($usuarioId === 'ana') {
                throw new ErrorPersistencia('lectura caída', '08006');
            }

            return $this->real->membresiasDe($usuarioId, $hospitalId);
        }

        public function existeActiva(string $usuarioId, string $hospitalId, string $rol): bool
        {
            return $this->real->existeActiva($usuarioId, $hospitalId, $rol);
        }

        public function guardar(Membresia $membresia): void
        {
            $this->real->guardar($membresia);
        }

        public function revocar(string $usuarioId, string $hospitalId, string $rol, DateTimeImmutable $momento): int
        {
            return $this->real->revocar($usuarioId, $hospitalId, $rol, $momento);
        }
    };
    [$casos, $repo] = casosConAdaptador(membresias: $parcial);
    $r = $casos['revocarRol']->ejecutar(Escenario::comandoRevocar('admin-01', 'ana', 'enfermeria'));
    igual([500, 'ERROR_PERSISTENCIA'], [$r->codigoHttp, $r->codigo], 'resultado');
    afirmar($repo->existeActiva('ana', Escenario::H_ORIENTE, 'enfermeria'), 'no debía revocarse');
});

prueba('REF-03', 'El formato de los tres eventos de auditoría no cambia (campos y orden)', function (): void {
    $e = new Escenario();
    $asignado = $e->asignarRol->ejecutar(Escenario::comandoAsignar('admin-01', 'nuevo-01', 'laboratorio'));
    $revocado = $e->caso('revocarRol')->ejecutar(Escenario::comandoRevocar('admin-01', 'ana', 'enfermeria'));
    $denegado = $e->autorizar->ejecutar(Escenario::comandoAutorizar('ana', 'prescripciones.crear'));
    $campos = static fn (?string $cid): array => array_keys($e->auditoria->buscarPorCorrelacion((string) $cid)?->datos ?? []);

    igual(['usuario_destino', 'rol', 'valido_desde', 'valido_hasta', 'ip'], $campos($asignado->correlationId), 'ROL_ASIGNADO');
    igual(['usuario_destino', 'rol', 'motivo', 'revocadas', 'ip'], $campos($revocado->correlationId), 'ROL_REVOCADO');
    igual(['permiso', 'recurso', 'motivo', 'ip'], $campos($denegado->correlationId), 'ACCESO_DENEGADO');
    $ev = $e->auditoria->buscarPorCorrelacion((string) $revocado->correlationId);
    igual(['admin-01', 'hosp-oriente', '1', 'Cambio de servicio: ya no atiende en esta unidad'], [$ev?->actorId, $ev?->hospitalId, $ev?->datos['revocadas'] ?? null, $ev?->datos['motivo'] ?? null], 'contenido ROL_REVOCADO');
});
