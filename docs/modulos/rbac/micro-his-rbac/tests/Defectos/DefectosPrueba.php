<?php

declare(strict_types=1);

/*
 * Semana 15 · Defectos ABIERTOS. Cada prueba afirma el comportamiento CORRECTO y hoy FALLA: es la
 * reproducción del defecto. Solo corren con  php tests/ejecutar.php --defectos  (no bloquean la suite).
 * Cuando un defecto se corrige, su prueba pasa y se mueve a la suite normal (regresión).
 * Registro: docs/modulos/rbac/semana-15/registro-defectos.md
 */

use Rbac\Persistencia\ConjuntoAdaptadores;
use Rbac\Persistencia\EnMemoria\FabricaEnMemoria;
use Rbac\Persistencia\EnMemoria\RegistroAuditoriaEnMemoria;
use Rbac\Presentacion\Http\Solicitud;
use Rbac\Pruebas\Dobles\Escenario;
use Rbac\Pruebas\Dobles\RegistroAuditoriaConFallas;

const DEF_ASIGNAR = '/api/v1/roles/asignaciones';

prueba('DEF-01', '[Mayor · nuevo S15] Una fecha imposible (2026-02-30, 2026-00-10) debe rechazarse con 422; hoy se «corrige» y se guarda otra fecha', function (): void {
    $guardadas = [];
    foreach (['2026-02-30', '2026-04-31', '2026-00-10'] as $fecha) {
        $e = new Escenario();
        $r = $e->kernel->manejar(Escenario::http('POST', DEF_ASIGNAR, 'token-admin-oriente', Escenario::H_ORIENTE,
            ['usuario_id' => 'nuevo-01', 'rol' => 'medico', 'valido_desde' => $fecha]));
        $m = array_values(array_filter($e->membresias->membresiasDe('nuevo-01', Escenario::H_ORIENTE), static fn ($x): bool => $x->rol === 'medico'));
        $guardadas[] = "$fecha → HTTP {$r->estado}" . ($m ? ' guardada como ' . $m[0]->validoDesde->format('Y-m-d') : '');
        if ($r->estado !== 422) {
            continue;
        }
        array_pop($guardadas);
    }
    igual([], $guardadas, 'fechas imposibles aceptadas');
});

prueba('DEF-02', '[Mayor · AR-02] La misma asignación CON fecha de fin, enviada dos veces, debe dar 409; hoy crea dos membresías activas', function (): void {
    $e = new Escenario();
    $cuerpo = ['usuario_id' => 'nuevo-01', 'rol' => 'laboratorio', 'valido_desde' => '2026-11-15', 'valido_hasta' => '2026-12-31'];
    $estados = [];
    for ($i = 0; $i < 2; $i++) {
        $estados[] = $e->kernel->manejar(Escenario::http('POST', DEF_ASIGNAR, 'token-admin-oriente', Escenario::H_ORIENTE, $cuerpo))->estado;
    }
    igual([201, 409], $estados, 'estados del primer y segundo envío');
});

prueba('DEF-03', '[Mayor · AR-01] Fuera de desarrollo, el punto de entrada real NO debe aceptar tokens de demostración (401); hoy asigna roles', function (): void {
    $anterior = getenv('MICROHIS_ENTORNO');
    putenv('MICROHIS_ENTORNO');                       // sin entorno declarado = no es desarrollo
    putenv('MICROHIS_PERSISTENCIA=memoria');
    putenv('MICROHIS_RESPALDO=' . sys_get_temp_dir() . '/rbac-def03.jsonl');
    try {
        $kernel = crearKernel(require dirname(__DIR__, 2) . '/config/config.php');
        $r = $kernel->manejar(new Solicitud('POST', DEF_ASIGNAR, ['Authorization' => 'Bearer token-admin-oriente', 'X-Tenant-ID' => 'hosp-oriente'],
            ['usuario_id' => 'nuevo-01', 'rol' => 'medico', 'valido_desde' => '2026-11-15']));
        igual(401, $r->estado, 'token de demostración en un arranque sin MICROHIS_ENTORNO=desarrollo');
    } finally {
        putenv('MICROHIS_PERSISTENCIA');
        putenv('MICROHIS_RESPALDO');
        $anterior === false ? putenv('MICROHIS_ENTORNO') : putenv("MICROHIS_ENTORNO=$anterior");
    }
});

prueba('DEF-04', '[Mayor · AR-04] Si fallan la bitácora Y el respaldo, debe quedar rastro en error_log con la referencia; hoy no queda nada', function (): void {
    $base = FabricaEnMemoria::crear(Escenario::datosDemo());
    $principal = new RegistroAuditoriaConFallas($base->auditoria);
    $principal->fallar = true;
    $respaldo = new RegistroAuditoriaConFallas(new RegistroAuditoriaEnMemoria());
    $respaldo->fallar = true;
    $casos = construirCasosDeUso(new ConjuntoAdaptadores($base->membresias, $base->catalogo, $base->usuarios, $principal, $base->transaccion),
        $respaldo, static fn (): string => 'corr-def04', static fn (): DateTimeImmutable => new DateTimeImmutable(Escenario::AHORA));
    $log = tempnam(sys_get_temp_dir(), 'rbac-def04-');
    $anterior = ini_set('error_log', $log);
    try {
        $r = $casos['autorizar']->ejecutar(Escenario::comandoAutorizar('ana', 'prescripciones.crear'));
        afirmar(!$r->permitido, 'sigue denegado (eso sí funciona)');
        afirmar(str_contains((string) file_get_contents($log), 'corr-def04'), 'error_log no menciona la denegación perdida corr-def04');
    } finally {
        ini_set('error_log', $anterior === false ? '' : $anterior);
        @unlink($log);
    }
});

prueba('DEF-05', '[Menor · AR-03] Un token usado contra otro hospital en permisos-efectivos debe dejar TENANT_NO_COINCIDE; hoy no deja rastro', function (): void {
    $e = new Escenario();
    $e->kernel->manejar(Escenario::http('POST', '/api/v1/autorizaciones/permisos-efectivos', 'token-admin-norte', Escenario::H_ORIENTE, ['permisos' => ['roles.asignar']]));
    igual(1, count($e->auditoria->eventosDeTipo('ACCESO_DENEGADO')), 'eventos ACCESO_DENEGADO tras el sondeo entre hospitales');
});
