<?php

declare(strict_types=1);

/**
 * Semana 13 · Reproduce los hallazgos de la autorrevisión del autor que se pueden ejecutar.
 * No corrige nada: deja la evidencia que el moderador verificará después del retrabajo.
 *
 * Uso: php herramientas/verificar-autorrevision.php   (desde docs/modulos/rbac/semana-13)
 */
$micro = dirname(__DIR__, 2) . '/micro-his-rbac';
require $micro . '/bootstrap/contenedor.php';
foreach (glob($micro . '/tests/Dobles/*.php') as $archivo) {
    require_once $archivo;
}

use Rbac\Presentacion\Http\Solicitud;
use Rbac\Pruebas\Dobles\Escenario;

$http = static fn (string $metodo, string $ruta, string $token, string $tenant, ?array $cuerpo = null): Solicitud
    => new Solicitud($metodo, $ruta, ['Authorization' => "Bearer $token", 'X-Tenant-ID' => $tenant], $cuerpo, '10.20.5.14');

// AR-01 · El punto de entrada REAL (crearKernel + config.php) acepta los tokens de demostración.
putenv('MICROHIS_PERSISTENCIA=memoria');
putenv('MICROHIS_RESPALDO=' . sys_get_temp_dir() . '/rbac-respaldo-autorrevision.jsonl');
$config = require $micro . '/config/config.php';
$kernel = crearKernel($config);
$r = $kernel->manejar($http('POST', '/api/v1/roles/asignaciones', 'token-admin-oriente', 'hosp-oriente',
    ['usuario_id' => 'nuevo-01', 'rol' => 'medico', 'valido_desde' => '2026-11-15']));
printf("AR-01  crearKernel(config.php) + «Bearer token-admin-oriente» → asignar rol: HTTP %d %s\n", $r->estado, $r->cuerpo['codigo'] ?? '');
printf("       tokens estáticos aceptados por config.php: %d (%s)\n", count($config['tokens_demo']), implode(', ', array_keys($config['tokens_demo'])));

// AR-02 · R-03 solo impide duplicar membresías ABIERTAS: con fecha de fin, la misma asignación entra dos veces.
foreach (['sin fin' => null, 'con fin' => '2026-12-31'] as $caso => $hasta) {
    $e = new Escenario();
    $cuerpo = ['usuario_id' => 'nuevo-01', 'rol' => 'laboratorio', 'valido_desde' => '2026-11-15'] + ($hasta ? ['valido_hasta' => $hasta] : []);
    $estados = [];
    for ($i = 0; $i < 2; $i++) {
        $estados[] = $e->kernel->manejar(Escenario::http('POST', '/api/v1/roles/asignaciones', 'token-admin-oriente', Escenario::H_ORIENTE, $cuerpo))->estado;
    }
    $activas = count(array_filter($e->membresias->membresiasDe('nuevo-01', Escenario::H_ORIENTE), static fn ($m): bool => $m->rol === 'laboratorio' && $m->estado === 'active'));
    printf("AR-02  misma asignación dos veces (%s): HTTP %s → membresías activas de laboratorio: %d\n", $caso, implode(' y ', $estados), $activas);
}

// AR-03 · Un token de Norte que sondea Oriente: /decisiones deja evidencia; /permisos-efectivos no.
$e = new Escenario();
$antes = count($e->auditoria->eventos());
$e->kernel->manejar(Escenario::http('POST', '/api/v1/autorizaciones/permisos-efectivos', 'token-admin-norte', Escenario::H_ORIENTE, ['permisos' => ['roles.asignar', 'auditoria.consultar']]));
$trasEfectivos = count($e->auditoria->eventos()) - $antes;
$e->kernel->manejar(Escenario::http('POST', '/api/v1/autorizaciones/decisiones', 'token-admin-norte', Escenario::H_ORIENTE, ['permiso' => 'roles.asignar', 'recurso' => 'sondeo']));
$trasDecision = count($e->auditoria->eventos()) - $antes - $trasEfectivos;
$motivo = $e->auditoria->eventos()[count($e->auditoria->eventos()) - 1]->datos['motivo'] ?? '—';
printf("AR-03  token de hosp-norte contra hosp-oriente: permisos-efectivos deja %d evento(s); decisiones deja %d (%s)\n", $trasEfectivos, $trasDecision, $motivo);
