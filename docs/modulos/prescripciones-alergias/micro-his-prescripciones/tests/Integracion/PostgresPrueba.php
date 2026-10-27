<?php

declare(strict_types=1);

/*
 * Integración con PostgreSQL real. Requiere PRESCRIPCIONES_DB_DSN (y PRESCRIPCIONES_DB_USUARIO / _CLAVE).
 * Cada prueba recrea el esquema «prescripciones» con database/schema.sql y database/datos-demo.sql.
 */

use Prescripciones\Aplicacion\Puertos\FuenteAlergiasNoDisponible;
use Prescripciones\Aplicacion\Puertos\RegistroAuditoria;
use Prescripciones\Aplicacion\Resultados\Resultado;
use Prescripciones\Dominio\Excepciones\ClaveEnUso;
use Prescripciones\Dominio\Excepciones\ErrorPersistencia;
use Prescripciones\Persistencia\Pdo\FabricaConexion;
use Prescripciones\Persistencia\Pdo\PdoFuenteAlergias;
use Prescripciones\Persistencia\Pdo\PdoRepositorioPrescripciones;
use Prescripciones\Presentacion\Http\Autenticacion\AutenticadorTokenDemo;

/** @return array{0: \PDO, 1: array<string, mixed>} conexión a una base recién instalada y la configuración */
function baseLimpia(): array
{
    $dsn = getenv('PRESCRIPCIONES_DB_DSN');
    if ($dsn === false || $dsn === '') {
        omitir('sin PRESCRIPCIONES_DB_DSN');
    }
    $config = require dirname(__DIR__, 2) . '/config/config.php';
    $pdo = FabricaConexion::crear($config['db']['dsn'], $config['db']['usuario'], $config['db']['clave']);
    $pdo->exec((string) file_get_contents(dirname(__DIR__, 2) . '/database/schema.sql'));
    $pdo->exec((string) file_get_contents(dirname(__DIR__, 2) . '/database/datos-demo.sql'));

    return [$pdo, $config];
}

function contar(\PDO $pdo, string $tabla): int
{
    return (int) $pdo->query("SELECT count(*) FROM $tabla")->fetchColumn();
}

prueba('INT-01', 'Camino feliz por HTTP con PostgreSQL: 201, ítems, verificación y traza en la bandeja; consulta idéntica', function (): void {
    [$pdo, $config] = baseLimpia();
    $kernel = crearKernel($config);
    $r = $kernel->manejar(Escenario::http('POST', '/api/v1/prescripciones', ['paciente' => 'P-002', 'items' => [Escenario::item('CEFA500', 500, 6, 'oral', 7)],
        'justificacion' => JUSTIFICACION_VALIDA], cabeceras: ['idempotency-key' => 'int-1']));
    igual([201, 'ADVERTENCIA'], [$r->estado, $r->cuerpo['verificacion'] ?? null], 'emisión');
    $id = $r->cuerpo['id'];
    igual([1, 1, 1], [contar($pdo, 'prescriptions'), contar($pdo, 'prescription_items'), contar($pdo, 'prescription_audit_outbox')], 'filas');
    $traza = $pdo->query('SELECT action, resource, result FROM prescription_audit_outbox')->fetch(\PDO::FETCH_ASSOC);
    igual(['action' => 'PRESCRIPCION_EMITIDA', 'resource' => "prescripcion:$id", 'result' => 'exito'], $traza, 'traza');
    $c = $kernel->manejar(Escenario::http('GET', "/api/v1/prescripciones/$id", token: 'token-farmacia-norte'));
    igual([200, ['Penicilina'], JUSTIFICACION_VALIDA, 'CRUZADA'], [$c->estado, $c->cuerpo['verificacion']['alergenos_evaluados'],
        $c->cuerpo['verificacion']['justificacion'], $c->cuerpo['verificacion']['hallazgos'][0]['tipo']], 'consulta');
    igual(['medicamento' => 'CEFA500', 'dosis' => 500.0, 'unidad' => 'mg', 'frecuencia_horas' => 6, 'via' => 'oral', 'dias' => 7], $c->cuerpo['items'][0], 'ítem');
    afirmar(str_ends_with($c->cuerpo['emitida_en'], '+00:00'), 'H-2: la fecha debe salir en UTC, salió ' . $c->cuerpo['emitida_en']);
    igual(404, $kernel->manejar(Escenario::http('GET', "/api/v1/prescripciones/$id", token: 'token-dr-sur', tenant: 'hosp-sur'))->estado, 'otro hospital');
});

prueba('INT-02', 'R-10 en la base: el trigger rechaza UPDATE y DELETE de prescripciones e ítems', function (): void {
    [$pdo, $config] = baseLimpia();
    crearKernel($config)->manejar(Escenario::http('POST', '/api/v1/prescripciones', ['paciente' => 'P-005', 'items' => [Escenario::item('PARA500')]],
        cabeceras: ['idempotency-key' => 'int-2']));
    foreach (['UPDATE prescriptions SET prescriber = \'otro\'', 'DELETE FROM prescriptions', 'UPDATE prescription_items SET dose = 9999',
        'DELETE FROM prescription_items'] as $sql) {
        $e = lanza(\PDOException::class, static fn () => $pdo->exec($sql), $sql);
        afirmar(str_contains($e->getMessage(), 'prescripcion_inmutable'), "$sql: " . $e->getMessage());
    }
    igual([1, 1], [contar($pdo, 'prescriptions'), contar($pdo, 'prescription_items')], 'filas intactas');
});

prueba('INT-03', 'R-04 con datos reales: el bloqueo no crea prescripción y deja PRESCRIPCION_BLOQUEADA en la bandeja', function (): void {
    [$pdo, $config] = baseLimpia();
    $kernel = crearKernel($config);
    $directa = $kernel->manejar(Escenario::http('POST', '/api/v1/prescripciones', ['paciente' => 'P-007', 'items' => [Escenario::item('AMOX500')]],
        cabeceras: ['idempotency-key' => 'int-3a']));
    $cruzada = $kernel->manejar(Escenario::http('POST', '/api/v1/prescripciones', ['paciente' => 'P-006', 'items' => [Escenario::item('CEFA500')],
        'justificacion' => JUSTIFICACION_VALIDA], cabeceras: ['idempotency-key' => 'int-3b']));
    igual([[422, 'BLOQUEO_ALERGIA'], [422, 'BLOQUEO_ALERGIA_CRUZADA']], [[$directa->estado, $directa->cuerpo['error']], [$cruzada->estado, $cruzada->cuerpo['error']]], 'respuestas');
    igual(0, contar($pdo, 'prescriptions'), 'prescripciones');
    igual(['PRESCRIPCION_BLOQUEADA', 'PRESCRIPCION_BLOQUEADA'], $pdo->query('SELECT action FROM prescription_audit_outbox ORDER BY outbox_id')->fetchAll(\PDO::FETCH_COLUMN), 'bandeja');
});

prueba('INT-04', 'R-11 en la base: la restricción UNIQUE convierte la carrera en ClaveEnUso', function (): void {
    [$pdo] = baseLimpia();
    $repo = new PdoRepositorioPrescripciones($pdo);
    $s = Escenario::solicitud('P-005', [Escenario::item('PARA500')], clave: 'misma');
    $p = new \Prescripciones\Dominio\Modelo\Prescripcion(null, 'hosp-norte', 'P-005', 'dr-lopez', 'misma', $s->huella(), $s->items,
        new \Prescripciones\Dominio\Modelo\ResultadoVerificacion('SIN_HALLAZGOS', []), [], null, false, new \DateTimeImmutable());
    $repo->guardar($p);
    lanza(ClaveEnUso::class, static fn () => $repo->guardar($p), 'segundo INSERT con la misma clave');
    igual(1, contar($pdo, 'prescriptions'), 'filas');
});

prueba('INT-05', 'Todo o nada con PostgreSQL: si la traza falla, la prescripción y sus ítems se revierten', function (): void {
    [$pdo, $config] = baseLimpia();
    $puertos = puertosPdo($pdo, $config);
    $puertos['auditoria'] = new class implements RegistroAuditoria {
        public function registrar(string $accion, string $hospital, string $usuario, string $recurso, string $resultado): void
        {
            throw new ErrorPersistencia('bandeja caída');
        }
    };
    $r = construirCasosDeUso($puertos)['emitir']->ejecutar(Escenario::solicitud('P-005', [Escenario::item('PARA500')]));
    igual([Resultado::NO_DISPONIBLE, 0, 0], [$r->tipo, contar($pdo, 'prescriptions'), contar($pdo, 'prescription_items')], 'nada a medias');
});

prueba('INT-06', 'R-07 con la base real: si la tabla allergies no responde → 503 y nada emitido', function (): void {
    [$pdo, $config] = baseLimpia();
    $pdo->exec('ALTER TABLE allergies RENAME TO allergies_fuera_de_linea');
    try {
        lanza(FuenteAlergiasNoDisponible::class, static fn () => (new PdoFuenteAlergias($pdo))->alergiasDe('P-001', 'hosp-norte'), 'adaptador');
        $r = crearKernel($config)->manejar(Escenario::http('POST', '/api/v1/prescripciones', ['paciente' => 'P-001', 'items' => [Escenario::item('AMOX500')],
            'confirma_sin_alergias' => true], cabeceras: ['idempotency-key' => 'int-6']));
        igual([503, 'VERIFICACION_NO_DISPONIBLE'], [$r->estado, $r->cuerpo['error']], 'respuesta');
        igual(0, contar($pdo, 'prescriptions'), 'prescripciones');
        igual('VERIFICACION_NO_DISPONIBLE', $pdo->query('SELECT action FROM prescription_audit_outbox')->fetchColumn(), 'traza');
    } finally {
        $pdo->exec('ALTER TABLE allergies_fuera_de_linea RENAME TO allergies');
    }
});

prueba('INT-07', 'Las restricciones de la base respaldan las reglas (justificación < 20, dosis ≤ 0, frecuencia inválida)', function (): void {
    [$pdo] = baseLimpia();
    $base = "INSERT INTO prescriptions (hospital, local_patient_id, prescriber, idempotency_key, content_hash, allergy_check_result, override_justification, issued_at)
             VALUES ('hosp-norte', 'P-002', 'dr-lopez', '%s', repeat('a', 64), '%s', %s, now())";
    lanza(\PDOException::class, static fn () => $pdo->exec(sprintf($base, 'c1', 'ADVERTENCIA', "'corta'")), 'advertencia con justificación corta');
    lanza(\PDOException::class, static fn () => $pdo->exec(sprintf($base, 'c2', 'BLOQUEO', 'NULL')), 'un BLOQUEO no se puede guardar como prescripción');
    $pdo->exec(sprintf($base, 'c3', 'SIN_HALLAZGOS', 'NULL'));
    $id = (int) $pdo->query("SELECT prescription_id FROM prescriptions WHERE idempotency_key = 'c3'")->fetchColumn();
    $item = "INSERT INTO prescription_items VALUES ($id, %d, 'PARA500', %s, 'mg', %d, 'oral', 5)";
    lanza(\PDOException::class, static fn () => $pdo->exec(sprintf($item, 1, '0', 8)), 'dosis 0');
    lanza(\PDOException::class, static fn () => $pdo->exec(sprintf($item, 2, '500', 5)), 'frecuencia 5 h');
});

prueba('INT-08', 'El catálogo PDO resuelve vías como lista y el grupo del alérgeno por código o por nombre', function (): void {
    [$pdo] = baseLimpia();
    $c = new \Prescripciones\Persistencia\Pdo\PdoCatalogoMedicamentos($pdo);
    igual([['oral', 'rectal'], 4000.0, null], [$c->buscar('PARA500')?->vias, $c->buscar('PARA500')?->dosisMaximaDiaria, $c->buscar('NOEXISTE')], 'medicamentos');
    $a = Escenario::alergias();
    igual(['betalactamicos', 'betalactamicos', 'aines'], [$c->grupoDelAlergeno($a['P-001'][0]), $c->grupoDelAlergeno($a['P-002'][0]),
        $c->grupoDelAlergeno($a['P-004'][0])], 'grupos');
});

prueba('INT-09', 'Mismo comportamiento que los dobles: 11 escenarios dan la misma respuesta con PDO y en memoria', function (): void {
    [$pdo, $config] = baseLimpia();
    $conPdo = construirKernel(construirCasosDeUso(puertosPdo($pdo, $config)), new AutenticadorTokenDemo($config['tokens_demo']));
    $enMemoria = (new Escenario())->kernel();
    $escenarios = [
        ['P-005', [Escenario::item('PARA500')], null, false, 'token-dr-lopez'],
        ['P-005', [Escenario::item('PARA500', 1000, 4)], null, false, 'token-dr-lopez'],
        ['P-005', [Escenario::item('RANI150')], null, false, 'token-dr-lopez'],
        ['P-001', [Escenario::item('AMOX500')], JUSTIFICACION_VALIDA, false, 'token-dr-lopez'],
        ['P-007', [Escenario::item('AMOX500')], null, false, 'token-dr-lopez'],
        ['P-002', [Escenario::item('CEFA500')], null, false, 'token-dr-lopez'],
        ['P-002', [Escenario::item('CEFA500')], JUSTIFICACION_VALIDA, false, 'token-dr-ruiz'],
        ['P-004', [Escenario::item('IBU400', 400)], JUSTIFICACION_VALIDA, false, 'token-dr-lopez'],
        ['P-003', [Escenario::item('AMOX500')], null, false, 'token-dr-lopez'],
        ['P-003', [Escenario::item('AMOX500')], null, true, 'token-dr-lopez'],
        ['P-900', [Escenario::item('PARA500')], null, false, 'token-dr-lopez'],
    ];
    foreach ($escenarios as $i => [$paciente, $items, $justificacion, $confirma, $token]) {
        $cuerpo = ['paciente' => $paciente, 'items' => $items, 'justificacion' => $justificacion, 'confirma_sin_alergias' => $confirma];
        $a = $conPdo->manejar(Escenario::http('POST', '/api/v1/prescripciones', $cuerpo, $token, cabeceras: ['idempotency-key' => "par-$i"]));
        $b = $enMemoria->manejar(Escenario::http('POST', '/api/v1/prescripciones', $cuerpo, $token, cabeceras: ['idempotency-key' => "par-$i"]));
        igual([$b->estado, $b->cuerpo], [$a->estado, $a->cuerpo], "escenario $i ($paciente)");
    }
});
