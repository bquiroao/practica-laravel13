<?php

declare(strict_types=1);

/*
 * Integración con PostgreSQL REAL. Solo corre si AUDITORIA_DB_DSN está definida; si no, se omite.
 * Cada ejecución crea un esquema temporal, aplica database/schema.sql y lo elimina al final.
 *   AUDITORIA_DB_DSN="pgsql:host=127.0.0.1;port=5432;dbname=micro_his_auditoria" AUDITORIA_DB_USUARIO=… AUDITORIA_DB_CLAVE=… php tests/ejecutar.php
 */

use Auditoria\Aplicacion\Comandos\ComandoAncla;
use Auditoria\Aplicacion\Comandos\ComandoConsultar;
use Auditoria\Aplicacion\Comandos\ComandoRegistrar;
use Auditoria\Aplicacion\Comandos\ComandoVerificar;
use Auditoria\Dominio\Modelo\Movimiento;
use Auditoria\Dominio\Modelo\SolicitudRegistro;
use Auditoria\Persistencia\Configuracion\PoliticaAccesoEstatica;
use Auditoria\Persistencia\Pdo\FabricaConexion;
use Auditoria\Persistencia\Pdo\PdoBitacora;
use Auditoria\Persistencia\Pdo\PdoTransaccion;
use Auditoria\Pruebas\Dobles\Escenario;
use Auditoria\Pruebas\Dobles\RelojFijo;

$intDsn = getenv('AUDITORIA_DB_DSN') ?: null;
$intEsquema = 'prueba_auditoria_' . getmypid();
$intPdo = null;

/** PDO del esquema temporal + casos de uso reales sobre PdoBitacora. */
$intSistema = static function () use (&$intPdo, $intDsn, $intEsquema): object {
    if ($intDsn === null) {
        omitir('defina AUDITORIA_DB_DSN para ejecutarla');
    }
    if ($intPdo === null) {
        $intPdo = FabricaConexion::crear($intDsn, getenv('AUDITORIA_DB_USUARIO') ?: 'postgres', getenv('AUDITORIA_DB_CLAVE') ?: '');
        $intPdo->exec("CREATE SCHEMA $intEsquema");
        $intPdo->exec("SET search_path TO $intEsquema");
        $intPdo->exec((string) file_get_contents(dirname(__DIR__, 2) . '/database/schema.sql'));
        register_shutdown_function(static fn () => $intPdo->exec("DROP SCHEMA $intEsquema CASCADE"));
    }
    $intPdo->exec('ALTER TABLE audit_logs DISABLE TRIGGER audit_logs_sin_truncate');
    $intPdo->exec('TRUNCATE audit_logs RESTART IDENTITY');
    $intPdo->exec('ALTER TABLE audit_logs ENABLE TRIGGER audit_logs_sin_truncate');
    $config = Escenario::config();
    $contador = 0;

    return (object) ['pdo' => $intPdo, 'casos' => construirCasosDeUso($config, new PdoBitacora($intPdo), new PdoTransaccion($intPdo),
        new PoliticaAccesoEstatica($config['permisos']), new RelojFijo(), static function () use (&$contador): string {
            return 'sis-' . ++$contador;
        })];
};

prueba('INT-01', 'PostgreSQL: registrar y verificar; el detalle pasa por jsonb (claves reordenadas) y la cadena sigue ÍNTEGRA', function () use ($intSistema): void {
    $s = $intSistema();
    $r = $s->casos['registrar']->ejecutar(new ComandoRegistrar('svc-pacientes', Escenario::H_ORIENTE,
        // jsonb ordena las claves por longitud y luego por bytes: «zz_clave_larga» deja de ser la primera.
        Escenario::mov(['detalle' => ['zz_clave_larga' => 'z', 'codigo' => 'P-000001', 'a' => ['bb' => 2, 'a' => 1]]])));
    $s->casos['registrar']->ejecutar(new ComandoRegistrar('svc-pacientes', Escenario::H_ORIENTE, Escenario::mov(['event_id' => 'ev-2'])));
    igual(['creado', 1], [$r->estado, $r->datos['id']], 'registro');
    $fila = $s->pdo->query("SELECT detalle::text AS d, ocurrido_en::text AS o FROM audit_logs WHERE id = 1")->fetch();
    igual('{"a": {"a": 1, "bb": 2}, "codigo": "P-000001", "zz_clave_larga": "z"}', $fila['d'], 'jsonb reordenó las claves');
    igual('2026-10-21 15:15:00+00', $fila['o'], 'fecha guardada en UTC');
    igual(['estado' => 'INTEGRA', 'verificados' => 2], $s->casos['verificar']->ejecutar(new ComandoVerificar('superadmin', Escenario::H_ORIENTE))->datos, 'verificación');
});

prueba('INT-02', 'PostgreSQL: el trigger rechaza UPDATE, DELETE y TRUNCATE sobre audit_logs (solo inserción)', function () use ($intSistema): void {
    $s = $intSistema();
    $s->casos['registrar']->ejecutar(new ComandoRegistrar('svc-pacientes', Escenario::H_ORIENTE, Escenario::mov()));
    foreach (["UPDATE audit_logs SET usuario_id = 'u-x'", 'DELETE FROM audit_logs', 'TRUNCATE audit_logs'] as $sql) {
        $e = lanza(PDOException::class, fn () => $s->pdo->exec($sql), $sql);
        afirmar(str_contains($e->getMessage(), 'solo inserción'), "$sql: " . $e->getMessage());
    }
    igual(1, (int) $s->pdo->query('SELECT count(*) FROM audit_logs')->fetchColumn(), 'la fila sigue');
});

prueba('INT-03', 'PostgreSQL: consulta filtrada real (rango local, usuario, módulo) y paginación con cursor sobre 52 filas', function () use ($intSistema): void {
    $s = $intSistema();
    for ($i = 1; $i <= 52; $i++) {
        $s->casos['registrar']->ejecutar(new ComandoRegistrar('svc-pacientes', Escenario::H_ORIENTE, Escenario::mov(['event_id' => "ev-$i",
            'usuario' => $i === 52 ? 'u-luis' : 'u-ana', 'ocurrido_en' => '2026-10-20T23:30:00-06:00'])));
    }
    $consultar = static fn (array $p): array => $s->casos['consultar']->ejecutar(new ComandoConsultar('auditor-oriente', Escenario::H_ORIENTE, $p))->datos;
    $dia20 = ['desde' => '2026-10-20', 'hasta' => '2026-10-20', 'modulo' => 'pacientes'];
    $p1 = $consultar($dia20 + ['usuario' => 'u-ana']);
    igual([50, 51, 2], [count($p1['movimientos']), $p1['movimientos'][0]['id'], $p1['movimientos'][49]['id']], 'primera página (23:30 local = 05:30 UTC del 21)');
    $p2 = $consultar($dia20 + ['usuario' => 'u-ana', 'cursor' => $p1['cursor_siguiente']]);
    igual([[1], null], [array_column($p2['movimientos'], 'id'), $p2['cursor_siguiente']], 'segunda página');
    igual([], $consultar(['desde' => '2026-10-21', 'hasta' => '2026-10-21', 'usuario' => 'u-ana'])['movimientos'], 'el 21 local no los incluye');
});

prueba('INT-04', 'PostgreSQL: ROLLBACK real; si la operación falla dentro de la transacción no queda la fila ni avanza la cadena', function () use ($intSistema): void {
    $s = $intSistema();
    $bitacora = new PdoBitacora($s->pdo);
    $m = Movimiento::desdeSolicitud(new SolicitudRegistro('svc-pacientes', Escenario::H_ORIENTE, Escenario::mov()));
    lanza(RuntimeException::class, fn () => (new PdoTransaccion($s->pdo))->ejecutar(function () use ($bitacora, $m): void {
        $bitacora->agregar($m->sellado(1, str_repeat('d', 64)));
        throw new RuntimeException('falla después del INSERT');
    }), 'operación');
    igual([0, null], [(int) $s->pdo->query('SELECT count(*) FROM audit_logs')->fetchColumn(), $bitacora->ultimo(Escenario::H_ORIENTE)], 'sin fila');
});

prueba('INT-05', 'PostgreSQL: un administrador desactiva el trigger y cambia una fila con SQL; la verificación la DETECTA', function () use ($intSistema): void {
    $s = $intSistema();
    for ($i = 1; $i <= 3; $i++) {
        $s->casos['registrar']->ejecutar(new ComandoRegistrar('svc-pacientes', Escenario::H_ORIENTE, Escenario::mov(['event_id' => "ev-$i"])));
    }
    $s->pdo->exec('ALTER TABLE audit_logs DISABLE TRIGGER audit_logs_sin_update_delete');
    $s->pdo->exec("UPDATE audit_logs SET usuario_id = 'u-encubierto' WHERE id = 2");
    $s->pdo->exec('ALTER TABLE audit_logs ENABLE TRIGGER audit_logs_sin_update_delete');
    $v = $s->casos['verificar']->ejecutar(new ComandoVerificar('superadmin', Escenario::H_ORIENTE))->datos;
    igual(['ALTERADA', 2], [$v['estado'], $v['primera_secuencia']], 'detección');
});

prueba('INT-07', 'PostgreSQL: se borra la última fila con el trigger desactivado; solo el ancla publicada antes lo delata (TRUNCADA)', function () use ($intSistema): void {
    $s = $intSistema();
    for ($i = 1; $i <= 3; $i++) {
        $s->casos['registrar']->ejecutar(new ComandoRegistrar('svc-pacientes', Escenario::H_ORIENTE, Escenario::mov(['event_id' => "ev-$i"])));
    }
    $ancla = $s->casos['ancla']->ejecutar(new ComandoAncla('superadmin', Escenario::H_ORIENTE))->datos;
    $s->pdo->exec('ALTER TABLE audit_logs DISABLE TRIGGER audit_logs_sin_update_delete');
    $s->pdo->exec('DELETE FROM audit_logs WHERE id = 3');
    $s->pdo->exec('ALTER TABLE audit_logs ENABLE TRIGGER audit_logs_sin_update_delete');
    $verificar = static fn (?array $a): string => $s->casos['verificar']->ejecutar(new ComandoVerificar('superadmin', Escenario::H_ORIENTE, $a))->datos['estado'];
    igual(['INTEGRA', 'TRUNCADA'], [$verificar(null), $verificar(['secuencia' => $ancla['secuencia'], 'sello' => $ancla['sello']])], 'sin ancla / con ancla');
});

prueba('INT-08', 'PostgreSQL (semana 6): la base sola impide una corrección sin motivo y una segunda corrección del mismo original', function () use ($intSistema): void {
    $s = $intSistema();
    $s->casos['registrar']->ejecutar(new ComandoRegistrar('svc-pacientes', Escenario::H_ORIENTE, Escenario::mov()));
    $c = $s->casos['registrar']->ejecutar(new ComandoRegistrar('svc-pacientes', Escenario::H_ORIENTE, Escenario::mov(['event_id' => 'ev-c',
        'usuario' => 'u-correcto', 'corrige_evento' => 'ev-1', 'motivo_correccion' => 'Usuario equivocado en el origen'])));
    igual('creado', $c->estado, 'corrección por el caso de uso');
    $insertar = static fn (string $evento, string $motivo, int $secuencia): string => "INSERT INTO audit_logs (emisor, event_id, hospital_id, usuario_id,
        modulo, accion, ocurrido_en, resultado, secuencia, sello, corrige_a, motivo_correccion) VALUES ('svc-pacientes', '$evento', 'hosp-oriente', 'u',
        'pacientes', 'PACIENTE_REGISTRADO', now(), 'exito', $secuencia, repeat('b', 64), 1, $motivo)";
    $sinMotivo = lanza(PDOException::class, fn () => $s->pdo->exec($insertar('ev-d', 'NULL', 10)), 'sin motivo');
    igual('23514', $sinMotivo->errorInfo[0], 'CHECK de corrección completa');
    $segunda = lanza(PDOException::class, fn () => $s->pdo->exec($insertar('ev-e', "'otra vez el mismo original'", 11)), 'segunda corrección');
    igual('23505', $segunda->errorInfo[0], 'único por original');
});

prueba('INT-06', 'PostgreSQL: la restricción única (emisor, event_id) y la de (cadena, secuencia) existen en el esquema', function () use ($intSistema): void {
    $s = $intSistema();
    $indices = $s->pdo->query("SELECT indexname FROM pg_indexes WHERE tablename = 'audit_logs' ORDER BY indexname")->fetchAll(PDO::FETCH_COLUMN);
    foreach (['audit_logs_evento_unico', 'audit_logs_cadena_unica', 'audit_logs_hospital_fecha', 'audit_logs_usuario_fecha', 'audit_logs_modulo_fecha'] as $indice) {
        afirmar(in_array($indice, $indices, true), "falta el índice $indice");
    }
});
