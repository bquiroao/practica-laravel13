<?php

declare(strict_types=1);

/*
 * Mismos adaptadores PDO de producción contra una base real:
 *  - INT-01..05: SQLite en memoria (se crea y se destruye en cada prueba).
 *  - INT-06: PostgreSQL, solo si se define NOTASSOAP_DB_DSN (de lo contrario se omite).
 */

use NotasSoap\Persistencia\Pdo\FabricaConexion;
use NotasSoap\Persistencia\Pdo\PdoRegistroAuditoria;
use NotasSoap\Persistencia\Pdo\PdoRepositorioDiagnosticos;
use NotasSoap\Persistencia\Pdo\PdoRepositorioExpedientes;
use NotasSoap\Persistencia\Pdo\PdoRepositorioNotas;
use NotasSoap\Persistencia\Pdo\PdoTransaccion;
use NotasSoap\Persistencia\Pdo\PdoVerificadorAcceso;
use NotasSoap\Presentacion\Http\AutenticadorTokenDemo;
use NotasSoap\Presentacion\Http\Kernel;
use NotasSoap\Pruebas\Dobles\Escenario;
use NotasSoap\Pruebas\Dobles\GeneradorSecuencial;
use NotasSoap\Pruebas\Dobles\RelojFijo;

$kernelSobre = static fn (PDO $pdo): Kernel => construirKernel(
    new AutenticadorTokenDemo(Escenario::TOKENS),
    new PdoVerificadorAcceso($pdo),
    new PdoRepositorioExpedientes($pdo),
    new PdoRepositorioNotas($pdo),
    new PdoRepositorioDiagnosticos($pdo),
    new PdoRegistroAuditoria($pdo),
    new PdoTransaccion($pdo),
    new GeneradorSecuencial(),
    new RelojFijo(),
);

$sqlite = static function (): PDO {
    if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
        omitir('la extensión pdo_sqlite no está habilitada');
    }
    $pdo = FabricaConexion::crear('sqlite::memory:', '', '');
    $pdo->exec((string) file_get_contents(__DIR__ . '/esquema-sqlite.sql'));
    $pdo->exec((string) file_get_contents(dirname(__DIR__, 2) . '/database/datos-demo.sql'));

    return $pdo;
};

$contar = static fn (PDO $pdo, string $tabla): int => (int) $pdo->query("SELECT COUNT(*) FROM $tabla")->fetchColumn();

prueba('INT-01', 'Camino feliz con PDO real: la nota, el diagnóstico y el evento quedan en la base', function () use ($sqlite, $kernelSobre, $contar): void {
    $pdo = $sqlite();
    $r = $kernelSobre($pdo)->manejar(Escenario::peticion());
    igual(201, $r->estado, 'estado');
    igual([1, 1, 1], [$contar($pdo, 'clinical_notes'), $contar($pdo, 'diagnoses'), $contar($pdo, 'audit_events')], 'filas');
    $fila = $pdo->query("SELECT note_type, status, version_no FROM clinical_notes")->fetch();
    igual(['note_type' => 'soap', 'status' => 'draft', 'version_no' => 1], $fila, 'fila de la nota');
});

prueba('INT-02', 'Las reglas de acceso y de expediente se leen de la base (care_assignments, medical_records)', function () use ($sqlite, $kernelSobre, $contar): void {
    $pdo = $sqlite();
    $k = $kernelSobre($pdo);
    igual(403, $k->manejar(Escenario::peticion([], ['authorization' => 'Bearer token-lopez']))->estado, 'no asignado');
    igual(409, $k->manejar(Escenario::peticion(['care_episode_id' => 'epi-200']))->estado, 'episodio de otro paciente');
    igual(0, $contar($pdo, 'clinical_notes'), 'sin notas');
});

prueba('INT-03', 'Firmar y consultar contra la base; firmar dos veces responde 409', function () use ($sqlite, $kernelSobre): void {
    $k = $kernelSobre($sqlite());
    $id = $k->manejar(Escenario::peticion())->cuerpo['clinical_note_id'];
    igual(200, $k->manejar(Escenario::peticion([], [], 'POST', "/api/v1/notas-soap/$id/firma"))->estado, 'firma');
    igual(409, $k->manejar(Escenario::peticion([], [], 'POST', "/api/v1/notas-soap/$id/firma"))->estado, 'segunda firma');
    $c = $k->manejar(Escenario::peticion([], [], 'GET', "/api/v1/notas-soap/$id"))->cuerpo;
    igual(['signed', 'J18.9'], [$c['status'], $c['diagnoses'][0]['code']], 'consulta');
});

prueba('INT-04', 'Error de persistencia real: si falla el INSERT del diagnóstico se revierte la nota (R-06)', function () use ($sqlite, $kernelSobre, $contar): void {
    $pdo = $sqlite();
    $pdo->exec("CREATE TRIGGER romper_diagnoses BEFORE INSERT ON diagnoses BEGIN SELECT RAISE(ABORT, 'falla simulada'); END;");
    $r = $kernelSobre($pdo)->manejar(Escenario::peticion());
    igual([500, 'error_persistencia'], [$r->estado, $r->cuerpo['error']['code']], 'respuesta');
    igual([0, 0, 0], [$contar($pdo, 'clinical_notes'), $contar($pdo, 'diagnoses'), $contar($pdo, 'audit_events')], 'nada guardado');
});

prueba('INT-05', 'La base también protege: no deja modificar una nota firmada ni borrar notas (defensa en profundidad)', function () use ($sqlite, $kernelSobre): void {
    $pdo = $sqlite();
    $k = $kernelSobre($pdo);
    $id = $k->manejar(Escenario::peticion())->cuerpo['clinical_note_id'];
    $k->manejar(Escenario::peticion([], [], 'POST', "/api/v1/notas-soap/$id/firma"));
    $repo = new PdoRepositorioNotas($pdo);
    $nota = $repo->buscar($id);
    $editada = new NotasSoap\Dominio\Modelo\NotaSoap($nota->id, $nota->expedienteId, $nota->episodioId, $nota->autorId, $nota->secciones, $nota->version, 'signed', $nota->creadaEn, new DateTimeImmutable());
    // 1.ª barrera (aplicación): el UPDATE condicionado a status = 'draft' no afecta filas.
    lanza(NotasSoap\Dominio\Excepciones\NotaFirmada::class, fn () => $repo->registrarFirma($editada), 'firma repetida desde el repositorio');
    // 2.ª barrera (base): aunque alguien salte la aplicación, el trigger rechaza el cambio.
    lanza(PDOException::class, fn () => $pdo->exec("UPDATE clinical_notes SET content = 'alterado'"), 'UPDATE directo sobre nota firmada');
    lanza(PDOException::class, fn () => $pdo->exec('DELETE FROM clinical_notes'), 'DELETE');
    igual('signed', $repo->buscar($id)?->estado, 'la nota sigue firmada');
});

prueba('INT-06', 'PostgreSQL real (opcional): camino feliz, bandeja de salida en jsonb y reversión con database/schema.sql', function () use ($kernelSobre, $contar): void {
    $dsn = getenv('NOTASSOAP_DB_DSN');
    if ($dsn === false || $dsn === '') {
        omitir('defina NOTASSOAP_DB_DSN para probar contra PostgreSQL');
    }
    $pdo = FabricaConexion::crear($dsn, getenv('NOTASSOAP_DB_USUARIO') ?: 'postgres', getenv('NOTASSOAP_DB_CLAVE') ?: '');
    $esquema = 'prueba_' . bin2hex(random_bytes(4));
    $pdo->exec("CREATE SCHEMA $esquema");
    try {
        $pdo->exec("SET search_path TO $esquema");
        $pdo->exec((string) file_get_contents(dirname(__DIR__, 2) . '/database/schema.sql'));
        $pdo->exec((string) file_get_contents(dirname(__DIR__, 2) . '/database/datos-demo.sql'));
        $ids = new GeneradorSecuencial();
        $k = construirKernel(new AutenticadorTokenDemo(Escenario::TOKENS), new PdoVerificadorAcceso($pdo), new PdoRepositorioExpedientes($pdo),
            new NotasSoap\Aplicacion\Integracion\RepositorioNotasConEventos(new PdoRepositorioNotas($pdo), new NotasSoap\Persistencia\Pdo\PdoBandejaSalida($pdo), $ids),
            new PdoRepositorioDiagnosticos($pdo), new PdoRegistroAuditoria($pdo), new PdoTransaccion($pdo), $ids, new RelojFijo());
        $id = $k->manejar(Escenario::peticion())->cuerpo['clinical_note_id'] ?? null;
        afirmar($id !== null, 'registro en PostgreSQL');
        igual(200, $k->manejar(Escenario::peticion([], [], 'POST', "/api/v1/notas-soap/$id/firma"))->estado, 'firma');
        igual('NotaSoapFirmada', $pdo->query("SELECT payload_minimal->>'clinical_note_id' = '$id' AS ok, event_type FROM outbox_events")->fetch()['event_type'] ?? null, 'outbox en jsonb');
        $pdo->exec("CREATE FUNCTION $esquema.romper() RETURNS trigger LANGUAGE plpgsql AS \$\$ BEGIN RAISE EXCEPTION 'falla simulada'; END; \$\$");
        $pdo->exec("CREATE TRIGGER romper BEFORE INSERT ON diagnoses FOR EACH ROW EXECUTE FUNCTION $esquema.romper()");
        $r = $kernelSobre($pdo)->manejar(Escenario::peticion());
        igual(500, $r->estado, 'error de persistencia');
        igual(1, $contar($pdo, 'clinical_notes'), 'la segunda nota se revirtió');
    } finally {
        $pdo->exec("DROP SCHEMA $esquema CASCADE");
    }
});

prueba('INT-07', 'Repositorio compartido: al firmar con PDO queda una fila en outbox_events en la misma transacción, sin texto clínico', function () use ($sqlite, $contar): void {
    $pdo = $sqlite();
    $ids = new GeneradorSecuencial();
    $k = construirKernel(new AutenticadorTokenDemo(Escenario::TOKENS), new PdoVerificadorAcceso($pdo), new PdoRepositorioExpedientes($pdo),
        new NotasSoap\Aplicacion\Integracion\RepositorioNotasConEventos(new PdoRepositorioNotas($pdo), new NotasSoap\Persistencia\Pdo\PdoBandejaSalida($pdo), $ids),
        new PdoRepositorioDiagnosticos($pdo), new PdoRegistroAuditoria($pdo), new PdoTransaccion($pdo), $ids, new RelojFijo());
    $id = $k->manejar(Escenario::peticion())->cuerpo['clinical_note_id'];
    igual(0, $contar($pdo, 'outbox_events'), 'el borrador no genera evento');
    $k->manejar(Escenario::peticion([], [], 'POST', "/api/v1/notas-soap/$id/firma"));
    $fila = $pdo->query('SELECT event_type, aggregate_id, payload_minimal, published_at FROM outbox_events')->fetch();
    igual(['NotaSoapFirmada', $id, null], [$fila['event_type'], $fila['aggregate_id'], $fila['published_at']], 'fila pendiente de publicar');
    afirmar(!str_contains((string) $fila['payload_minimal'], 'Tos'), 'payload sin contenido clínico');
});

prueba('INT-08', 'Objetos reutilizables: el mismo flujo con adaptadores PDO y en memoria da respuestas idénticas', function () use ($sqlite, $kernelSobre): void {
    $flujo = static function (NotasSoap\Presentacion\Http\Kernel $k): array {
        $r1 = $k->manejar(Escenario::peticion());
        $id = $r1->cuerpo['clinical_note_id'];
        $r2 = $k->manejar(Escenario::peticion([], [], 'POST', "/api/v1/notas-soap/$id/firma"));
        $r3 = $k->manejar(Escenario::peticion([], [], 'GET', "/api/v1/notas-soap/$id"));
        $r4 = $k->manejar(Escenario::peticion([], ['authorization' => 'Bearer token-lopez']));

        return [[$r1->estado, $r1->cuerpo], [$r2->estado, $r2->cuerpo], [$r3->estado, $r3->cuerpo], [$r4->estado, $r4->cuerpo]];
    };
    $enMemoria = crearKernelEnMemoria(['tokens_demo' => Escenario::TOKENS],
        new NotasSoap\Persistencia\EnMemoria\AlmacenesEnMemoria(require dirname(__DIR__, 2) . '/config/datos-demo-memoria.php'), new GeneradorSecuencial(), new RelojFijo());
    igual($flujo($kernelSobre($sqlite())), $flujo($enMemoria), 'PDO vs memoria');
});
