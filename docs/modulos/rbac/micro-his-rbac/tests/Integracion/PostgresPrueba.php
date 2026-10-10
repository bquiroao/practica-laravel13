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
use Rbac\Persistencia\Pdo\FabricaConexion;
use Rbac\Persistencia\Pdo\PdoCatalogoPermisos;
use Rbac\Persistencia\Pdo\PdoDirectorioUsuarios;
use Rbac\Persistencia\Pdo\PdoRegistroAuditoria;
use Rbac\Persistencia\Pdo\PdoRepositorioMembresias;
use Rbac\Persistencia\Pdo\PdoTransaccion;
use Rbac\Presentacion\Http\Autenticacion\AutenticadorTokenDemo;
use Rbac\Pruebas\Dobles\Escenario;
use Rbac\Pruebas\Dobles\RegistroAuditoriaEnMemoria;

$dsn = getenv('MICROHIS_DB_DSN');

if ($dsn === false || $dsn === '') {
    prueba('INT-00', 'Integración PostgreSQL (asignación, denegación, 23505, append-only, rollback)', static fn () => omitir('defina MICROHIS_DB_DSN para ejecutarla'));

    return;
}

$pdo = FabricaConexion::crear($dsn, getenv('MICROHIS_DB_USUARIO') ?: 'postgres', getenv('MICROHIS_DB_CLAVE') ?: '');
$esquema = 'microhis_prueba_' . bin2hex(random_bytes(4));
$pdo->exec("CREATE SCHEMA $esquema");
$pdo->exec("SET search_path TO $esquema");
$pdo->exec((string) file_get_contents(dirname(__DIR__, 2) . '/database/schema.sql'));
$pdo->exec((string) file_get_contents(dirname(__DIR__, 2) . '/database/datos-demo.sql'));

$correlaciones = ['corr-int-1', 'corr-int-2', 'corr-int-3', 'corr-int-4'];
$siguiente = 0;
$crearCasos = static fn (Closure $correlacion): array => construirCasosDeUso(
    new PdoRepositorioMembresias($pdo),
    new PdoCatalogoPermisos($pdo),
    new PdoDirectorioUsuarios($pdo),
    new PdoRegistroAuditoria($pdo),
    new RegistroAuditoriaEnMemoria(),
    new PdoTransaccion($pdo),
    $correlacion,
    static fn (): DateTimeImmutable => new DateTimeImmutable(Escenario::AHORA),
);
$casos = $crearCasos(static function () use (&$siguiente, $correlaciones): string {
    return $correlaciones[$siguiente++];
});
$kernel = construirKernel($casos, new AutenticadorTokenDemo([
    'token-admin-oriente' => ['admin-01', 'hosp-oriente'],
    'token-ana' => ['ana', 'hosp-oriente'],
]));
$contar = static fn (string $sql): int => (int) $pdo->query($sql)->fetchColumn();

try {
    prueba('INT-01', 'PostgreSQL: asignación HTTP → fila en membresias + ROL_ASIGNADO en eventos_auditoria', function () use ($kernel, $contar): void {
        $r = $kernel->manejar(Escenario::http('POST', '/api/v1/roles/asignaciones', 'token-admin-oriente', cuerpo: [
            'usuario_id' => 'nuevo-01', 'rol' => 'laboratorio', 'valido_desde' => '2026-11-15T00:00:00-06:00',
        ]));
        igual(201, $r->estado, 'HTTP');
        igual(1, $contar("SELECT count(*) FROM membresias WHERE usuario_id = 'nuevo-01' AND rol_codigo = 'laboratorio'"), 'membresía');
        igual(1, $contar("SELECT count(*) FROM eventos_auditoria WHERE tipo = 'ROL_ASIGNADO'"), 'evento');
    });

    prueba('INT-02', 'PostgreSQL: denegación HTTP → 403 y ACCESO_DENEGADO con el motivo en jsonb', function () use ($kernel, $contar): void {
        $r = $kernel->manejar(Escenario::http('POST', '/api/v1/prescripciones', 'token-ana'));
        igual(403, $r->estado, 'HTTP');
        igual(1, $contar("SELECT count(*) FROM eventos_auditoria WHERE tipo = 'ACCESO_DENEGADO' AND datos->>'motivo' = 'PERMISO_NO_ASIGNADO'"), 'evento');
    });

    prueba('INT-03', 'PostgreSQL: el índice único parcial produce 23505 → MembresiaDuplicada', function () use ($pdo): void {
        $repo = new PdoRepositorioMembresias($pdo);
        lanza(MembresiaDuplicada::class, fn () => $repo->guardar(new Membresia('ana', 'hosp-oriente', 'enfermeria', 'active', new DateTimeImmutable('2026-11-15'))), 'duplicado real');
    });

    prueba('INT-04', 'PostgreSQL: la bitácora es append-only (el trigger rechaza UPDATE y DELETE)', function () use ($pdo): void {
        lanza(PDOException::class, fn () => $pdo->exec("UPDATE eventos_auditoria SET actor_id = 'x'"), 'UPDATE');
        lanza(PDOException::class, fn () => $pdo->exec('DELETE FROM eventos_auditoria'), 'DELETE');
    });

    prueba('INT-05', 'PostgreSQL: si ROL_ASIGNADO falla (correlation_id repetido), la membresía se revierte', function () use ($crearCasos, $contar): void {
        // Reutiliza un correlation_id ya guardado: el INSERT en la bitácora viola UNIQUE dentro de la transacción.
        $casos = $crearCasos(static fn (): string => 'corr-int-1');
        $r = $casos['asignarRol']->ejecutar(Escenario::comandoAsignar('admin-01', 'marta', 'laboratorio'));
        igual(500, $r->codigoHttp, 'HTTP');
        igual(0, $contar("SELECT count(*) FROM membresias WHERE usuario_id = 'marta' AND rol_codigo = 'laboratorio'"), 'membresía revertida');
    });
} finally {
    $pdo->exec("DROP SCHEMA $esquema CASCADE");
}
