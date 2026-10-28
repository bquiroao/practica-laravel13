<?php

declare(strict_types=1);

/*
 * Integración con PostgreSQL real (opcional). Se ejecuta solo si existe PACIENTES_DB_DSN.
 * Crea un esquema temporal, aplica schema.sql, prueba de punta a punta (HTTP → PDO) y lo elimina.
 *
 *   PACIENTES_DB_DSN="pgsql:host=127.0.0.1;port=5432;dbname=postgres"
 *   PACIENTES_DB_USUARIO=postgres   PACIENTES_DB_CLAVE=...
 */

use Pacientes\Persistencia\ConjuntoAdaptadores;
use Pacientes\Persistencia\Configuracion\PoliticaAccesoEstatica;
use Pacientes\Persistencia\FabricaPdo;
use Pacientes\Persistencia\Pdo\FabricaConexion;
use Pacientes\Persistencia\Seguridad\ProteccionDocumentos;
use Pacientes\Presentacion\Http\Autenticacion\AutenticadorTokenDemo;
use Pacientes\Pruebas\Dobles\Escenario;
use Pacientes\Pruebas\Dobles\RegistroAuditoriaConFallas;
use Pacientes\Pruebas\Dobles\RelojFijo;

$dsn = getenv('PACIENTES_DB_DSN');

if ($dsn === false || $dsn === '') {
    prueba('INT-00', 'Integración PostgreSQL (HTTP→tabla, documento cifrado, versión, append-only, rollback)',
        static fn () => omitir('defina PACIENTES_DB_DSN para ejecutarla'));

    return;
}

$pdo = FabricaConexion::crear($dsn, getenv('PACIENTES_DB_USUARIO') ?: 'postgres', getenv('PACIENTES_DB_CLAVE') ?: '');
$esquema = 'microhis_pacientes_prueba_' . bin2hex(random_bytes(4));
$pdo->exec("CREATE SCHEMA $esquema");
$pdo->exec("SET search_path TO $esquema");
$pdo->exec((string) file_get_contents(dirname(__DIR__, 2) . '/database/schema.sql'));

$adaptadores = FabricaPdo::sobre($pdo, new ProteccionDocumentos(random_bytes(32), random_bytes(32)));
$config = Escenario::config();
$casosCon = static fn (ConjuntoAdaptadores $a): array => construirCasosDeUso($a, new PoliticaAccesoEstatica($config['permisos']), new RelojFijo());
$kernel = construirKernel($casosCon($adaptadores), new AutenticadorTokenDemo($config['tokens_demo']));
$contar = static fn (string $sql): int => (int) $pdo->query($sql)->fetchColumn();

try {
    prueba('INT-01', 'PostgreSQL: POST → fila en pacientes (DPI cifrado, nunca en claro) + PACIENTE_REGISTRADO', function () use ($kernel, $contar, $pdo): void {
        $r = $kernel->manejar(Escenario::http('POST', '/api/v1/pacientes', cuerpo: Escenario::json(Escenario::datosAna())));
        igual(201, $r->estado, 'HTTP');
        igual(1, $contar("SELECT count(*) FROM pacientes WHERE code = 'P-000001' AND documento_enmascarado = '*********0101'"), 'fila');
        afirmar(!str_contains((string) json_encode($pdo->query('SELECT * FROM pacientes')->fetchAll()), Escenario::DPI_ANA), 'el DPI está en claro en la tabla');
        igual(1, $contar("SELECT count(*) FROM eventos_auditoria WHERE accion = 'PACIENTE_REGISTRADO'"), 'evento');
    });

    prueba('INT-02', 'PostgreSQL: búsqueda y detalle descifran el documento; PUT con versión vieja → 409 real', function () use ($kernel): void {
        $b = $kernel->manejar(Escenario::http('GET', '/api/v1/pacientes', 'token-clinico-oriente', consulta: ['nombre' => 'LÓPEZ', 'nacimiento' => '1985-03']));
        igual([200, 1, '*********0101'], [$b->estado, $b->cuerpo['total'], $b->cuerpo['resultados'][0]['identificador'] ?? null], 'búsqueda');
        $put = static fn (string $version, string $tel) => $kernel->manejar(Escenario::http('PUT', '/api/v1/pacientes/P-000001',
            cuerpo: Escenario::json(Escenario::datosAna(['telefono' => $tel])), cabeceras: ['If-Match' => $version]));
        igual(200, $put('"1"', '+502 4444 0001')->estado, 'primera edición');
        $viejo = $put('"1"', '+502 3333 0002');
        igual([409, 2], [$viejo->estado, $viejo->cuerpo['version_vigente'] ?? null], 'segunda edición');
    });

    prueba('INT-03', 'PostgreSQL: la bitácora es append-only (el trigger rechaza UPDATE y DELETE)', function () use ($pdo): void {
        lanza(PDOException::class, fn () => $pdo->exec("UPDATE eventos_auditoria SET actor_id = 'x'"), 'UPDATE');
        lanza(PDOException::class, fn () => $pdo->exec('DELETE FROM eventos_auditoria'), 'DELETE');
    });

    prueba('INT-04', 'PostgreSQL: si la bitácora falla dentro de la transacción, el paciente se revierte (ROLLBACK real)', function () use ($adaptadores, $casosCon, $contar): void {
        $bitacora = new RegistroAuditoriaConFallas();
        $bitacora->fallar = true;
        $conFalla = new ConjuntoAdaptadores($adaptadores->consulta, $adaptadores->repositorio, $adaptadores->indice, $bitacora, $adaptadores->transaccion, $adaptadores->idempotencia);
        $r = Escenario::registrar((object) ['casos' => $casosCon($conFalla)], Escenario::datosLuis());
        igual('no_disponible', $r->estado, 'resultado');
        igual(0, $contar("SELECT count(*) FROM pacientes WHERE given_name = 'Luis Fernando'"), 'paciente revertido');
    });
} finally {
    $pdo->exec("DROP SCHEMA $esquema CASCADE");
}
