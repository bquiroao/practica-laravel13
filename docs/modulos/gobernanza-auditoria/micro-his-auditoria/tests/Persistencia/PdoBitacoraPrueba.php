<?php

declare(strict_types=1);

use Auditoria\Dominio\Excepciones\ErrorPersistencia;
use Auditoria\Dominio\Filtros\FiltroRangoFechas;
use Auditoria\Dominio\Filtros\FiltroUsuario;
use Auditoria\Dominio\Modelo\ConsultaMovimientos;
use Auditoria\Dominio\Modelo\Movimiento;
use Auditoria\Dominio\Modelo\Sello;
use Auditoria\Dominio\Modelo\SolicitudRegistro;
use Auditoria\Persistencia\Pdo\PdoBitacora;
use Auditoria\Persistencia\Pdo\PdoTransaccion;
use Auditoria\Pruebas\Dobles\Escenario;
use Auditoria\Pruebas\Dobles\PdoGuionado;
use Auditoria\Pruebas\Dobles\PdoQueFalla;

$perMov = static fn (array $cambios = []): Movimiento =>
    Movimiento::desdeSolicitud(new SolicitudRegistro('svc-pacientes', Escenario::H_ORIENTE, Escenario::mov($cambios)));

/** Fila tal como la devuelve PostgreSQL: jsonb con las claves reordenadas y char(64) con su ancho. */
$perFila = static fn (array $cambios = []): array => array_merge([
    'id' => 7, 'emisor' => 'svc-pacientes', 'event_id' => 'ev-1', 'hospital_id' => 'hosp-oriente', 'usuario_id' => 'u-ana', 'modulo' => 'pacientes',
    'accion' => 'PACIENTE_REGISTRADO', 'ocurrido_en' => '2026-10-21 15:15:00+00', 'resultado' => 'exito', 'recurso' => 'paciente:P-000001',
    'correlation_id' => 'corr-0001', 'detalle' => '{"codigo": "P-000001"}', 'secuencia' => 3, 'sello' => str_repeat('a', 64),
], $cambios);

prueba('PER-01', 'agregar(): un INSERT preparado con parámetros con nombre, fecha en UTC, detalle JSON y el id que devuelve RETURNING', function () use ($perMov): void {
    $pdo = new PdoGuionado([['patron' => '/^INSERT INTO audit_logs .* RETURNING id$/s', 'filas' => [['id' => 41]]]]);
    $m = $perMov()->sellado(1, str_repeat('b', 64));
    $guardado = (new PdoBitacora($pdo))->agregar($m);
    igual(41, $guardado->id, 'id asignado por la base');
    $p = $pdo->ejecutadas[0]['parametros'];
    igual(['2026-10-21 15:15:00+00:00', '{"codigo":"P-000001"}', 1, 'hosp-oriente'], [$p['ocurrido_en'], $p['detalle'], $p['secuencia'], $p['hospital_id']], 'parámetros');
    afirmar(!str_contains($pdo->ejecutadas[0]['sql'], 'u-ana') && !str_contains($pdo->ejecutadas[0]['sql'], 'P-000001'), 'un valor quedó dentro del SQL');
});

prueba('PER-02', 'buscar(): compone el WHERE con el alcance, los fragmentos de cada filtro y el cursor; un valor malicioso solo viaja como parámetro', function (): void {
    $pdo = new PdoGuionado([['patron' => '/^SELECT .* FROM audit_logs WHERE .* ORDER BY ocurrido_en DESC, id DESC LIMIT :limite$/s', 'filas' => []]]);
    $malicioso = "u' OR '1'='1";
    $p = ['desde' => '2026-10-20', 'hasta' => '2026-10-21', 'usuario' => $malicioso];
    (new PdoBitacora($pdo))->buscar(new ConsultaMovimientos(Escenario::H_ORIENTE, false, [new FiltroRangoFechas(), new FiltroUsuario()], $p, 51,
        [new DateTimeImmutable('2026-10-21T10:00:00Z'), 9]));
    $sql = $pdo->ejecutadas[0]['sql'];
    foreach (['hospital_id = :alcance_hospital', '(ocurrido_en, id) < (CAST(:cursor_fecha AS timestamptz), :cursor_id)',
        '(ocurrido_en >= :desde AND ocurrido_en < :hasta_exclusivo)', '(usuario_id = :usuario)'] as $parte) {
        afirmar(str_contains($sql, $parte), "falta «{$parte}» en el SQL");
    }
    afirmar(!str_contains($sql, "'1'='1"), 'el valor malicioso quedó dentro del SQL');
    igual([$malicioso, 51, Escenario::H_ORIENTE, 9], [$pdo->ejecutadas[0]['parametros']['usuario'], $pdo->ejecutadas[0]['parametros']['limite'],
        $pdo->ejecutadas[0]['parametros']['alcance_hospital'], $pdo->ejecutadas[0]['parametros']['cursor_id']], 'parámetros');
    $red = new PdoGuionado([['filas' => []]]);
    (new PdoBitacora($red))->buscar(new ConsultaMovimientos(null, true, [], [], 51));
    afirmar(!str_contains($red->ejecutadas[0]['sql'], ':alcance_hospital'), 'la red filtra por hospital');
});

prueba('PER-03', 'La cadena de red (hospital NULL) se lee con IS NOT DISTINCT FROM y el candado usa una clave por cadena', function (): void {
    $pdo = new PdoGuionado([['filas' => []], ['filas' => [['pg_advisory_xact_lock' => '']]], ['filas' => []]]);
    $b = new PdoBitacora($pdo);
    igual(null, $b->ultimo(null), 'sin filas');
    $b->bloquearCadena(Escenario::H_ORIENTE);
    $b->cadena(null);
    afirmar(str_contains($pdo->ejecutadas[0]['sql'], 'hospital_id IS NOT DISTINCT FROM CAST(:hospital AS varchar)'), 'ultimo() no compara NULL bien');
    igual([['hospital' => null], ['clave' => 'audit_logs:hosp-oriente']], [$pdo->ejecutadas[0]['parametros'], $pdo->ejecutadas[1]['parametros']], 'parámetros');
});

prueba('PER-04', 'Hidratar una fila: jsonb reordenado y sello char(64) → el sello recalculado SIGUE coincidiendo', function () use ($perMov, $perFila): void {
    $original = $perMov(['detalle' => ['zeta' => 1, 'alfa' => ['b' => 2, 'a' => 1]]]);
    $sello = Sello::calcular(Sello::GENESIS, 1, $original);
    $pdo = new PdoGuionado([['filas' => [$perFila(['detalle' => '{"alfa": {"a": 1, "b": 2}, "zeta": 1}', 'secuencia' => 1, 'sello' => $sello . '  '])]]]);
    $leido = (new PdoBitacora($pdo))->porId(7);
    igual([7, $sello, true], [$leido->id, $leido->sello, hash_equals(Sello::calcular(Sello::GENESIS, 1, $leido), $leido->sello)], 'lectura');
});

prueba('PER-05', 'Cualquier PDOException (08006, 23505, 22001) → ErrorPersistencia, sin SQL ni valores en el mensaje', function () use ($perMov): void {
    foreach (['08006', '23505', '22001'] as $estado) {
        $e = lanza(ErrorPersistencia::class, fn () => (new PdoBitacora(new PdoQueFalla($estado)))->agregar($perMov()->sellado(1, str_repeat('c', 64))), $estado);
        igual([$estado, 'La bitácora no está disponible.'], [$e->sqlState, $e->getMessage()], $estado);
    }
});

prueba('PER-06', 'PdoTransaccion: COMMIT al terminar; ROLLBACK y la MISMA excepción si falla la operación; COMMIT caído → ErrorPersistencia', function (): void {
    $pdo = new PdoGuionado();
    igual('ok', (new PdoTransaccion($pdo))->ejecutar(fn () => 'ok'), 'resultado');
    $fallo = new DomainException('regla');
    igual($fallo, lanza(DomainException::class, fn () => (new PdoTransaccion($pdo))->ejecutar(fn () => throw $fallo), 'operación'), 'misma excepción');
    $pdo->fallarCommit = true;
    lanza(ErrorPersistencia::class, fn () => (new PdoTransaccion($pdo))->ejecutar(fn () => 'ok'), 'commit');
    igual(['BEGIN', 'COMMIT', 'BEGIN', 'ROLLBACK', 'BEGIN', 'ROLLBACK'], $pdo->transaccion, 'secuencia');
});
