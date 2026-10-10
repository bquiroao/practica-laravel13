<?php

declare(strict_types=1);

/*
 * Integración con PostgreSQL real (opcional). Se ejecuta solo si existe MICROHIS_DB_DSN.
 * Crea un esquema temporal, aplica schema.sql + datos-demo.sql, prueba y lo elimina.
 *
 *   MICROHIS_DB_DSN="pgsql:host=127.0.0.1;port=5432;dbname=postgres"
 *   MICROHIS_DB_USUARIO=postgres   MICROHIS_DB_CLAVE=...
 */

use Rbac\Dominio\Excepciones\MembresiaDuplicada;
use Rbac\Dominio\Modelo\Membresia;
use Rbac\Persistencia\EnMemoria\RegistroAuditoriaEnMemoria;
use Rbac\Persistencia\Pdo\FabricaPdo;
use Rbac\Persistencia\Pdo\PdoRepositorioMembresias;
use Rbac\Presentacion\Http\Autenticacion\AutenticadorTokenDemo;
use Rbac\Pruebas\Dobles\Escenario;
use Rbac\Pruebas\Soporte\PostgresTemporal;

if (!PostgresTemporal::disponible()) {
    prueba('INT-00', 'Integración PostgreSQL (MVC + PDO de punta a punta, 23505, append-only, rollback)', static fn () => omitir('defina MICROHIS_DB_DSN para ejecutarla'));

    return;
}

PostgresTemporal::ejecutar(static function (PDO $pdo): void {
    $crearCasos = static fn (Closure $correlacion): array => construirCasosDeUso(
        FabricaPdo::crear($pdo),
        new RegistroAuditoriaEnMemoria(),
        $correlacion,
        static fn (): DateTimeImmutable => new DateTimeImmutable(Escenario::AHORA),
    );
    $siguiente = 0;
    $casos = $crearCasos(static function () use (&$siguiente): string {
        return 'corr-int-' . ++$siguiente;
    });
    $kernel = construirKernel($casos, new AutenticadorTokenDemo([
        'token-admin-oriente' => ['admin-01', 'hosp-oriente'],
        'token-ana' => ['ana', 'hosp-oriente'],
    ]));
    $contar = static fn (string $sql): int => (int) $pdo->query($sql)->fetchColumn();

    prueba('INT-01', 'PostgreSQL: asignación HTTP → fila en membresias + ROL_ASIGNADO en eventos_auditoria', function () use ($kernel, $contar): void {
        $r = $kernel->manejar(Escenario::http('POST', '/api/v1/roles/asignaciones', 'token-admin-oriente', cuerpo: [
            'usuario_id' => 'nuevo-01', 'rol' => 'laboratorio', 'valido_desde' => '2026-11-15T00:00:00-06:00',
        ]));
        igual(201, $r->estado, 'HTTP');
        igual(1, $contar("SELECT count(*) FROM membresias WHERE usuario_id = 'nuevo-01' AND rol_codigo = 'laboratorio'"), 'membresía');
        igual(1, $contar("SELECT count(*) FROM eventos_auditoria WHERE tipo = 'ROL_ASIGNADO'"), 'evento');
    });

    prueba('INT-02', 'PostgreSQL: denegación HTTP → 403 y ACCESO_DENEGADO con el motivo en jsonb', function () use ($kernel, $contar): void {
        igual(403, $kernel->manejar(Escenario::http('POST', '/api/v1/prescripciones', 'token-ana'))->estado, 'HTTP');
        igual(1, $contar("SELECT count(*) FROM eventos_auditoria WHERE tipo = 'ACCESO_DENEGADO' AND datos->>'motivo' = 'PERMISO_NO_ASIGNADO'"), 'evento');
    });

    prueba('INT-03', 'PostgreSQL: el índice único parcial produce 23505 → MembresiaDuplicada', function () use ($pdo): void {
        lanza(MembresiaDuplicada::class, fn () => (new PdoRepositorioMembresias($pdo))
            ->guardar(new Membresia('ana', 'hosp-oriente', 'enfermeria', 'active', new DateTimeImmutable('2026-11-15'))), 'duplicado real');
    });

    prueba('INT-04', 'PostgreSQL: la bitácora es append-only (el trigger rechaza UPDATE y DELETE)', function () use ($pdo): void {
        lanza(PDOException::class, fn () => $pdo->exec("UPDATE eventos_auditoria SET actor_id = 'x'"), 'UPDATE');
        lanza(PDOException::class, fn () => $pdo->exec('DELETE FROM eventos_auditoria'), 'DELETE');
    });

    prueba('INT-05', 'PostgreSQL: si ROL_ASIGNADO falla (correlation_id repetido), la membresía se revierte', function () use ($crearCasos, $contar): void {
        $casos = $crearCasos(static fn (): string => 'corr-int-1');
        igual(500, $casos['asignarRol']->ejecutar(Escenario::comandoAsignar('admin-01', 'marta', 'laboratorio'))->codigoHttp, 'HTTP');
        igual(0, $contar("SELECT count(*) FROM membresias WHERE usuario_id = 'marta' AND rol_codigo = 'laboratorio'"), 'membresía revertida');
    });
});
