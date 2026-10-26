<?php

declare(strict_types=1);

/*
 * Semana 4 — Suite de CONTRATO del Repository Bitacora: las MISMAS 8 verificaciones corren contra el
 * adaptador InMemory (CON-MEM-*) y contra el PDO sobre PostgreSQL (CON-PDO-*, solo con AUDITORIA_DB_DSN).
 * Si un adaptador se aparta del contrato, falla su fila y no la del otro.
 */

use Auditoria\Aplicacion\Puertos\Transaccion;
use Auditoria\Dominio\Excepciones\ErrorPersistencia;
use Auditoria\Dominio\Filtros\FiltroRangoFechas;
use Auditoria\Dominio\Filtros\FiltroUsuario;
use Auditoria\Dominio\Modelo\ConsultaMovimientos;
use Auditoria\Dominio\Modelo\Movimiento;
use Auditoria\Dominio\Modelo\Sello;
use Auditoria\Dominio\Modelo\SolicitudRegistro;
use Auditoria\Dominio\Puertos\Bitacora;
use Auditoria\Persistencia\FabricaAdaptadores;
use Auditoria\Persistencia\Pdo\FabricaConexion;
use Auditoria\Pruebas\Dobles\Escenario;

/** Movimiento sellado listo para agregar (la secuencia la elige la prueba). */
$conMov = static function (int $secuencia, array $cambios = [], string $emisor = 'svc-pacientes'): Movimiento {
    $m = Movimiento::desdeSolicitud(new SolicitudRegistro($emisor, $cambios['hospital'] ?? Escenario::H_ORIENTE, Escenario::mov($cambios)));

    return $m->sellado($secuencia, Sello::calcular(Sello::GENESIS, $secuencia, $m));
};

/** @return array{0: Bitacora, 1: Transaccion} adaptadores PDO sobre un esquema temporal vacío */
$conPdo = static function (): array {
    static $pdo = null;
    $dsn = getenv('AUDITORIA_DB_DSN') ?: null;
    if ($dsn === null) {
        omitir('defina AUDITORIA_DB_DSN para ejecutarla');
    }
    $esquema = 'contrato_auditoria_' . getmypid();
    if ($pdo === null) {
        $pdo = FabricaConexion::crear($dsn, getenv('AUDITORIA_DB_USUARIO') ?: 'postgres', getenv('AUDITORIA_DB_CLAVE') ?: '');
        $pdo->exec("CREATE SCHEMA $esquema");
        $pdo->exec("SET search_path TO $esquema");
        $pdo->exec((string) file_get_contents(dirname(__DIR__, 2) . '/database/schema.sql'));
        register_shutdown_function(static fn () => $pdo->exec("DROP SCHEMA $esquema CASCADE"));
    }
    $pdo->exec('ALTER TABLE audit_logs DISABLE TRIGGER audit_logs_sin_truncate');
    $pdo->exec('TRUNCATE audit_logs RESTART IDENTITY');
    $pdo->exec('ALTER TABLE audit_logs ENABLE TRIGGER audit_logs_sin_truncate');
    $conjunto = FabricaAdaptadores::pdo($pdo);

    return [$conjunto->bitacora, $conjunto->transaccion];
};

$conMemoria = static function (): array {
    $conjunto = FabricaAdaptadores::enMemoria();

    return [$conjunto->bitacora, $conjunto->transaccion];
};

$contrato = static function (string $prefijo, callable $fabrica) use ($conMov): void {
    prueba("CON-$prefijo-01", "[$prefijo] agregar() asigna ids crecientes y lo leído es idéntico a lo guardado (contenido, secuencia, sello)", function () use ($fabrica, $conMov): void {
        [$b] = $fabrica();
        $a = $b->agregar($conMov(1, ['event_id' => 'a']));
        $c = $b->agregar($conMov(2, ['event_id' => 'c']));
        afirmar($a->id !== null && $c->id > $a->id, 'ids no crecientes');
        $leido = $b->porId($a->id);
        igual([$a->contenido(), 1, $a->sello], [$leido->contenido(), $leido->secuencia, $leido->sello], 'lectura');
        igual(null, $b->porId(999), 'inexistente');
    });

    prueba("CON-$prefijo-02", "[$prefijo] porEvento() busca por (emisor, event_id): el mismo event_id de otro emisor es otro evento", function () use ($fabrica, $conMov): void {
        [$b] = $fabrica();
        $b->agregar($conMov(1, ['event_id' => 'x']));
        igual(['x', null], [$b->porEvento('svc-pacientes', 'x')?->eventId, $b->porEvento('svc-rbac', 'x')], 'por emisor');
    });

    prueba("CON-$prefijo-03", "[$prefijo] el mismo (emisor, event_id) dos veces → ErrorPersistencia, y la primera fila sigue", function () use ($fabrica, $conMov): void {
        [$b] = $fabrica();
        $b->agregar($conMov(1, ['event_id' => 'dup']));
        lanza(ErrorPersistencia::class, fn () => $b->agregar($conMov(2, ['event_id' => 'dup'])), 'duplicado');
        igual(1, count($b->cadena(Escenario::H_ORIENTE)), 'filas');
    });

    prueba("CON-$prefijo-04", "[$prefijo] la misma secuencia dos veces en una cadena → ErrorPersistencia; en otra cadena (u otro hospital) no", function () use ($fabrica, $conMov): void {
        [$b] = $fabrica();
        $b->agregar($conMov(1, ['event_id' => 'a']));
        lanza(ErrorPersistencia::class, fn () => $b->agregar($conMov(1, ['event_id' => 'b'])), 'misma cadena');
        $b->agregar($conMov(1, ['event_id' => 'c', 'hospital' => Escenario::H_NORTE]));
        $b->agregar($conMov(1, ['event_id' => 'd', 'hospital' => null]));
        igual([1, 1, 1], [count($b->cadena(Escenario::H_ORIENTE)), count($b->cadena(Escenario::H_NORTE)), count($b->cadena(null))], 'cadenas');
    });

    prueba("CON-$prefijo-05", "[$prefijo] ultimo() y cadena() por hospital, en orden de secuencia; la cadena de red (null) es aparte", function () use ($fabrica, $conMov): void {
        [$b] = $fabrica();
        foreach ([[2, 'b'], [1, 'a'], [3, 'c']] as [$secuencia, $evento]) {
            $b->agregar($conMov($secuencia, ['event_id' => $evento]));
        }
        igual([[1, 2, 3], 3, null], [array_map(static fn (Movimiento $m): int => $m->secuencia, $b->cadena(Escenario::H_ORIENTE)),
            $b->ultimo(Escenario::H_ORIENTE)?->secuencia, $b->ultimo(null)], 'cadena');
    });

    prueba("CON-$prefijo-06", "[$prefijo] buscar(): alcance, filtros, orden (fecha, id) descendente, límite y cursor", function () use ($fabrica, $conMov): void {
        [$b] = $fabrica();
        $ids = [];
        foreach ([['a', '2026-10-20T10:00:00-06:00', 'u-ana'], ['b', '2026-10-20T10:00:00-06:00', 'u-ana'], ['c', '2026-10-20T11:00:00-06:00', 'u-luis'],
            ['d', '2026-10-21T10:00:00-06:00', 'u-ana']] as $i => [$evento, $fecha, $usuario]) {
            $ids[$evento] = $b->agregar($conMov($i + 1, ['event_id' => $evento, 'ocurrido_en' => $fecha, 'usuario' => $usuario]))->id;
        }
        $b->agregar($conMov(1, ['event_id' => 'n', 'hospital' => Escenario::H_NORTE, 'ocurrido_en' => '2026-10-20T12:00:00-06:00']));
        $p = ['desde' => '2026-10-20', 'hasta' => '2026-10-20'];
        $consulta = static fn (array $extra = [], int $limite = 10, ?array $cursor = null, bool $red = false): array => array_map(
            static fn (Movimiento $m): string => $m->eventId,
            $b->buscar(new ConsultaMovimientos($red ? null : Escenario::H_ORIENTE, $red, [new FiltroRangoFechas(), ...($extra ? [new FiltroUsuario()] : [])],
                $p + $extra, $limite, $cursor)));
        igual(['c', 'b', 'a'], $consulta(), 'orden: c (11:00), luego b y a a la misma hora, por id descendente');
        igual(['b', 'a'], $consulta(['usuario' => 'u-ana']), 'filtro por usuario');
        igual(['c', 'b'], $consulta([], 2), 'límite');
        igual(['a'], $consulta([], 10, [new DateTimeImmutable('2026-10-20T16:00:00Z'), $ids['b']]), 'cursor después de b');
        igual(['n', 'c', 'b', 'a'], $consulta([], 10, null, true), 'red: también norte');
    });

    prueba("CON-$prefijo-07", "[$prefijo] un detalle con claves desordenadas se lee de forma que su sello recalculado coincide", function () use ($fabrica, $conMov): void {
        [$b] = $fabrica();
        $guardado = $b->agregar($conMov(1, ['detalle' => ['zz_clave_larga' => 1, 'a' => ['y' => 2, 'x' => 1]]]));
        $leido = $b->porId($guardado->id);
        afirmar(hash_equals(Sello::calcular(Sello::GENESIS, 1, $leido), $leido->sello), 'el sello recalculado no coincide');
    });

    prueba("CON-$prefijo-09", "[$prefijo] semana 6: correccionDe() y correccionesDe(); una segunda corrección del mismo original → ErrorPersistencia", function () use ($fabrica, $conMov): void {
        [$b] = $fabrica();
        $original = $b->agregar($conMov(1, ['event_id' => 'o']));
        $otro = $b->agregar($conMov(2, ['event_id' => 'x']));
        $correccion = $b->agregar($conMov(3, ['event_id' => 'c', 'motivo_correccion' => 'Usuario equivocado en el origen'])->corrigiendo($original->id));
        igual([$correccion->id, null], [$b->correccionDe($original->id)?->id, $b->correccionDe($otro->id)], 'correccionDe');
        igual([$original->id => $correccion->id], $b->correccionesDe([$original->id, $otro->id, 999]), 'correccionesDe');
        igual([], $b->correccionesDe([]), 'lista vacía');
        igual([$original->id, 'Usuario equivocado en el origen'], [$b->porId($correccion->id)->corrigeA, $b->porId($correccion->id)->motivoCorreccion], 'lectura');
        lanza(ErrorPersistencia::class, fn () => $b->agregar($conMov(4, ['event_id' => 'd', 'motivo_correccion' => 'Otra corrección más'])->corrigiendo($original->id)),
            'segunda corrección');
    });

    prueba("CON-$prefijo-08", "[$prefijo] Transaccion: lo agregado dentro de una operación que lanza no queda", function () use ($fabrica, $conMov): void {
        [$b, $t] = $fabrica();
        lanza(RuntimeException::class, fn () => $t->ejecutar(function () use ($b, $conMov): void {
            $b->agregar($conMov(1));
            throw new RuntimeException('falla');
        }), 'operación');
        igual([null, 'ok'], [$b->ultimo(Escenario::H_ORIENTE), $t->ejecutar(fn () => 'ok')], 'rollback y siguiente transacción');
    });
};

$contrato('MEM', $conMemoria);
$contrato('PDO', $conPdo);
